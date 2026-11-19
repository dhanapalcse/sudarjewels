<?php

namespace App\Models;

use App\Enums\PayoutMethod;
use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AffiliatePayout extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'method' => PayoutMethod::class,
            'paid_on' => 'date',
            'completed_at' => 'datetime',
            'destination_snapshot' => 'encrypted',
            'amount_paise' => 'integer',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }

    public function heldEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class, 'payout_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
