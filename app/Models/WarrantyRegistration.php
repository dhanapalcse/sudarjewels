<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class WarrantyRegistration extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['verified_at' => 'datetime', 'registered_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (WarrantyRegistration $r) => $r->uuid ??= (string) Str::uuid());
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(PurchasedUnit::class);
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
