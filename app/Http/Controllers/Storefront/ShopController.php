<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalogue\ProductSearch;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShopController extends Controller
{
    public function __construct(private ProductSearch $search) {}

    public function index(Request $request): View
    {
        $filters = $this->search->filters($request);

        return $this->render($request, $filters, null, Seo::make(
            $filters['q'] !== '' ? 'Search: '.$filters['q'] : 'Shop all jewellery',
            'Browse anti-tarnish earrings, necklaces, rings and bracelets.',
            ['canonical' => $this->canonical(route('shop'), $request), 'noindex' => $filters['q'] !== ''],
        ));
    }

    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);
        $filters = $this->search->filters($request);

        return $this->render($request, $filters, $category, Seo::make(
            $category->seo_title ?: $category->name,
            $category->seo_description ?: ($category->description ?: "Shop {$category->name} from SUDAR JEWELS."),
            ['canonical' => $this->canonical(route('shop.category', $category), $request)],
        ));
    }

    private function render(Request $request, array $filters, ?Category $category, Seo $seo): View
    {
        $products = $this->search->search($filters, $category);
        $topCategories = Category::active()->topLevel()->with(['children' => fn ($q) => $q->where('is_active', true)])->orderBy('sort_order')->get();

        return view('storefront.shop', [
            'products' => $products,
            'filters' => $filters,
            'category' => $category,
            'subcategories' => $category ? $category->children()->where('is_active', true)->get() : collect(),
            'topCategories' => $topCategories,
            'facets' => $this->search->facets(),
            'sorts' => ProductSearch::SORTS,
            'activeCount' => $this->search->activeCount($filters),
            'seo' => $seo,
        ]);
    }

    /** Canonical ignores filters/sort but keeps pagination. */
    private function canonical(string $base, Request $request): string
    {
        $page = (int) $request->query('page', 1);

        return $page > 1 ? $base.'?page='.$page : $base;
    }
}
