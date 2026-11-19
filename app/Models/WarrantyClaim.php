<?php

namespace App\Models;

use App\Enums\ClaimStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarrantyClaim extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => ClaimStatus::class, 'decided_at' => 'datetime', 'resolved_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::created(function (WarrantyClaim $claim) {
            $claim->claim_number ??= 'WC'.str_pad((string) $claim->id, 6, '0', STR_PAD_LEFT);
            $claim->saveQuietly();
        });
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(PurchasedUnit::class, 'purchased_unit_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function registration(): BelongsTo
    {
        return $this->belongsTo(WarrantyRegistration::class, 'warranty_registration_id');
    }

    public function replacementUnit(): BelongsTo
    {
        return $this->belongsTo(PurchasedUnit::class, 'replacement_unit_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(ClaimUpdate::class)->orderBy('id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ClaimAttachment::class);
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by_id');
    }
}
