@extends('layouts.app')

@php
    $heroSrc = $hero ? \App\Models\ProductImage::urlFor($hero['webp'][array_key_last($hero['webp'])]) : null;
    $heroSet = $hero ? collect($hero['webp'])->map(fn ($p, $w) => \App\Models\ProductImage::urlFor($p).' '.$w.'w')->implode(', ') : null;
    $months = (int) $settings->get('warranty.months');
    $headline = $settings->get('home.hero_heading');
@endphp

@section('content')
    {{-- Hero --}}
    <section class="relative overflow-hidden bg-pearl" aria-labelledby="hero-title">
        <div class="container-page relative z-10 lg:flex lg:min-h-[460px] lg:items-center">
            <div class="pb-6 pt-6 text-center sm:pt-10 lg:w-[42%] lg:py-16 lg:pr-8 lg:text-left">
                <p class="inline-flex rounded-md border border-gold bg-white/70 px-3 py-1 text-xs text-charcoal sm:text-sm">{{ $settings->get('business.tagline') }}</p>
                {{-- Each sentence of the headline starts on its own line, as in the brand design. --}}
                <h1 id="hero-title" class="mt-4 font-display text-[2.75rem] italic leading-[1.05] text-teal-900 sm:text-6xl lg:text-7xl">{!! str_replace('. ', '.<br>', e($headline)) !!}</h1>
                <div class="mt-4 flex items-center justify-center gap-3 lg:justify-start" aria-hidden="true">
                    <span class="h-px w-16 bg-gold"></span><x-sj-icon name="star4" class="size-4 text-gold" /><span class="h-px w-16 bg-gold"></span>
                </div>
                @if ($settings->get('home.hero_subheading'))
                    <p class="mx-auto mt-4 max-w-md text-muted lg:mx-0">{{ $settings->get('home.hero_subheading') }}</p>
                @endif
                <a href="{{ route('shop') }}" class="btn-primary mt-7 hidden min-h-12 px-8 text-base lg:inline-flex">Explore Collection</a>
            </div>
        </div>
        {{-- Photo bleeds to the right edge of the screen on desktop --}}
        <div class="relative lg:absolute lg:inset-y-0 lg:right-0 lg:w-[58%]">
                <div class="relative aspect-[4/3] sm:aspect-[16/9] lg:aspect-auto lg:h-full">
                    @if ($heroSrc)
                        <img src="{{ $heroSrc }}" srcset="{{ $heroSet }}" sizes="(min-width: 1024px) 58vw, 100vw"
                             width="{{ $hero['width'] ?? 1600 }}" height="{{ $hero['height'] ?? 900 }}"
                             alt="{{ $hero['alt'] ?? 'SUDAR JEWELS jewellery' }}" fetchpriority="high" loading="eager" decoding="async"
                             class="h-full w-full object-cover">
                    @else
                        <div class="h-full w-full bg-mint"></div>
                    @endif
                    {{-- Soft blend into the pearl background --}}
                    <div class="pointer-events-none absolute inset-x-0 top-0 h-16 bg-gradient-to-b from-pearl to-transparent lg:inset-y-0 lg:left-0 lg:right-auto lg:h-full lg:w-1/3 lg:bg-gradient-to-r" aria-hidden="true"></div>
                    @if ($hero['is_sample'] ?? false)
                        <span class="badge absolute right-3 top-3 bg-white/90 text-charcoal ring-1 ring-line">Sample image</span>
                    @endif
                    <div class="absolute inset-x-0 bottom-5 flex justify-center lg:hidden">
                        <a href="{{ route('shop') }}" class="btn-primary min-h-12 px-8 text-base shadow-lg">Explore Collection</a>
                    </div>
                </div>
        </div>
    </section>

    {{-- Warranty strip --}}
    <section class="border-y border-teal/10 bg-mint" aria-label="Warranty">
        <div class="container-page flex flex-wrap items-center justify-center gap-x-5 gap-y-2 py-3.5 text-sm sm:text-base">
            <span class="inline-flex items-center gap-2 font-medium text-teal-900">
                <x-sj-icon name="shield-outline" class="size-6 text-teal" />{{ $months }}-month product warranty
            </span>
            <span class="hidden h-5 w-px bg-teal/30 sm:block" aria-hidden="true"></span>
            <a href="{{ route('warranty.start') }}" class="text-teal-900 underline underline-offset-4 hover:text-teal">Register Warranty</a>
        </div>
    </section>

    {{-- Shop by category --}}
    @if ($categories->isNotEmpty())
        <section class="container-page mt-10 sm:mt-14" aria-labelledby="cat-heading">
            <x-section-heading title="Shop by Category" id="cat-heading" />
            <ul class="mx-auto mt-6 grid max-w-4xl grid-cols-4 gap-3 sm:mt-8 sm:gap-8">
                @foreach ($categories->take(4) as $category)
                    <li>
                        <a href="{{ route('shop.category', $category) }}" class="group block text-center">
                            <div class="mx-auto aspect-square w-full max-w-44 overflow-hidden rounded-full bg-white ring-1 ring-line transition group-hover:ring-2 group-hover:ring-gold">
                                <x-product-image :image="$categoryImages[$category->id] ?? null" :alt="''" sizes="(min-width: 640px) 176px, 22vw" class="transition-transform duration-500 group-hover:scale-105" />
                            </div>
                            <span class="mt-2 block text-xs text-charcoal sm:mt-3 sm:text-lg">{{ $category->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if ($categories->count() > 4)
                <p class="mt-4 text-center text-sm"><a href="{{ route('shop') }}" class="text-teal underline underline-offset-4">More categories</a></p>
            @endif
        </section>
    @endif

    {{-- New arrivals --}}
    @if ($newArrivals->isNotEmpty())
        <section class="container-page mt-12 sm:mt-16" aria-labelledby="new-heading">
            <x-section-heading title="New Arrivals" id="new-heading" />
            <div class="mt-6 grid grid-cols-2 gap-3 sm:mt-8 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($newArrivals->take(8) as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
            <p class="mt-6 text-center"><a href="{{ route('shop', ['sort' => 'newest']) }}" class="btn-secondary">View all new arrivals</a></p>
        </section>
    @endif

    {{-- Best sellers --}}
    @if ($bestSellers->isNotEmpty())
        <section class="container-page mt-12 sm:mt-16" aria-labelledby="best-heading">
            <x-section-heading title="Best Sellers" id="best-heading" />
            <div class="mt-6 grid grid-cols-2 gap-3 sm:mt-8 sm:gap-5 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($bestSellers->take(8) as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Warranty highlight --}}
    <section class="container-page mt-14 sm:mt-16" aria-labelledby="warranty-heading">
        <div class="grid items-center gap-5 rounded-2xl border border-teal/10 bg-mint p-6 text-center sm:grid-cols-[auto_1fr_auto] sm:p-8 sm:text-left">
            <x-sj-icon name="shield" class="mx-auto size-12 text-teal sm:mx-0" />
            <div>
                <h2 id="warranty-heading" class="font-display text-3xl italic text-teal-900">{{ $settings->get('home.warranty_heading') }}</h2>
                <p class="mt-1 text-muted">{{ $settings->get('home.warranty_text') }}</p>
            </div>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('warranty.start') }}" class="btn-primary">Register Warranty</a>
                <a href="{{ route('pages.warranty-coverage') }}" class="btn-secondary bg-white">What's covered</a>
            </div>
        </div>
    </section>
@endsection
