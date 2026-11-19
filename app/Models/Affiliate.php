<?php

namespace App\Models;

use App\Enums\AffiliateStatus;
use App\Enums\PayoutMethod;
use App\Support\Mask;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Affiliate extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $hidden = ['payout_account_name', 'payout_account_number', 'payout_ifsc', 'payout_upi_id', 'admin_notes'];

    protected function casts(): array
    {
        return [
            'status' => AffiliateStatus::class,
            'payout_method' => PayoutMethod::class,
            'payout_account_name' => 'encrypted',
            'payout_account_number' => 'encrypted',
            'payout_ifsc' => 'encrypted',
            'payout_upi_id' => 'encrypted',
            'approved_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'payout_details_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function commissionEntries(): HasMany
    {
        return $this->hasMany(CommissionEntry::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(ReferralVisit::class);
    }

    public function isActive(): bool
    {
        return $this->status === AffiliateStatus::Active && $this->user?->is_active;
    }

    public function name(): string
    {
        return $this->user?->name ?? $this->code;
    }

    /** Masked payout destination safe to show in lists and payout records. */
    public function maskedPayoutDestination(): ?string
    {
        return match ($this->payout_method) {
            PayoutMethod::Upi => $this->payout_upi_id ? 'UPI '.Mask::upi($this->payout_upi_id) : null,
            PayoutMethod::BankTransfer => $this->payout_account_number
                ? 'A/c '.Mask::tail($this->payout_account_number).' · '.$this->payout_ifsc
                : null,
            default => null,
        };
    }
}
