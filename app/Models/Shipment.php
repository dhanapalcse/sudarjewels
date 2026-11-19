<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['dispatched_at' => 'datetime', 'delivered_at' => 'datetime', 'is_handover' => 'boolean'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(PurchasedUnit::class);
    }
}
