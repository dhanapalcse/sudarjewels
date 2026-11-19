<?php

namespace App\Models;

use App\Enums\ReservationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => ReservationStatus::class];
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function outstanding(): int
    {
        return $this->quantity - $this->fulfilled_quantity - $this->released_quantity;
    }
}
