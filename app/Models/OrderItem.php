<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class OrderItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'variant_attributes' => 'array',
            'commission_enabled' => 'boolean',
            'warranty_eligible' => 'boolean',
            'unit_price_paise' => 'integer',
            'quantity' => 'integer',
            'line_subtotal_paise' => 'integer',
            'allocated_discount_paise' => 'integer',
            'tax_paise' => 'integer',
            'line_total_paise' => 'integer',
            'commission_rate_bp' => 'integer',
            'commission_eligible_paise' => 'integer',
            'commission_paise' => 'integer',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id')->withTrashed();
    }

    public function units(): HasMany
    {
        return $this->hasMany(PurchasedUnit::class)->orderBy('unit_number');
    }

    public function reservation(): HasOne
    {
        return $this->hasOne(StockReservation::class);
    }

    public function refundItems(): HasMany
    {
        return $this->hasMany(RefundItem::class);
    }

    public function label(): string
    {
        return $this->product_name.($this->variant_name ? ' — '.$this->variant_name : '');
    }
}
