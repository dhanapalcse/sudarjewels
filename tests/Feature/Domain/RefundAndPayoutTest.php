<?php

namespace Tests\Feature\Domain;

use App\Domain\Commissions\CommissionService;
use App\Domain\Commissions\PayoutService;
use App\Domain\Orders\FulfilmentService;
use App\Domain\Orders\OrderService;
use App\Domain\Orders\PaymentService;
use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Affiliate;
use App\Models\CommissionEntry;
use App\Models\Order;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefundAndPayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Affiliate $affiliate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->admin();
        $this->affiliate = $this->affiliate();
    }

    /** Confirmed, fully paid, delivered order with 2 × ₹1,000 (commission ₹200 each line → ₹400). */
    private function paidDeliveredOrder(int $qty = 2): Order
    {
        $variant = $this->variant(100000, 10);
        $order = app(OrderService::class)->confirm($this->order($this->admin, [[$variant, $qty]], $this->affiliate), $this->admin);
        app(PaymentService::class)->recordPayment($order, $order->grand_total_paise, PaymentMethod::Upi, 'UPI1', now(), $this->admin);
        $fulfilment = app(FulfilmentService::class);
        $shipment = $fulfilment->dispatch($order, $order->units()->pluck('id')->all(), [], $this->admin);
        $fulfilment->markDelivered($shipment, now()->subDays(20), $this->admin);

        return $order->refresh();
    }

    private function makePayable(): void
    {
        app(Settings::class)->set(['commission.return_window_days' => 7]);
        app(CommissionService::class)->markEligibleAsPayable();
    }

    public function test_commission_is_pending_on_confirmation_and_not_payable_until_window_configured(): void
    {
        $order = $this->paidDeliveredOrder();
        $entry = CommissionEntry::where('order_id', $order->id)->first();
        $this->assertSame(CommissionStatus::Pending, $entry->status);
        $this->assertSame(40000, $entry->amount_paise);

        $this->assertSame(0, app(CommissionService::class)->markEligibleAsPayable(), 'Window not set → nothing becomes payable');
        $this->makePayable();
        $this->assertSame(CommissionStatus::Payable, $entry->refresh()->status);
    }

    public function test_commission_waits_for_full_payment_and_return_window(): void
    {
        app(Settings::class)->set(['commission.return_window_days' => 30]);
        $order = $this->paidDeliveredOrder(); // delivered 20 days ago
        app(CommissionService::class)->markEligibleAsPayable();
        $this->assertSame(CommissionStatus::Pending, CommissionEntry::where('order_id', $order->id)->first()->status);
    }

    public function test_partial_then_full_refund_reverses_proportionally_and_payment_status_follows(): void
    {
        $order = $this->paidDeliveredOrder();
        $item = $order->items->first();
        $payments = app(PaymentService::class);

        $payments->recordRefund($order, 100000, [['order_item_id' => $item->id, 'quantity' => 1, 'amount_paise' => 100000]], PaymentMethod::Upi, 'R1', 'One piece returned', $this->admin);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->refresh()->payment_status);
        $this->assertSame(20000, (int) CommissionEntry::where('order_id', $order->id)->sum('amount_paise'));

        $payments->recordRefund($order, 100000, [['order_item_id' => $item->id, 'quantity' => 1, 'amount_paise' => 100000]], PaymentMethod::Upi, 'R2', 'Second piece returned', $this->admin);
        $this->assertSame(PaymentStatus::Refunded, $order->refresh()->payment_status);
        $this->assertSame(0, (int) CommissionEntry::where('order_id', $order->id)->sum('amount_paise'));
        $this->assertSame(0, (int) CommissionEntry::where('order_id', $order->id)->where('status', '!=', 'cancelled')->sum('amount_paise'));
        $this->assertSame(2, $order->units()->where('status', 'refunded')->count());
        $this->assertSame(0, $order->units()->where('warranty_status', 'registered')->count());
    }

    public function test_refund_cannot_exceed_amount_paid(): void
    {
        $order = $this->paidDeliveredOrder();
        $this->expectException(BusinessRuleException::class);
        app(PaymentService::class)->recordRefund($order, $order->grand_total_paise + 1, [], PaymentMethod::Upi, null, 'too much', $this->admin);
    }

    public function test_duplicate_refund_submission_is_recorded_once(): void
    {
        $order = $this->paidDeliveredOrder();
        $item = $order->items->first();
        foreach ([1, 2] as $_) {
            app(PaymentService::class)->recordRefund($order, 50000, [['order_item_id' => $item->id, 'quantity' => 0, 'amount_paise' => 50000]], PaymentMethod::Upi, null, 'goodwill', $this->admin, 'refund-key-1');
        }
        $this->assertSame(50000, $order->refresh()->amount_refunded_paise);
    }

    public function test_refund_after_payout_creates_adjustment_against_future_earnings(): void
    {
        $order = $this->paidDeliveredOrder();
        $this->makePayable();
        $payouts = app(PayoutService::class);
        $entries = CommissionEntry::where('affiliate_id', $this->affiliate->id)->pluck('id')->all();
        $payout = $payouts->create($this->affiliate, $entries, $this->admin);
        $payouts->complete($payout, PayoutMethod::Upi, 'UTR123', now(), 40000, $this->admin);

        $item = $order->items->first();
        app(PaymentService::class)->recordRefund($order, 100000, [['order_item_id' => $item->id, 'quantity' => 1, 'amount_paise' => 100000]], PaymentMethod::Upi, 'R1', 'Return', $this->admin);

        $adjustment = CommissionEntry::where('type', CommissionType::Adjustment->value)->first();
        $this->assertNotNull($adjustment);
        $this->assertSame(-20000, $adjustment->amount_paise);
        $this->assertSame(CommissionStatus::Payable, $adjustment->status);
        $this->assertSame(CommissionStatus::Paid, CommissionEntry::where('type', 'earning')->first()->status, 'Paid history is preserved');

        // Next payout nets the adjustment against new earnings.
        $order2 = $this->paidDeliveredOrder(1);
        $this->makePayable();
        $payable = CommissionEntry::where('status', 'payable')->whereNull('payout_id')->get();
        $this->assertSame(0, (int) $payable->sum('amount_paise')); // +200 new, −200 adjustment

        $this->expectException(BusinessRuleException::class);
        $payouts->create($this->affiliate, $payable->pluck('id')->all(), $this->admin);
    }

    public function test_entries_cannot_be_in_two_active_payouts(): void
    {
        $this->paidDeliveredOrder();
        $this->makePayable();
        $ids = CommissionEntry::pluck('id')->all();
        $payouts = app(PayoutService::class);
        $payouts->create($this->affiliate, $ids, $this->admin);

        try {
            $payouts->create($this->affiliate, $ids, $this->admin);
            $this->fail('Duplicate payout should be rejected');
        } catch (BusinessRuleException) {
        }
        $this->assertSame(1, \App\Models\AffiliatePayout::count());
    }

    public function test_failed_payout_releases_entries_for_a_new_payout_and_keeps_history(): void
    {
        $this->paidDeliveredOrder();
        $this->makePayable();
        $ids = CommissionEntry::pluck('id')->all();
        $payouts = app(PayoutService::class);
        $first = $payouts->create($this->affiliate, $ids, $this->admin);
        $payouts->abandon($first, PayoutStatus::Failed, 'Bank rejected', $this->admin);

        $second = $payouts->create($this->affiliate, $ids, $this->admin);
        $payouts->complete($second, PayoutMethod::BankTransfer, 'NEFT9', now(), 40000, $this->admin);

        $this->assertSame(PayoutStatus::Failed, $first->refresh()->status);
        $this->assertSame(1, $first->items()->count());
        $this->assertSame(CommissionStatus::Paid, CommissionEntry::first()->status);
        $this->assertSame($second->id, CommissionEntry::first()->payout_id);
    }

    public function test_payout_amount_must_match(): void
    {
        $this->paidDeliveredOrder();
        $this->makePayable();
        $payout = app(PayoutService::class)->create($this->affiliate, CommissionEntry::pluck('id')->all(), $this->admin);
        $this->expectException(BusinessRuleException::class);
        app(PayoutService::class)->complete($payout, PayoutMethod::Upi, 'X', now(), 39999, $this->admin);
    }

    public function test_cancelling_confirmed_order_retires_pending_commission(): void
    {
        $variant = $this->variant(100000, 5);
        $order = app(OrderService::class)->confirm($this->order($this->admin, [[$variant, 1]], $this->affiliate), $this->admin);
        app(OrderService::class)->cancel($order, $this->admin, 'Customer cancelled');

        $this->assertSame(0, (int) CommissionEntry::where('order_id', $order->id)->sum('amount_paise'));
        $this->assertSame(0, CommissionEntry::where('order_id', $order->id)->where('status', '!=', 'cancelled')->count());
    }

    public function test_repeated_event_processing_creates_one_earning(): void
    {
        $order = $this->paidDeliveredOrder();
        app(CommissionService::class)->createEarningsForOrder($order);
        app(CommissionService::class)->createEarningsForOrder($order);
        $this->assertSame(1, CommissionEntry::where('order_id', $order->id)->where('type', 'earning')->count());
    }
}
