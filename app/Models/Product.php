<?php

namespace App\Models;

use App\Enums\ProductStatus;
use App\Support\Settings;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'is_best_seller' => 'boolean',
            'is_new_arrival' => 'boolean',
            'commission_enabled' => 'boolean',
            'commission_rate_bp' => 'integer',
            'warranty_eligible' => 'boolean',
            'warranty_months' => 'integer',
            'is_sample' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'subcategory_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('id');
    }

    public function activeVariants(): HasMany
    {
        return $this->variants()->where('is_active', true);
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id');
    }

    public function mainImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)
            ->whereNotNull('renditions')
            ->orderByDesc('is_main')->orderBy('sort_order')->orderBy('id');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('status', ProductStatus::Published);
    }

    public function isPublished(): bool
    {
        return $this->status === ProductStatus::Published;
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function hasDiscount(): bool
    {
        return $this->regular_price_paise !== null && $this->regular_price_paise > $this->price_paise;
    }

    public function totalAvailable(): int
    {
        return (int) $this->activeVariants->sum(fn (ProductVariant $v) => $v->available());
    }

    public function minPrice(): int
    {
        $prices = $this->activeVariants->map(fn (ProductVariant $v) => $v->effectivePrice());

        return (int) ($prices->min() ?? $this->price_paise);
    }

    public function warrantyMonths(): int
    {
        return $this->warranty_months ?: (int) app(Settings::class)->get('warranty.months');
    }
}
