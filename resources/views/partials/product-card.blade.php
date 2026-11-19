@php
    /** @var \App\Models\Product $product */
    $available = $product->totalAvailable();
    $price = $product->minPrice();
@endphp
<article class="group relative flex flex-col overflow-hidden rounded-xl border border-line bg-white transition-shadow hover:shadow-md">
    <div class="relative aspect-[4/3] overflow-hidden bg-pearl">
        <x-product-image :image="$product->mainImage" :alt="$product->name" :eager="$eager ?? false"
            sizes="(min-width: 1280px) 22vw, (min-width: 768px) 30vw, 48vw"
            class="transition-transform duration-500 group-hover:scale-[1.04]" />
        <div class="absolute left-2 top-2 flex flex-col items-start gap-1">
            @if ($product->is_sample)
                <span class="badge bg-white/90 text-charcoal ring-1 ring-line">Sample</span>
            @endif
            @if ($available < 1)
                <span class="badge bg-charcoal text-white">Sold out</span>
            @elseif ($product->hasDiscount())
                <span class="badge bg-teal text-white">Offer</span>
            @endif
        </div>
    </div>
    <div class="flex flex-1 flex-col px-3 py-2.5 sm:px-4 sm:py-3">
        <h3 class="text-sm leading-snug text-charcoal sm:text-base">
            <a href="{{ route('products.show', $product) }}" class="after:absolute after:inset-0 focus-visible:outline-none">{{ $product->name }}</a>
        </h3>
        <p class="mt-0.5 flex flex-wrap items-baseline gap-x-2">
            <span class="font-semibold text-teal sm:text-lg">{{ \App\Support\Money::format($price) }}</span>
            @if ($product->hasDiscount())
                <span class="text-xs text-muted line-through sm:text-sm"><span class="sr-only">Regular price </span>{{ \App\Support\Money::format($product->regular_price_paise) }}</span>
            @endif
        </p>
    </div>
</article>
