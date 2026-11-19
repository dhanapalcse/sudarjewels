<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Affiliates\ReferralService;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductImage;
use App\Support\CatalogueCache;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, Settings $settings, ReferralService $referrals): View
    {
        $isPreview = ! $product->isPublished();
        if ($isPreview && ! $request->user()?->can('catalogue.manage')) {
            abort(404);
        }

        $product->load(['category', 'subcategory', 'activeVariants']);
        $images = $product->images()->whereNotNull('renditions')->get();
        $related = CatalogueCache::remember("related:{$product->id}", 600, fn () => Product::published()
            ->where('category_id', $product->category_id)->whereKeyNot($product->id)
            ->with(['mainImage', 'activeVariants'])->inRandomOrder()->limit(4)->get());

        $variants = $product->activeVariants->map(fn ($v) => [
            'id' => $v->id,
            'sku' => $v->sku,
            'label' => $v->displayName(),
            'price' => $v->effectivePrice(),
            'regular' => $v->effectiveRegularPrice(),
            'available' => $v->available(),
        ])->values();
        $available = $variants->sum('available');
        $mainImage = $images->first();

        $jsonLd = [[
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $product->name,
            'sku' => $product->sku,
            'description' => strip_tags((string) ($product->short_description ?: $product->description)),
            'image' => $images->map(fn (ProductImage $i) => $i->fallbackUrl())->filter()->values()->all(),
            'brand' => ['@type' => 'Brand', 'name' => $settings->get('business.name')],
            'offers' => [
                '@type' => 'Offer',
                'priceCurrency' => 'INR',
                'price' => number_format($product->minPrice() / 100, 2, '.', ''),
                'availability' => $available > 0 ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'url' => route('products.show', $product),
            ],
        ], [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $product->category->name, 'item' => route('shop.category', $product->category)],
                ['@type' => 'ListItem', 'position' => 3, 'name' => $product->name],
            ],
        ]];

        return view('storefront.product', [
            'product' => $product,
            'images' => $images,
            'variants' => $variants,
            'available' => $available,
            'related' => $related,
            'isPreview' => $isPreview,
            'referral' => $referrals->current($request),
            'whatsappConfigured' => (bool) $settings->whatsappNumber(),
            'seo' => Seo::make($product->seo_title ?: $product->name, $product->seo_description ?: ($product->short_description ?: $product->name), [
                'canonical' => route('products.show', $product),
                'image' => $mainImage?->fallbackUrl(),
                'type' => 'product',
                'noindex' => $isPreview,
                'jsonLd' => $isPreview ? [] : $jsonLd,
            ]),
        ]);
    }
}
