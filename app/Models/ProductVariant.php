<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id', 'stock_on_hand', 'stock_reserved'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'stock_on_hand' => 'integer',
            'stock_reserved' => 'integer',
            'price_paise' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(StockMovement::class)->latest('id');
    }

    public function available(): int
    {
        return max(0, $this->stock_on_hand - $this->stock_reserved);
    }

    public function effectivePrice(): int
    {
        return (int) ($this->price_paise ?? $this->product->price_paise);
    }

    public function effectiveRegularPrice(): ?int
    {
        return $this->regular_price_paise ?? $this->product->regular_price_paise;
    }

    /** @return array<string, string> */
    public function attributesForDisplay(): array
    {
        return array_filter([
            'Finish' => $this->finish,
            'Size' => $this->size,
            'Colour' => $this->colour,
        ]);
    }

    public function displayName(): string
    {
        return $this->name ?: (implode(' / ', $this->attributesForDisplay()) ?: 'Standard');
    }
}
