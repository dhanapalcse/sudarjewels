<?php

namespace App\Domain\Orders;

use App\Domain\Commissions\CommissionService;
use App\Domain\Inventory\InventoryService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\UnitStatus;
use App\Enums\WarrantyStatus;
use App\Exceptions\BusinessRuleException;
use App\Jobs\ReportPurchaseToAnalytics;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\PurchasedUnit;
use App\Models\Refund;
use App\Models\RefundItem;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        private OrderService $orders,
        private CommissionService $commissions,
        private InventoryService $inventory,
    ) {}

    public function recordPayment(Order $order, int $amountPaise, PaymentMethod $method, ?string $reference, CarbonInterface $paidAt, User $actor, ?string $idempotencyKey = null, ?string $notes = null): Payment
    {
        if ($amountPaise <= 0) {
            throw new BusinessRuleException('Payment amount must be greater than zero.');
        }
        if (in_array($order->status, [OrderStatus::Cancelled, OrderStatus::Draft], true)) {
            throw new BusinessRuleException('Payments can only be recorded on submitted or confirmed orders.');
        }
        if ($idempotencyKey && ($existing = Payment::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        return DB::transaction(function () use ($order, $amountPaise, $method, $reference, $paidAt, $actor, $idempotencyKey, $notes) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $outstanding = $order->grand_total_paise - $order->amount_paid_paise;
            if ($amountPaise > $outstanding) {
                throw new BusinessRuleException('Payment exceeds the outstanding balance of '.\App\Support\Money::format($outstanding).'.');
            }
            $payment = Payment::create([
                'order_id' => $order->id,
                'amount_paise' => $amountPaise,
                'method' => $method,
                'reference' => $reference,
                'paid_at' => $paidAt,
                'notes' => $notes,
                'recorded_by_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
            ]);
            $order->increment('amount_paid_paise', $amountPaise);
            $this->refreshPaymentStatus($order->refresh(), $actor);
            $this->orders->history($order, 'payment', null, $method->value, 'Payment '.\App\Support\Money::format($amountPaise).($reference ? " ref {$reference}" : ''), $actor);

            return $payment;
        });
    }

    /**
     * @param  array<int, array{order_item_id:int, quantity:int, amount_paise:int}>  $lines
     */
    public function recordRefund(Order $order, int $amountPaise, array $lines, PaymentMethod $method, ?string $reference, string $reason, User $actor, ?string $idempotencyKey = null): Refund
    {
        if ($idempotencyKey && ($existing = Refund::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }
        if ($amountPaise <= 0) {
            throw new BusinessRuleException('Refund amount must be greater than zero.');
        }
        $lines = array_values(array_filter($lines, fn ($l) => (int) ($l['quantity'] ?? 0) > 0 || (int) ($l['amount_paise'] ?? 0) > 0));
        $linesTotal = array_sum(array_map(fn ($l) => (int) $l['amount_paise'], $lines));
        if ($linesTotal > $amountPaise) {
            throw new BusinessRuleException('Item refund amounts exceed the refund total.');
        }

        return DB::transaction(function () use ($order, $amountPaise, $lines, $method, $reference, $reason, $actor, $idempotencyKey) {
            $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
            $refundable = $order->amount_paid_paise - $order->amount_refunded_paise;
            if ($amountPaise > $refundable) {
                throw new BusinessRuleException('Refund exceeds the refundable amount of '.\App\Support\Money::format($refundable).'.');
            }
            $refund = Refund::create([
                'order_id' => $order->id,
                'amount_paise' => $amountPaise,
                'method' => $method,
                'reference' => $reference,
                'reason' => $reason,
                'refunded_at' => now(),
                'recorded_by_id' => $actor->id,
                'idempotency_key' => $idempotencyKey,
            ]);

            foreach ($lines as $line) {
                /** @var OrderItem $item */
                $item = $order->items()->whereKey($line['order_item_id'])->firstOrFail();
                $qty = (int) $line['quantity'];
                $alreadyRefundedAmount = (int) RefundItem::where('order_item_id', $item->id)->sum('amount_paise');
                if ($alreadyRefundedAmount + (int) $line['amount_paise'] > $item->line_total_paise) {
                    throw new BusinessRuleException("Refund for {$item->label()} exceeds its paid line total.");
                }
                $refundItem = RefundItem::create([
                    'refund_id' => $refund->id,
                    'order_item_id' => $item->id,
                    'quantity' => $qty,
                    'amount_paise' => (int) $line['amount_paise'],
                ]);
                if ($qty > 0) {
                    $this->markUnitsRefunded($item, $qty, $refundItem, $actor);
                }
                $this->commissions->reverseForRefundItem($refundItem);
            }

            $order->increment('amount_refunded_paise', $amountPaise);
            $this->refreshPaymentStatus($order->refresh(), $actor);
            $this->orders->history($order, 'refund', null, $method->value, 'Refund '.\App\Support\Money::format($amountPaise).": {$reason}", $actor);
            $this->orders->refreshFulfilmentStatus($order, $actor);

            return $refund;
        });
    }

    public function refreshPaymentStatus(Order $order, ?User $actor = null): void
    {
        $paid = $order->amount_paid_paise;
        $refunded = $order->amount_refunded_paise;
        $to = match (true) {
            $refunded > 0 && $refunded >= $paid => PaymentStatus::Refunded,
            $refunded > 0 => PaymentStatus::PartiallyRefunded,
            $paid >= $order->grand_total_paise && $paid > 0 => PaymentStatus::Paid,
            $paid > 0 => PaymentStatus::PartiallyPaid,
            default => PaymentStatus::Unpaid,
        };
        if ($to === $order->payment_status) {
            return;
        }
        $from = $order->payment_status;
        $from->assertCanTransitionTo($to);
        $order->update(['payment_status' => $to]);
        $this->orders->history($order, 'payment_status', $from->value, $to->value, null, $actor);

        if ($to === PaymentStatus::Paid && $order->analytics_reported_at === null) {
            ReportPurchaseToAnalytics::dispatch($order->id)->afterCommit();
        }
    }

    private function markUnitsRefunded(OrderItem $item, int $qty, RefundItem $refundItem, User $actor): void
    {
        // Prefer returned units, then never-dispatched units, then delivered/dispatched units.
        $order = [UnitStatus::Returned->value => 0, UnitStatus::Allocated->value => 1, UnitStatus::Delivered->value => 2, UnitStatus::Dispatched->value => 3];
        $units = PurchasedUnit::where('order_item_id', $item->id)
            ->whereIn('status', array_keys($order))
            ->lockForUpdate()->get()
            ->sortBy(fn (PurchasedUnit $u) => [$order[$u->status->value], $u->unit_number])
            ->take($qty);
        if ($units->count() < $qty) {
            throw new BusinessRuleException("Only {$units->count()} unit(s) of {$item->label()} can still be refunded.");
        }
        $releasable = 0;
        foreach ($units as $unit) {
            if ($unit->status === UnitStatus::Allocated) {
                $releasable++;
            }
            $unit->update([
                'status' => UnitStatus::Refunded,
                'refund_item_id' => $refundItem->id,
                'refunded_at' => now(),
                'warranty_status' => $unit->warranty_status === WarrantyStatus::NotEligible ? WarrantyStatus::NotEligible : WarrantyStatus::Void,
            ]);
        }
        if ($releasable > 0) {
            $this->inventory->releaseUnits($item->id, $releasable, "refund-release:refund_item:{$refundItem->id}");
        }
    }
}
