@extends('layouts.app')

@section('content')
    {{-- Hero: single prioritised image --}}
    <section class="container-page pt-4 sm:pt-6">
        <div class="relative overflow-hidden rounded-2xl bg-mint">
            <div class="aspect-[4/5] sm:aspect-[16/9] lg:aspect-[21/9]">
                @if ($hero)
                    <img src="{{ \App\Models\ProductImage::urlFor($hero['webp'][array_key_last($hero['webp'])]) }}"
                         srcset="{{ collect($hero['webp'])->map(fn ($p, $w) => \App\Models\ProductImage::urlFor($p).' '.$w.'w')->implode(', ') }}"
                         sizes="(min-width: 1280px) 1216px, 100vw" width="{{ $hero['width'] ?? 1600 }}" height="{{ $hero['height'] ?? 900 }}"
                         alt="{{ $hero['alt'] ?? 'SUDAR JEWELS jewellery' }}" fetchpriority="high" loading="eager" decoding="async"
                         class="h-full w-full object-cover">
                @endif
            </div>
            <div class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-charcoal/70 via-charcoal/25 to-transparent p-6 pt-24 sm:p-10 sm:pt-32">
                @if ($hero['is_sample'] ?? false)
                    <span class="badge mb-3 bg-white/90 text-charcoal">Sample image</span>
                @endif
                <h1 class="display max-w-xl text-4xl leading-tight text-white sm:text-5xl lg:text-6xl">{{ $settings->get('home.hero_heading') }}</h1>
                <p class="mt-3 max-w-md text-base text-white/90">{{ $settings->get('home.hero_subheading') }}</p>
                <a href="{{ route('shop') }}" class="btn mt-6 bg-white text-teal hover:bg-pearl">Explore collection <x-sj-icon name="chevron-right" class="size-4" /></a>
            </div>
        </div>
    </section>

    {{-- Shop by category --}}
    @if ($categories->isNotEmpty())
        <section class="container-page mt-14" aria-labelledby="cat-heading">
            <div class="flex items-end justify-between">
                <h2 id="cat-heading" class="display text-3xl sm:text-4xl">Shop by category</h2>
                <a href="{{ route('shop') }}" class="text-sm text-teal underline-offset-4 hover:underline">View all</a>
            </div>
            <ul class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-4">
                @foreach ($categories as $category)
                    <li>
                        <a href="{{ route('shop.category', $category) }}" class="group block">
                            <div class="aspect-square overflow-hidden rounded-xl bg-pearl">
                                <x-product-image :image="$categoryImages[$category->id] ?? null" :alt="$category->name" sizes="(min-width: 640px) 25vw, 50vw" class="transition-transform duration-500 group-hover:scale-[1.03]" />
                            </div>
                            <span class="mt-2 block text-center text-sm font-medium sm:text-base">{{ $category->name }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- New arrivals --}}
    @if ($newArrivals->isNotEmpty())
        <section class="container-page mt-14" aria-labelledby="new-heading">
            <div class="flex items-end justify-between">
                <h2 id="new-heading" class="display text-3xl sm:text-4xl">New arrivals</h2>
                <a href="{{ route('shop', ['sort' => 'newest']) }}" class="text-sm text-teal underline-offset-4 hover:underline">See more</a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($newArrivals as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    {{-- Warranty highlight --}}
    <section class="container-page mt-16" aria-labelledby="warranty-heading">
        <div class="grid items-center gap-6 rounded-2xl bg-mint p-6 sm:grid-cols-[auto_1fr_auto] sm:p-10">
            <x-sj-icon name="shield" class="size-12 text-teal" />
            <div>
                <h2 id="warranty-heading" class="display text-3xl">{{ $settings->get('home.warranty_heading') }}</h2>
                <p class="mt-2 max-w-xl text-muted">{{ $settings->get('home.warranty_text') }}</p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a href="{{ route('warranty.start') }}" class="btn-primary">Register warranty</a>
                <a href="{{ route('pages.warranty-coverage') }}" class="btn-secondary bg-white">What's covered</a>
            </div>
        </div>
    </section>

    {{-- Best sellers --}}
    @if ($bestSellers->isNotEmpty())
        <section class="container-page mt-14" aria-labelledby="best-heading">
            <div class="flex items-end justify-between">
                <h2 id="best-heading" class="display text-3xl sm:text-4xl">Best sellers</h2>
                <a href="{{ route('shop', ['sort' => 'best']) }}" class="text-sm text-teal underline-offset-4 hover:underline">See more</a>
            </div>
            <div class="mt-6 grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-3 lg:grid-cols-4">
                @foreach ($bestSellers as $product)
                    @include('partials.product-card', ['product' => $product])
                @endforeach
            </div>
        </section>
    @endif

    <section class="container-page mt-16 grid gap-6 border-y border-line py-10 text-sm sm:grid-cols-3">
        <div class="flex gap-3"><x-sj-icon name="sparkle" class="size-6 shrink-0 text-gold-700" /><p><strong class="block font-semibold">Anti-tarnish finish</strong><span class="text-muted">Made for everyday wear.</span></p></div>
        <div class="flex gap-3"><x-sj-icon name="truck" class="size-6 shrink-0 text-gold-700" /><p><strong class="block font-semibold">Delivery across India</strong><span class="text-muted">{{ \Illuminate\Support\Str::limit($settings->get('shipping.delivery_summary'), 70) }}</span></p></div>
        <div class="flex gap-3"><x-sj-icon name="whatsapp" class="size-6 shrink-0 text-gold-700" /><p><strong class="block font-semibold">Order on WhatsApp</strong><span class="text-muted">Personal help choosing your piece.</span></p></div>
    </section>
@endsection
