<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PageController extends Controller
{
    public function show(Request $request, string $slug): View
    {
        $page = Page::findBySlug($slug) ?? abort(404);

        return view('storefront.page', [
            'page' => $page,
            'seo' => Seo::make($page->seo_title ?: $page->title, $page->seo_description, ['canonical' => url()->current()]),
        ]);
    }

    public function contact(): View
    {
        return view('storefront.contact', [
            'page' => Page::findBySlug('contact'),
            'seo' => Seo::make('Contact us', 'Reach SUDAR JEWELS on WhatsApp, phone or email.'),
        ]);
    }
}
