<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A shopper identified by a normalised phone number. Logs in with a phone OTP (guard: customer).
 */
class Customer extends Authenticatable
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'marketing_opt_in', 'marketing_consent_at', 'marketing_consent_source', 'notes'];

    protected $hidden = ['remember_token'];

    protected function casts(): array
    {
        return [
            'marketing_opt_in' => 'boolean',
            'marketing_consent_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function warrantyRegistrations(): HasMany
    {
        return $this->hasMany(WarrantyRegistration::class);
    }

    public function claims(): HasMany
    {
        return $this->hasMany(WarrantyClaim::class);
    }

    public function getAuthPassword(): string
    {
        return ''; // OTP-only authentication
    }
}
