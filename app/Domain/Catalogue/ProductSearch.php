<?php

namespace App\Domain\Catalogue;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CatalogueCache;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Catalogue filtering for Shop All and category pages. All state lives in query
 * parameters so filters and sorting survive pagination and are shareable.
 */
class ProductSearch
{
    public const SORTS = [
        'featured' => 'Featured',
        'newest' => 'Newest',
        'price_asc' => 'Price: low to high',
        'price_desc' => 'Price: high to low',
        'best' => 'Best sellers',
    ];

    public const PER_PAGE = 24;

    /** @return array<string, mixed> */
    public function filters(Request $request): array
    {
        $list = fn (string $key) => array_values(array_filter(array_map('strval', (array) $request->query($key, [])), fn ($v) => $v !== '' && mb_strlen($v) <= 60));

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 80),
            'category' => (string) $request->query('category', ''),
            'sub' => (string) $request->query('sub', ''),
            'min' => is_numeric($request->query('min')) ? max(0, (int) $request->query('min')) : null,
            'max' => is_numeric($request->query('max')) ? max(0, (int) $request->query('max')) : null,
            'in_stock' => $request->boolean('in_stock'),
            'finish' => $list('finish'),
            'colour' => $list('colour'),
            'size' => $list('size'),
            'sort' => array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : 'featured',
        ];
    }

    public function search(array $filters, ?Category $category = null): LengthAwarePaginator
    {
        $query = Product::query()->published()
            ->with(['mainImage', 'activeVariants', 'category']);

        if ($category) {
            $query->where(fn (Builder $q) => $q->where('category_id', $category->id)->orWhere('subcategory_id', $category->id));
        } elseif ($filters['category'] !== '') {
            $cat = Category::active()->where('slug', $filters['category'])->first();
            $query->where(fn (Builder $q) => $q->where('category_id', $cat?->id ?? 0)->orWhere('subcategory_id', $cat?->id ?? 0));
        }
        if ($filters['sub'] !== '') {
            $sub = Category::active()->where('slug', $filters['sub'])->first();
            $query->where('subcategory_id', $sub?->id ?? 0);
        }
        if ($filters['q'] !== '') {
            $term = '%'.str_replace(['%', '_'], ['\%', '\_'], $filters['q']).'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $term)->orWhere('sku', 'like', $term)
                ->orWhere('short_description', 'like', $term)
                ->orWhereHas('variants', fn (Builder $v) => $v->where('sku', 'like', $term)));
        }
        if ($filters['min'] !== null) {
            $query->where('price_paise', '>=', $filters['min'] * 100);
        }
        if ($filters['max'] !== null && $filters['max'] > 0) {
            $query->where('price_paise', '<=', $filters['max'] * 100);
        }
        foreach (['finish', 'colour', 'size'] as $attribute) {
            if ($filters[$attribute] !== []) {
                $query->whereHas('activeVariants', fn (Builder $v) => $v->whereIn($attribute, $filters[$attribute]));
            }
        }
        if ($filters['in_stock']) {
            $query->whereHas('activeVariants', fn (Builder $v) => $v->whereColumn('stock_on_hand', '>', 'stock_reserved'));
        }

        match ($filters['sort']) {
            'newest' => $query->orderByDesc('published_at')->orderByDesc('id'),
            'price_asc' => $query->orderBy('price_paise')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price_paise')->orderBy('id'),
            'best' => $query->orderByDesc('is_best_seller')->orderByDesc('published_at'),
            default => $query->orderByDesc('is_featured')->orderByDesc('published_at')->orderByDesc('id'),
        };

        return $query->paginate(self::PER_PAGE)->withQueryString();
    }

    /** @return array{finish: array<string>, colour: array<string>, size: array<string>} */
    public function facets(): array
    {
        return CatalogueCache::remember('facets', 3600, function () {
            $base = ProductVariant::query()->where('is_active', true)
                ->whereHas('product', fn (Builder $p) => $p->published());

            return [
                'finish' => (clone $base)->whereNotNull('finish')->distinct()->orderBy('finish')->pluck('finish')->all(),
                'colour' => (clone $base)->whereNotNull('colour')->distinct()->orderBy('colour')->pluck('colour')->all(),
                'size' => (clone $base)->whereNotNull('size')->distinct()->orderBy('size')->pluck('size')->all(),
            ];
        });
    }

    public function activeCount(array $filters): int
    {
        return ($filters['q'] !== '' ? 1 : 0) + ($filters['sub'] !== '' ? 1 : 0) + ($filters['min'] !== null ? 1 : 0)
            + ($filters['max'] !== null ? 1 : 0) + ($filters['in_stock'] ? 1 : 0)
            + count($filters['finish']) + count($filters['colour']) + count($filters['size']);
    }
}
