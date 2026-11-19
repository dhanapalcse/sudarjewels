@extends('layouts.app')

@php
    $base = $category ? route('shop.category', $category) : route('shop');
    $title = $category?->name ?? ($filters['q'] !== '' ? 'Results for “'.$filters['q'].'”' : 'Shop all');
@endphp

@section('content')
<div class="container-page pt-6" x-data="{ filters: false, loading: false }" @keydown.escape.window="filters = false">
    <nav aria-label="Breadcrumb" class="text-sm text-muted">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" class="hover:text-teal">Home</a></li>
            <li aria-hidden="true">/</li>
            @if ($category)
                <li><a href="{{ route('shop') }}" class="hover:text-teal">Shop</a></li>
                <li aria-hidden="true">/</li>
                <li aria-current="page" class="text-charcoal">{{ $category->name }}</li>
            @else
                <li aria-current="page" class="text-charcoal">Shop</li>
            @endif
        </ol>
    </nav>

    <div class="mt-3 flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="display text-4xl sm:text-5xl">{{ $title }}</h1>
            <p class="mt-1 text-sm text-muted" aria-live="polite">{{ $products->total() }} {{ \Illuminate\Support\Str::plural('piece', $products->total()) }}</p>
        </div>
    </div>

    {{-- Category chips --}}
    <nav class="no-scrollbar -mx-4 mt-5 overflow-x-auto px-4" aria-label="Categories">
        <ul class="flex gap-2">
            <li><a href="{{ route('shop', request()->except(['page', 'category', 'sub'])) }}" class="chip whitespace-nowrap {{ ! $category ? 'chip-active' : '' }}">All</a></li>
            @foreach ($topCategories as $c)
                <li><a href="{{ route('shop.category', array_merge(['category' => $c], request()->except(['page', 'sub']))) }}" class="chip whitespace-nowrap {{ $category?->id === $c->id ? 'chip-active' : '' }}">{{ $c->name }}</a></li>
            @endforeach
        </ul>
    </nav>
    @if ($subcategories->isNotEmpty())
        <ul class="mt-3 flex flex-wrap gap-2 text-sm" aria-label="Subcategories">
            @foreach ($subcategories as $sub)
                <li><a href="{{ $base.'?'.http_build_query(array_merge(request()->except(['page']), ['sub' => $filters['sub'] === $sub->slug ? null : $sub->slug])) }}" class="chip {{ $filters['sub'] === $sub->slug ? 'chip-active' : '' }}">{{ $sub->name }}</a></li>
            @endforeach
        </ul>
    @endif

    <div class="mt-6 lg:grid lg:grid-cols-[240px_1fr] lg:gap-10">
        {{-- Filters: slide-over on mobile, sidebar on desktop --}}
        <div>
            <div class="flex items-center justify-between gap-2 lg:hidden">
                <button type="button" class="btn-secondary" @click="filters = true" aria-controls="filter-panel">
                    <x-sj-icon name="filter" class="size-4" /> Filters @if ($activeCount)<span class="badge bg-teal text-white">{{ $activeCount }}</span>@endif
                </button>
                <form method="get" action="{{ $base }}" @change="loading = true; $el.submit()">
                    @foreach (request()->except(['sort', 'page']) as $k => $v)
                        @foreach ((array) $v as $vv)<input type="hidden" name="{{ is_array($v) ? $k.'[]' : $k }}" value="{{ $vv }}">@endforeach
                    @endforeach
                    <label for="sort-mobile" class="sr-only">Sort by</label>
                    <select id="sort-mobile" name="sort" class="field min-h-11 py-2 text-sm">
                        @foreach ($sorts as $key => $label)<option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>@endforeach
                    </select>
                </form>
            </div>

            <div x-show="filters" x-cloak class="fixed inset-0 z-50 bg-charcoal/40 lg:hidden" @click="filters = false"></div>
            <aside id="filter-panel" :class="filters ? 'translate-x-0' : 'translate-x-full lg:translate-x-0'"
                   class="fixed inset-y-0 right-0 z-50 flex w-[88%] max-w-sm translate-x-full flex-col bg-white shadow-xl transition-transform lg:static lg:z-auto lg:w-auto lg:max-w-none lg:translate-x-0 lg:shadow-none"
                   aria-label="Filters" x-trap.noscroll="filters && window.innerWidth < 1024">
                <form method="get" action="{{ $base }}" class="flex h-full flex-col" @submit="loading = true; filters = false">
                    <div class="flex h-14 items-center justify-between border-b border-line px-4 lg:hidden">
                        <h2 class="text-base font-semibold">Filters</h2>
                        <button type="button" class="btn-ghost -mr-3 px-3" @click="filters = false"><x-sj-icon name="close" /><span class="sr-only">Close filters</span></button>
                    </div>
                    <div class="flex-1 space-y-6 overflow-y-auto p-4 lg:p-0">
                        <div>
                            <label for="f-q" class="field-label">Search</label>
                            <input id="f-q" type="search" name="q" value="{{ $filters['q'] }}" class="field" placeholder="Name or SKU">
                        </div>
                        <div class="hidden lg:block">
                            <label for="f-sort" class="field-label">Sort by</label>
                            <select id="f-sort" name="sort" class="field">
                                @foreach ($sorts as $key => $label)<option value="{{ $key }}" @selected($filters['sort'] === $key)>{{ $label }}</option>@endforeach
                            </select>
                        </div>
                        @if ($filters['sub'] !== '')<input type="hidden" name="sub" value="{{ $filters['sub'] }}">@endif
                        <fieldset>
                            <legend class="field-label">Price (₹)</legend>
                            <div class="flex items-center gap-2">
                                <label class="sr-only" for="f-min">Minimum price</label>
                                <input id="f-min" type="number" inputmode="numeric" min="0" name="min" value="{{ $filters['min'] }}" placeholder="Min" class="field">
                                <span aria-hidden="true">–</span>
                                <label class="sr-only" for="f-max">Maximum price</label>
                                <input id="f-max" type="number" inputmode="numeric" min="0" name="max" value="{{ $filters['max'] }}" placeholder="Max" class="field">
                            </div>
                        </fieldset>
                        <label class="flex min-h-11 items-center gap-3 text-sm">
                            <input type="checkbox" name="in_stock" value="1" @checked($filters['in_stock']) class="size-5 rounded border-line text-teal focus:ring-teal">
                            In stock only
                        </label>
                        @foreach (['finish' => 'Finish', 'colour' => 'Colour', 'size' => 'Size'] as $key => $label)
                            @if (! empty($facets[$key]))
                                <fieldset>
                                    <legend class="field-label">{{ $label }}</legend>
                                    <div class="space-y-1">
                                        @foreach ($facets[$key] as $value)
                                            <label class="flex min-h-10 items-center gap-3 text-sm">
                                                <input type="checkbox" name="{{ $key }}[]" value="{{ $value }}" @checked(in_array($value, $filters[$key], true)) class="size-5 rounded border-line text-teal focus:ring-teal">
                                                {{ $value }}
                                            </label>
                                        @endforeach
                                    </div>
                                </fieldset>
                            @endif
                        @endforeach
                    </div>
                    <div class="flex gap-2 border-t border-line p-4 lg:mt-6 lg:border-0 lg:p-0">
                        <a href="{{ $base }}" class="btn-secondary flex-1">Clear</a>
                        <button class="btn-primary flex-1">Apply</button>
                    </div>
                </form>
            </aside>
        </div>

        <section aria-label="Products" class="mt-6 lg:mt-0" :aria-busy="loading">
            <div x-show="loading" x-cloak class="mb-4 flex items-center gap-2 text-sm text-muted" role="status">
                <span class="size-4 animate-spin rounded-full border-2 border-teal border-t-transparent"></span> Updating results…
            </div>
            @if ($products->isEmpty())
                <div class="rounded-2xl bg-pearl p-10 text-center">
                    <x-sj-icon name="search" class="mx-auto size-8 text-muted" />
                    <h2 class="mt-3 text-lg font-semibold">No pieces match these filters</h2>
                    <p class="mt-1 text-sm text-muted">Try removing a filter or searching for something else.</p>
                    <div class="mt-5 flex justify-center gap-3">
                        <a href="{{ $base }}" class="btn-secondary">Clear filters</a>
                        <a href="{{ route('shop') }}" class="btn-primary">Shop all</a>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 xl:grid-cols-4" :class="loading && 'opacity-50'">
                    @foreach ($products as $product)
                        @include('partials.product-card', ['product' => $product, 'eager' => $loop->index < 2])
                    @endforeach
                </div>
                <div class="mt-10">{{ $products->onEachSide(1)->links() }}</div>
            @endif
        </section>
    </div>
</div>
@if ($category)
    @push('scripts')
        <script>window.addEventListener('load', () => window.sjTrack('view_item_list', { item_list_id: @json($category->slug), item_list_name: @json($category->name) }));</script>
    @endpush
@endif
@endsection
