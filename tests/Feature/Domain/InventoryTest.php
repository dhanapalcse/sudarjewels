<?php

namespace Tests\Feature\Domain;

use App\Domain\Inventory\InsufficientStock;
use App\Domain\Inventory\InventoryService;
use App\Domain\Orders\FulfilmentService;
use App\Domain\Orders\OrderService;
use App\Enums\OrderStatus;
use App\Enums\ReservationStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\StockMovement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_reserves_stock_and_retry_is_idempotent(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(10000, 5);
        $order = $this->order($admin, [[$variant, 2]]);

        $service = app(OrderService::class);
        $service->confirm($order, $admin);
        $service->confirm($order->refresh(), $admin); // retry

        $variant->refresh();
        $this->assertSame(5, $variant->stock_on_hand);
        $this->assertSame(2, $variant->stock_reserved);
        $this->assertSame(2, $order->units()->count());
        $this->assertSame(1, $order->items()->first()->reservation()->count());
    }

    public function test_last_unit_cannot_be_reserved_twice_even_with_stale_reads(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(10000, 1);
        $first = $this->order($admin, [[$variant, 1]]);
        $second = $this->order($admin, [[$variant, 1]]);

        // Both requests read "1 available" before either writes.
        $this->assertSame(1, $first->items->first()->variant->available());
        $this->assertSame(1, $second->items->first()->variant->available());

        app(OrderService::class)->confirm($first, $admin);
        try {
            app(OrderService::class)->confirm($second, $admin);
            $this->fail('Second confirmation should have failed.');
        } catch (InsufficientStock) {
        }

        $this->assertSame(OrderStatus::Submitted->value === $second->refresh()->status->value ? OrderStatus::Submitted : OrderStatus::Draft, $second->status);
        $this->assertSame(1, $variant->refresh()->stock_reserved);
        $this->assertSame(0, $second->units()->count(), 'Failed confirmation must roll back fully');
    }

    public function test_dispatch_converts_reservation_once_and_cancellation_releases(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(10000, 5);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 3]]), $admin);
        $units = $order->units()->orderBy('id')->get();

        app(FulfilmentService::class)->dispatch($order, [$units[0]->id], ['courier' => 'X'], $admin);
        app(FulfilmentService::class)->dispatch($order, [$units[0]->id, $units[1]->id], ['courier' => 'X'], $admin); // unit 0 already dispatched

        $variant->refresh();
        $this->assertSame(3, $variant->stock_on_hand);
        $this->assertSame(1, $variant->stock_reserved);
        $this->assertSame(2, StockMovement::where('type', 'sale')->sum(\DB::raw('-quantity')));
    }

    public function test_cancel_before_dispatch_releases_reservation(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(10000, 5);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 2]]), $admin);
        app(OrderService::class)->cancel($order, $admin, 'Customer changed mind');

        $variant->refresh();
        $this->assertSame(0, $variant->stock_reserved);
        $this->assertSame(5, $variant->stock_on_hand);
        $this->assertSame(ReservationStatus::Released, $order->items()->first()->reservation->status);
    }

    public function test_returns_restock_only_on_explicit_approval_and_only_once(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(10000, 2);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 1]]), $admin);
        $unit = $order->units()->first();
        $fulfilment = app(FulfilmentService::class);
        $shipment = $fulfilment->dispatch($order, [$unit->id], [], $admin);
        $fulfilment->markDelivered($shipment, now(), $admin);
        $fulfilment->markReturned($order, [$unit->id], $admin);

        $this->assertSame(1, $variant->refresh()->stock_on_hand, 'Return alone must not restock');

        $fulfilment->approveRestock($unit->refresh(), $admin);
        $fulfilment->approveRestock($unit->refresh(), $admin);
        $this->assertSame(2, $variant->refresh()->stock_on_hand);
    }

    public function test_manual_adjustment_cannot_remove_reserved_stock(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(10000, 2);
        app(OrderService::class)->confirm($this->order($admin, [[$variant, 2]]), $admin);

        $this->expectException(BusinessRuleException::class);
        app(InventoryService::class)->adjust($variant, -1, 'stocktake-1', $admin);
    }
}
