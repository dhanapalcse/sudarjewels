<?php

namespace App\Domain\Orders;

use App\Domain\Inventory\InventoryService;
use App\Domain\Notifications\Notifier;
use App\Domain\Warranty\WarrantyService;
use App\Enums\OrderStatus;
use App\Enums\UnitStatus;
use App\Enums\WarrantyStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\PurchasedUnit;
use App\Models\Shipment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Dispatch, delivery, in-person handover and returns — always per purchased unit,
 * so partial shipments and partial returns are exact.
 */
class FulfilmentService
{
    public function __construct(
        private InventoryService $inventory,
        private OrderService $orders,
        private WarrantyService $warranty,
        private Notifier $notifier,
    ) {}

    /**
     * @param  array<int>  $unitIds
     * @param  array{courier?:?string, tracking_number?:?string, tracking_url?:?string, notes?:?string, dispatched_at?:?CarbonInterface}  $details
     */
    public function dispatch(Order $order, array $unitIds, array $details, User $actor): Shipment
    {
        if ($order->status !== OrderStatus::Confirmed) {
            throw new BusinessRuleException('Only confirmed orders can be dispatched.');
        }

        $shipment = DB::transaction(function () use ($order, $unitIds, $details, $actor) {
            $units = PurchasedUnit::where('order_id', $order->id)->whereIn('id', $unitIds)->lockForUpdate()->get();
            $units = $units->filter(fn (PurchasedUnit $u) => $u->status === UnitStatus::Allocated);
            if ($units->isEmpty()) {
                throw new BusinessRuleException('Select at least one unit that is awaiting dispatch.');
            }
            $shipment = Shipment::create([
                'order_id' => $order->id,
                'courier' => $details['courier'] ?? null,
                'tracking_number' => $details['tracking_number'] ?? null,
                'tracking_url' => $details['tracking_url'] ?? null,
                'notes' => $details['notes'] ?? null,
                'dispatched_at' => $details['dispatched_at'] ?? now(),
                'is_handover' => (bool) ($details['is_handover'] ?? false),
                'created_by_id' => $actor->id,
            ]);
            foreach ($units->groupBy('order_item_id') as $itemId => $group) {
                $this->inventory->consumeReservation((int) $itemId, $group->count(), "dispatch:shipment:{$shipment->id}:item:{$itemId}", $shipment, $actor);
            }
            PurchasedUnit::whereIn('id', $units->pluck('id'))->where('status', UnitStatus::Allocated->value)->update([
                'status' => UnitStatus::Dispatched->value,
                'shipment_id' => $shipment->id,
                'dispatched_at' => $shipment->dispatched_at,
            ]);
            $this->orders->refreshFulfilmentStatus($order->refresh(), $actor);

            return $shipment;
        });

        if (! $shipment->is_handover) {
            DB::afterCommit(fn () => $this->notifier->orderDispatched($order, $shipment));
        }

        return $shipment;
    }

    public function markDelivered(Shipment $shipment, CarbonInterface $deliveredAt, User $actor): void
    {
        DB::transaction(function () use ($shipment, $deliveredAt, $actor) {
            $shipment = Shipment::whereKey($shipment->id)->lockForUpdate()->firstOrFail();
            $units = $shipment->units()->where('status', UnitStatus::Dispatched->value)->lockForUpdate()->get();
            if ($shipment->delivered_at === null) {
                $shipment->update(['delivered_at' => $deliveredAt]);
            }
            foreach ($units as $unit) {
                $unit->update(['status' => UnitStatus::Delivered, 'delivered_at' => $deliveredAt]);
                $this->warranty->applyCoverage($unit);
            }
            $order = $shipment->order()->first();
            $this->orders->refreshFulfilmentStatus($order, $actor);
            $this->warranty->attachNewlyEligibleUnits($order);
        });
    }

    /** In-person sale: dispatch and deliver in one step; warranty starts at handover. */
    public function handover(Order $order, array $unitIds, CarbonInterface $at, User $actor): Shipment
    {
        $shipment = $this->dispatch($order, $unitIds, ['dispatched_at' => $at, 'is_handover' => true, 'notes' => 'Handed over in person'], $actor);
        $this->markDelivered($shipment, $at, $actor);

        return $shipment;
    }

    /** @param array<int> $unitIds */
    public function markReturned(Order $order, array $unitIds, User $actor, ?string $note = null): void
    {
        DB::transaction(function () use ($order, $unitIds, $actor, $note) {
            $units = PurchasedUnit::where('order_id', $order->id)->whereIn('id', $unitIds)
                ->whereIn('status', [UnitStatus::Dispatched->value, UnitStatus::Delivered->value, UnitStatus::Refunded->value])
                ->whereNull('returned_at')->lockForUpdate()->get();
            if ($units->isEmpty()) {
                throw new BusinessRuleException('Select dispatched or delivered units to mark as returned.');
            }
            foreach ($units as $unit) {
                $unit->update([
                    'status' => $unit->status === UnitStatus::Refunded ? UnitStatus::Refunded : UnitStatus::Returned,
                    'returned_at' => now(),
                    'warranty_status' => $unit->warranty_status === WarrantyStatus::NotEligible ? WarrantyStatus::NotEligible : WarrantyStatus::Void,
                ]);
            }
            $this->orders->history($order, 'fulfilment', null, 'returned', trim('Units returned: '.$units->pluck('code')->implode(', ').' '.$note), $actor);
            $this->orders->refreshFulfilmentStatus($order->refresh(), $actor);
        });
    }

    /** Restock happens only on explicit staff approval of a returned unit. */
    public function approveRestock(PurchasedUnit $unit, User $actor): void
    {
        if (! $unit->returned_at) {
            throw new BusinessRuleException('Only returned units can be restocked.');
        }
        DB::transaction(function () use ($unit, $actor) {
            $unit = PurchasedUnit::whereKey($unit->id)->lockForUpdate()->firstOrFail();
            if ($unit->restocked_at) {
                return;
            }
            $this->inventory->restockUnit($unit, $actor);
            $unit->update(['restocked_at' => now(), 'restock_approved_by_id' => $actor->id]);
            $this->orders->history($unit->order, 'inventory', null, 'restocked', "Unit {$unit->code} restocked", $actor);
        });
    }
}
