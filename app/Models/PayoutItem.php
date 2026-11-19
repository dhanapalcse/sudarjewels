<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutItem extends Model
{
    protected $guarded = ['id'];

    public function payout(): BelongsTo
    {
        return $this->belongsTo(AffiliatePayout::class, 'affiliate_payout_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(CommissionEntry::class, 'commission_entry_id');
    }
}
