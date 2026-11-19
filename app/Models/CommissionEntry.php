<?php

namespace App\Models;

use App\Enums\CommissionStatus;
use App\Enums\CommissionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionEntry extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => CommissionType::class,
            'status' => CommissionStatus::class,
            'amount_paise' => 'integer',
            'payable_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'payout_id');
    }

    public function reverses(): BelongsTo
    {
        return $this->belongsTo(CommissionEntry::class, 'reverses_entry_id');
    }
}
