<?php

namespace Tests\Feature\Domain;

use App\Domain\Orders\FulfilmentService;
use App\Domain\Orders\OrderService;
use App\Domain\Orders\PaymentService;
use App\Domain\Warranty\WarrantyCalculator;
use App\Domain\Warranty\WarrantyService;
use App\Enums\PaymentMethod;
use App\Enums\WarrantyStatus;
use App\Models\WarrantyRegistration;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyTest extends TestCase
{
    use RefreshDatabase;

    #[\PHPUnit\Framework\Attributes\DataProvider('boundaries')]
    public function test_calendar_month_boundaries(string $deliveredLocal, string $expectedEndLocal): void
    {
        $start = CarbonImmutable::parse($deliveredLocal, 'Asia/Kolkata');
        [, $end] = WarrantyCalculator::coverage($start, 3);
        $this->assertSame($expectedEndLocal, $end->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
    }

    public static function boundaries(): array
    {
        return [
            'mid-month' => ['2026-01-15 10:00', '2026-04-15 23:59:59'],
            '31st into 30-day month' => ['2026-01-31 18:00', '2026-04-30 23:59:59'],
            'Nov 30 into non-leap Feb' => ['2026-11-30 09:00', '2027-02-28 23:59:59'],
            'Nov 30 into leap Feb' => ['2027-11-30 09:00', '2028-02-29 23:59:59'],
            'late night IST is still the same local day' => ['2026-03-31 23:30', '2026-06-30 23:59:59'],
            'year rollover' => ['2026-12-01 00:05', '2027-03-01 23:59:59'],
        ];
    }

    public function test_coverage_uses_business_timezone_not_utc(): void
    {
        // 20:00 UTC on 31 Jan is 01:30 IST on 1 Feb → ends 1 May IST.
        [, $end] = WarrantyCalculator::coverage(CarbonImmutable::parse('2026-01-31 20:00', 'UTC'), 3);
        $this->assertSame('2026-05-01 23:59:59', $end->setTimezone('Asia/Kolkata')->format('Y-m-d H:i:s'));
    }

    public function test_partial_delivery_identical_units_and_repeat_registration(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(50000, 5);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 3]]), $admin);
        $units = $order->units()->orderBy('unit_number')->get();
        $this->assertCount(3, $units);

        $fulfilment = app(FulfilmentService::class);
        $s1 = $fulfilment->dispatch($order, [$units[0]->id, $units[1]->id], [], $admin);
        $fulfilment->markDelivered($s1, CarbonImmutable::parse('2026-01-10 12:00', 'Asia/Kolkata'), $admin);

        $warranty = app(WarrantyService::class);
        $reg = $warranty->register($order, 'Priya S', '9876543210');
        $this->assertSame(2, $reg->units()->count());
        $this->assertSame(WarrantyStatus::Unregistered, $units[2]->refresh()->warranty_status);

        // Later shipment of the third identical unit attaches automatically.
        $s2 = $fulfilment->dispatch($order, [$units[2]->id], [], $admin);
        $fulfilment->markDelivered($s2, CarbonImmutable::parse('2026-02-05 12:00', 'Asia/Kolkata'), $admin);
        $this->assertSame(3, $reg->units()->count());

        $again = $warranty->register($order, 'Someone Else', '9876543210');
        $this->assertSame($reg->id, $again->id, 'Repeat registration returns the existing record');
        $this->assertSame('Priya S', $again->registered_name);
        $this->assertSame(1, WarrantyRegistration::count());

        $u0 = $units[0]->refresh();
        $u2 = $units[2]->refresh();
        $this->assertSame('2026-04-10', $u0->warranty_ends_at->setTimezone('Asia/Kolkata')->toDateString());
        $this->assertSame('2026-05-05', $u2->warranty_ends_at->setTimezone('Asia/Kolkata')->toDateString());
        $this->assertNotEquals($u0->warranty_registered_at, null);
    }

    public function test_late_registration_keeps_original_coverage_period(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(50000, 5);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 1]]), $admin);
        $unit = $order->units()->first();
        app(FulfilmentService::class)->handover($order, [$unit->id], CarbonImmutable::parse('2026-01-01 11:00', 'Asia/Kolkata'), $admin);

        $this->travelTo(CarbonImmutable::parse('2026-02-20', 'Asia/Kolkata'));
        app(WarrantyService::class)->register($order, 'A', '9876543210');
        $unit->refresh();
        $this->assertSame('2026-01-01', $unit->warranty_starts_at->setTimezone('Asia/Kolkata')->toDateString());
        $this->assertSame('2026-04-01', $unit->warranty_ends_at->setTimezone('Asia/Kolkata')->toDateString());
        $this->assertSame('2026-02-20', $unit->warranty_registered_at->setTimezone('Asia/Kolkata')->toDateString());
    }

    public function test_refunded_unit_loses_warranty(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(50000, 5);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 2]]), $admin);
        app(PaymentService::class)->recordPayment($order, $order->grand_total_paise, PaymentMethod::Cash, null, now(), $admin);
        app(FulfilmentService::class)->handover($order, $order->units()->pluck('id')->all(), now(), $admin);
        app(WarrantyService::class)->register($order, 'A', '9876543210');

        app(PaymentService::class)->recordRefund($order->refresh(), 50000, [['order_item_id' => $order->items->first()->id, 'quantity' => 1, 'amount_paise' => 50000]], PaymentMethod::Cash, null, 'Return', $admin);
        $this->assertSame(1, $order->units()->where('warranty_status', 'registered')->count());
        $this->assertSame(1, $order->units()->where('warranty_status', 'void')->count());
    }
}
