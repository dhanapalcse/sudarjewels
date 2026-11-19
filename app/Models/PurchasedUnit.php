<?php

namespace App\Models;

use App\Enums\UnitStatus;
use App\Enums\WarrantyStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One physical piece of jewellery sold. Order items with quantity N have N units,
 * so delivery, returns, warranty coverage and claims are tracked per piece.
 */
class PurchasedUnit extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => UnitStatus::class,
            'warranty_status' => WarrantyStatus::class,
            'warranty_eligible' => 'boolean',
            'dispatched_at' => 'datetime',
            'delivered_at' => 'datetime',
            'returned_at' => 'datetime',
            'restocked_at' => 'datetime',
            'refunded_at' => 'datetime',
            'warranty_starts_at' => 'datetime',
            'warranty_ends_at' => 'datetime',
            'warranty_registered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(WarrantyRegistration::class, 'warranty_registration_id');
    }

    public function replacementOf(): BelongsTo
    {
        return $this->belongsTo(PurchasedUnit::class, 'replacement_of_unit_id');
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }

    public function isCovered(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? Carbon::instance($at) : now();

        return $this->warranty_status === WarrantyStatus::Registered
            && $this->warranty_starts_at && $this->warranty_ends_at
            && $at->betweenIncluded($this->warranty_starts_at, $this->warranty_ends_at);
    }
}
