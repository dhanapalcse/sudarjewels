<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Support\CatalogueCache;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Settings $settings): View
    {
        $sections = CatalogueCache::remember('home', 600, fn () => [
            'categories' => Category::active()->topLevel()->orderBy('sort_order')->limit(8)->get(),
            'newArrivals' => Product::published()->with(['mainImage', 'activeVariants'])
                ->orderByDesc('is_new_arrival')->orderByDesc('published_at')->limit(8)->get(),
            'bestSellers' => Product::published()->where('is_best_seller', true)->with(['mainImage', 'activeVariants'])
                ->orderByDesc('published_at')->limit(8)->get(),
            'categoryImages' => Product::published()->with('mainImage')->get()->groupBy('category_id')
                ->map(fn ($products) => $products->first(fn ($p) => $p->mainImage)?->mainImage),
        ]);

        $hero = $settings->get('home.hero_image');

        return view('storefront.home', $sections + [
            'hero' => is_array($hero) ? $hero : null,
            'seo' => Seo::make(
                $settings->get('business.name').' — '.$settings->get('business.tagline'),
                $settings->get('home.hero_subheading'),
                ['canonical' => route('home'), 'jsonLd' => [[
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => $settings->get('business.name'),
                    'url' => route('home'),
                    'sameAs' => array_values($settings->socialLinks()),
                ]]],
            ),
        ]);
    }
}
