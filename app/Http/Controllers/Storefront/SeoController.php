<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Page;
use App\Models\Product;
use App\Support\CatalogueCache;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function sitemap(): Response
    {
        $xml = CatalogueCache::remember('sitemap', 3600, function () {
            $urls = [
                ['loc' => route('home')],
                ['loc' => route('shop')],
                ['loc' => route('pages.contact')],
                ['loc' => route('warranty.start')],
            ];
            foreach (Category::active()->get() as $c) {
                $urls[] = ['loc' => route('shop.category', $c), 'lastmod' => $c->updated_at?->toAtomString()];
            }
            foreach (Product::published()->select(['id', 'slug', 'updated_at'])->get() as $p) {
                $urls[] = ['loc' => route('products.show', $p), 'lastmod' => $p->updated_at?->toAtomString()];
            }
            $map = ['about' => 'pages.about', 'delivery' => 'pages.delivery', 'returns' => 'pages.returns', 'privacy' => 'pages.privacy', 'terms' => 'pages.terms', 'affiliate-terms' => 'pages.affiliate-terms', 'warranty-coverage' => 'pages.warranty-coverage'];
            foreach (Page::where('is_published', true)->get() as $page) {
                if (isset($map[$page->slug])) {
                    $urls[] = ['loc' => route($map[$page->slug]), 'lastmod' => $page->updated_at?->toAtomString()];
                }
            }

            return view('seo.sitemap', ['urls' => $urls])->render();
        });

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        $lines = app()->environment('production')
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /account', 'Disallow: /affiliate', 'Disallow: /warranty/', 'Disallow: /enquiry', 'Disallow: /*?*sort=', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
