<?php

namespace App\Domain\Inventory;

use App\Enums\ReservationStatus;
use App\Enums\StockMovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\PurchasedUnit;
use App\Models\StockMovement;
use App\Models\StockReservation;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Stock = stock_on_hand (physically held) and stock_reserved (promised to confirmed orders).
 * Every change uses a conditional atomic UPDATE so concurrent requests cannot oversell,
 * and every movement carries a unique reference so retries never double-apply.
 */
class InventoryService
{
    /** Manual adjustment (receiving stock, stock-take corrections). */
    public function adjust(ProductVariant $variant, int $delta, string $reference, ?User $actor, ?string $note = null, StockMovementType $type = StockMovementType::Adjustment): ?StockMovement
    {
        if ($delta === 0) {
            return null;
        }

        return DB::transaction(function () use ($variant, $delta, $reference, $actor, $note, $type) {
            if (StockMovement::where('reference', $reference)->exists()) {
                return null;
            }
            $query = ProductVariant::whereKey($variant->id);
            if ($delta < 0) {
                // Cannot remove stock that is reserved for confirmed orders.
                $query->whereRaw('stock_on_hand - stock_reserved >= ?', [-$delta]);
            }
            if ($query->update(['stock_on_hand' => DB::raw('stock_on_hand + '.(int) $delta), 'updated_at' => now()]) !== 1) {
                throw new BusinessRuleException('Cannot remove more stock than is available (reserved stock is protected).');
            }

            return $this->movement($variant->id, $type, $delta, $reference, null, $actor, $note);
        });
    }

    /** Reserve stock for every item of a confirmed order (inside the caller's transaction). */
    public function reserveForOrder(Order $order): void
    {
        $items = $order->items()->with('variant')->orderBy('product_variant_id')->get();
        foreach ($items as $item) {
            if (StockReservation::where('order_item_id', $item->id)->exists()) {
                continue;
            }
            if (! $item->product_variant_id || ! $item->variant || $item->variant->trashed()) {
                throw new BusinessRuleException("{$item->label()} is no longer in the catalogue.");
            }
            $affected = ProductVariant::whereKey($item->product_variant_id)
                ->whereRaw('stock_on_hand - stock_reserved >= ?', [$item->quantity])
                ->update(['stock_reserved' => DB::raw('stock_reserved + '.(int) $item->quantity), 'updated_at' => now()]);
            if ($affected !== 1) {
                $fresh = ProductVariant::find($item->product_variant_id);
                throw new InsufficientStock($item->label(), $item->quantity, $fresh?->available() ?? 0);
            }
            StockReservation::create([
                'order_item_id' => $item->id,
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'status' => ReservationStatus::Active,
            ]);
        }
    }

    /** Dispatch converts reserved stock into a sale movement. */
    public function consumeReservation(int $orderItemId, int $quantity, string $reference, ?Model $source, ?User $actor): void
    {
        if (StockMovement::where('reference', $reference)->exists()) {
            return;
        }
        $reservation = StockReservation::where('order_item_id', $orderItemId)->lockForUpdate()->firstOrFail();
        if ($reservation->outstanding() < $quantity) {
            throw new BusinessRuleException('Reservation does not cover the dispatched quantity.');
        }
        $affected = ProductVariant::whereKey($reservation->product_variant_id)
            ->where('stock_reserved', '>=', $quantity)->where('stock_on_hand', '>=', $quantity)
            ->update([
                'stock_on_hand' => DB::raw('stock_on_hand - '.$quantity),
                'stock_reserved' => DB::raw('stock_reserved - '.$quantity),
                'updated_at' => now(),
            ]);
        if ($affected !== 1) {
            throw new BusinessRuleException('Stock levels are inconsistent with the reservation. Check inventory.');
        }
        $reservation->fulfilled_quantity += $quantity;
        $reservation->status = $reservation->outstanding() === 0 ? ReservationStatus::Fulfilled : ReservationStatus::Active;
        $reservation->save();
        $this->movement($reservation->product_variant_id, StockMovementType::Sale, -$quantity, $reference, $source, $actor);
    }

    /** Release all outstanding reservations of an order (cancellation). */
    public function releaseForOrder(Order $order): void
    {
        $reservations = StockReservation::whereIn('order_item_id', $order->items()->pluck('id'))
            ->where('status', ReservationStatus::Active->value)->lockForUpdate()->get();
        foreach ($reservations as $reservation) {
            $this->releaseQuantity($reservation, $reservation->outstanding());
        }
    }

    /** Release part of one item's reservation (e.g. refunded before dispatch). */
    public function releaseUnits(int $orderItemId, int $quantity, string $reference): void
    {
        $reservation = StockReservation::where('order_item_id', $orderItemId)->lockForUpdate()->first();
        if ($reservation) {
            $this->releaseQuantity($reservation, min($quantity, $reservation->outstanding()));
        }
    }

    public function restockUnit(PurchasedUnit $unit, User $actor): void
    {
        $variantId = $unit->orderItem->product_variant_id;
        if (! $variantId) {
            throw new BusinessRuleException('The original variant no longer exists; adjust stock manually.');
        }
        $reference = "restock:unit:{$unit->id}";
        if (StockMovement::where('reference', $reference)->exists()) {
            return;
        }
        ProductVariant::whereKey($variantId)->update(['stock_on_hand' => DB::raw('stock_on_hand + 1'), 'updated_at' => now()]);
        $this->movement($variantId, StockMovementType::ReturnRestock, 1, $reference, $unit, $actor, "Return of {$unit->code} approved for restock");
    }

    private function releaseQuantity(StockReservation $reservation, int $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }
        ProductVariant::whereKey($reservation->product_variant_id)->where('stock_reserved', '>=', $quantity)
            ->update(['stock_reserved' => DB::raw('stock_reserved - '.$quantity), 'updated_at' => now()]);
        $reservation->released_quantity += $quantity;
        $reservation->status = $reservation->outstanding() === 0
            ? ($reservation->fulfilled_quantity > 0 ? ReservationStatus::Fulfilled : ReservationStatus::Released)
            : ReservationStatus::Active;
        $reservation->save();
    }

    private function movement(int $variantId, StockMovementType $type, int $quantity, string $reference, ?Model $source, ?User $actor, ?string $note = null): StockMovement
    {
        $balance = (int) ProductVariant::whereKey($variantId)->value('stock_on_hand');

        return StockMovement::create([
            'product_variant_id' => $variantId,
            'type' => $type,
            'quantity' => $quantity,
            'balance_after' => $balance,
            'source_type' => $source?->getMorphClass(),
            'source_id' => $source?->getKey(),
            'reference' => $reference,
            'user_id' => $actor?->id,
            'note' => $note,
        ]);
    }
}
