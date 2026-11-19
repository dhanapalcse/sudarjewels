@extends('layouts.app')

@php
    $fmt = fn ($p) => \App\Support\Money::format($p);
    $warrantyMonths = $product->warranty_eligible ? $product->warrantyMonths() : null;
@endphp

{{-- The sticky WhatsApp bar replaces the bottom navigation on product pages. --}}
@section('hideBottomNav', '1')

@section('content')
<div class="container-page pb-28 pt-4 lg:pb-10">
    @if ($isPreview)
        <p class="mb-4 rounded-xl border border-gold bg-pearl px-4 py-3 text-sm" role="note">Draft preview — this product is not visible to customers.</p>
    @endif
    <nav aria-label="Breadcrumb" class="text-sm text-muted">
        <ol class="flex flex-wrap items-center gap-1">
            <li><a href="{{ route('home') }}" class="hover:text-teal">Home</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('shop.category', $product->category) }}" class="hover:text-teal">{{ $product->category->name }}</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="text-charcoal">{{ $product->name }}</li>
        </ol>
    </nav>

    <div class="mt-4 grid gap-8 lg:grid-cols-2 lg:gap-12">
        {{-- Gallery --}}
        <section aria-label="Product images" x-data="gallery({{ max(1, $images->count()) }})" class="lg:sticky lg:top-24 lg:self-start">
            <div class="relative overflow-hidden rounded-2xl bg-pearl">
                <div x-ref="track" @scroll.debounce.50ms="onScroll" class="no-scrollbar flex aspect-square snap-x snap-mandatory overflow-x-auto"
                     tabindex="0" aria-roledescription="carousel" @keydown.arrow-right.prevent="go(index + 1)" @keydown.arrow-left.prevent="go(index - 1)">
                    @forelse ($images as $image)
                        <div class="relative h-full w-full shrink-0 snap-center" aria-roledescription="slide" aria-label="Image {{ $loop->iteration }} of {{ $images->count() }}">
                            <x-product-image :image="$image" :eager="$loop->first" sizes="(min-width: 1024px) 50vw, 100vw" :alt="$image->alt ?: $product->name" />
                        </div>
                    @empty
                        <div class="h-full w-full shrink-0"><x-product-image :image="null" :alt="$product->name" /></div>
                    @endforelse
                </div>
                @if ($product->is_sample)
                    <span class="badge absolute left-3 top-3 bg-white/90 text-charcoal ring-1 ring-line">Sample product</span>
                @endif
                @if ($images->isNotEmpty())
                    <button type="button" x-ref="zoomTrigger" @click="openZoom()" class="btn absolute bottom-3 right-3 min-h-10 bg-white/90 px-3 text-charcoal shadow-sm hover:bg-white">
                        <x-sj-icon name="zoom" class="size-4" /><span class="sr-only">Zoom image</span>
                    </button>
                @endif
                @if ($images->count() > 1)
                    <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5 lg:hidden" aria-hidden="true">
                        @foreach ($images as $image)<span class="h-1.5 rounded-full transition-all" :class="index === {{ $loop->index }} ? 'w-5 bg-teal' : 'w-1.5 bg-white'"></span>@endforeach
                    </div>
                @endif
            </div>
            @if ($images->count() > 1)
                <ul class="mt-3 hidden gap-2 lg:flex" aria-label="Choose image">
                    @foreach ($images as $image)
                        <li>
                            <button type="button" @click="go({{ $loop->index }})" class="size-20 overflow-hidden rounded-lg ring-2 ring-offset-2" :class="index === {{ $loop->index }} ? 'ring-teal' : 'ring-transparent'" :aria-current="index === {{ $loop->index }}">
                                <x-product-image :image="$image" sizes="80px" :alt="'View image '.$loop->iteration" />
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif

            {{-- Zoom dialog --}}
            <div x-show="zoomOpen" x-cloak class="fixed inset-0 z-50 flex flex-col bg-white" role="dialog" aria-modal="true" aria-label="Zoomed image" x-trap.noscroll="zoomOpen" @keydown.escape="closeZoom()" @keydown.arrow-right="go(index + 1)" @keydown.arrow-left="go(index - 1)">
                <div class="flex h-14 items-center justify-between px-4">
                    <span class="text-sm text-muted" x-text="`${index + 1} / ${count}`"></span>
                    <button type="button" x-ref="zoomClose" class="btn-ghost -mr-3 px-3" @click="closeZoom()"><x-sj-icon name="close" /><span class="sr-only">Close</span></button>
                </div>
                <div class="relative flex-1 overflow-hidden" style="touch-action: pinch-zoom">
                    @foreach ($images as $image)
                        <img x-show="index === {{ $loop->index }}" src="{{ $image->url(1400) }}" alt="{{ $image->alt ?: $product->name }}" loading="lazy"
                             @click="toggleZoom($event)" :style="`transform-origin: ${origin}; transform: scale(${zoomed ? 2.2 : 1})`"
                             class="h-full w-full cursor-zoom-in object-contain transition-transform duration-200" :class="zoomed && 'cursor-zoom-out'">
                    @endforeach
                    @if ($images->count() > 1)
                        <button type="button" @click="go(index - 1)" class="btn absolute left-2 top-1/2 -translate-y-1/2 bg-white/90 px-3 shadow"><x-sj-icon name="chevron-left" /><span class="sr-only">Previous</span></button>
                        <button type="button" @click="go(index + 1)" class="btn absolute right-2 top-1/2 -translate-y-1/2 bg-white/90 px-3 shadow"><x-sj-icon name="chevron-right" /><span class="sr-only">Next</span></button>
                    @endif
                </div>
            </div>
        </section>

        {{-- Details and enquiry --}}
        <section aria-labelledby="product-title"
                 x-data="enquiry({ variants: @js($variants), basePrice: {{ $product->minPrice() }}, endpoint: @js(route('enquiry.whatsapp')), csrf: @js(csrf_token()), name: @js($product->name) })">
            <h1 id="product-title" class="display text-4xl leading-tight sm:text-5xl">{{ $product->name }}</h1>
            <p class="mt-3 flex flex-wrap items-baseline gap-x-3">
                <span class="text-2xl font-semibold" x-text="new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR', maximumFractionDigits: price % 100 ? 2 : 0 }).format(price / 100)">{{ $fmt($product->minPrice()) }}</span>
                @if ($product->hasDiscount())
                    <span class="text-muted line-through"><span class="sr-only">Regular price </span>{{ $fmt($product->regular_price_paise) }}</span>
                @endif
                <span class="text-xs text-muted">Inclusive of taxes</span>
            </p>
            <dl class="mt-3 flex flex-wrap gap-x-6 gap-y-1 text-sm text-muted">
                <div class="flex gap-1"><dt>SKU:</dt><dd class="text-charcoal" x-text="selected ? selected.sku : @js($product->sku)">{{ $product->sku }}</dd></div>
                <div class="flex gap-1"><dt>Availability:</dt>
                    <dd class="text-charcoal" x-text="selected ? (selected.available > 0 ? (selected.available <= 3 ? `Only ${selected.available} left` : 'In stock') : 'Out of stock') : @js($available > 0 ? 'In stock' : 'Out of stock')">{{ $available > 0 ? 'In stock' : 'Out of stock' }}</dd>
                </div>
            </dl>
            @if ($product->short_description)
                <p class="mt-5 text-base leading-relaxed">{{ $product->short_description }}</p>
            @endif

            <form method="post" action="{{ route('enquiry.whatsapp') }}" @submit="submit($event)" class="mt-6 space-y-5" novalidate>
                @csrf
                @if ($referral)<input type="hidden" name="ref" value="{{ $referral->affiliate->code }}">@endif
                @if ($variants->count() > 1)
                    <fieldset>
                        <legend class="field-label">Choose an option</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($variants as $variant)
                                <label class="chip cursor-pointer has-[:checked]:chip-active has-[:disabled]:cursor-not-allowed has-[:disabled]:opacity-50 has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-teal">
                                    <input type="radio" name="variant_id" value="{{ $variant['id'] }}" class="sr-only" x-model.number="variantId" @change="error = ''; quantity = 1" @disabled($variant['available'] < 1)>
                                    {{ $variant['label'] }}@if ($variant['available'] < 1)<span class="sr-only"> (out of stock)</span>@endif
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @elseif ($variants->count() === 1)
                    <input type="hidden" name="variant_id" value="{{ $variants[0]['id'] }}">
                    @if ($variants[0]['label'] !== 'Standard')<p class="text-sm"><span class="text-muted">Option:</span> {{ $variants[0]['label'] }}</p>@endif
                @endif

                <div>
                    <label for="qty" class="field-label">Quantity</label>
                    <div class="inline-flex items-center rounded-full border border-line">
                        <button type="button" class="btn-ghost px-4" @click="dec()" :disabled="quantity <= 1"><x-sj-icon name="minus" class="size-4" /><span class="sr-only">Decrease quantity</span></button>
                        <input id="qty" name="quantity" type="number" inputmode="numeric" min="1" :max="maxQty" x-model.number="quantity" value="1" class="w-12 border-0 bg-transparent text-center text-base focus:ring-0">
                        <button type="button" class="btn-ghost px-4" @click="inc()" :disabled="quantity >= maxQty"><x-sj-icon name="plus" class="size-4" /><span class="sr-only">Increase quantity</span></button>
                    </div>
                </div>

                <p x-ref="error" x-show="error" x-cloak x-text="error" tabindex="-1" class="rounded-lg bg-red-50 px-3 py-2 text-sm text-danger" role="alert"></p>
                @error('variant_id')<p class="field-error" role="alert">{{ $message }}</p>@enderror

                {{-- Sticky on mobile, inline on desktop --}}
                <div class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 p-3 backdrop-blur lg:static lg:border-0 lg:bg-transparent lg:p-0">
                    @if ($whatsappConfigured)
                        <button type="submit" class="btn-primary w-full text-base" :disabled="busy" @if ($available < 1) disabled @endif>
                            <x-sj-icon name="whatsapp" /> <span x-text="busy ? 'Opening WhatsApp…' : 'Enquire on WhatsApp'">Enquire on WhatsApp</span>
                        </button>
                    @else
                        <a href="{{ route('pages.contact') }}" class="btn-primary w-full text-base">Contact us to order</a>
                    @endif
                </div>
            </form>

            <div class="mt-8 divide-y divide-line border-y border-line" x-data="{ open: 'details' }">
                @foreach (array_filter([
                    'details' => ['Description', $product->description ?: $product->short_description],
                    'materials' => ['Materials', $product->materials],
                    'care' => ['Care instructions', $product->care_instructions],
                ], fn ($s) => filled($s[1])) as $key => [$heading, $body])
                    <div>
                        <h2><button type="button" class="flex min-h-14 w-full items-center justify-between text-left font-medium" @click="open = open === '{{ $key }}' ? null : '{{ $key }}'" :aria-expanded="open === '{{ $key }}'">{{ $heading }}<x-sj-icon name="chevron-down" class="size-4 transition-transform" ::class="open === '{{ $key }}' && 'rotate-180'" /></button></h2>
                        <div x-show="open === '{{ $key }}'" x-collapse class="prose-sj pb-5 text-sm">{!! nl2br(e(strip_tags($body))) !!}</div>
                    </div>
                @endforeach
                <div>
                    <h2><button type="button" class="flex min-h-14 w-full items-center justify-between text-left font-medium" @click="open = open === 'warranty' ? null : 'warranty'" :aria-expanded="open === 'warranty'">Warranty<x-sj-icon name="chevron-down" class="size-4" /></button></h2>
                    <div x-show="open === 'warranty'" x-collapse class="pb-5 text-sm">
                        @if ($warrantyMonths)
                            <p><strong>{{ $warrantyMonths }}-month warranty</strong> from delivery. {{ $product->warranty_terms ?: $settings->get('warranty.default_terms') }}</p>
                            <p class="mt-2"><a href="{{ route('pages.warranty-coverage') }}" class="text-teal underline">Full coverage details</a> · <a href="{{ route('warranty.start') }}" class="text-teal underline">Register warranty</a></p>
                        @else
                            <p>This piece is not covered by the standard warranty.</p>
                        @endif
                    </div>
                </div>
                <div>
                    <h2><button type="button" class="flex min-h-14 w-full items-center justify-between text-left font-medium" @click="open = open === 'delivery' ? null : 'delivery'" :aria-expanded="open === 'delivery'">Delivery &amp; returns<x-sj-icon name="chevron-down" class="size-4" /></button></h2>
                    <div x-show="open === 'delivery'" x-collapse class="space-y-2 pb-5 text-sm">
                        <p>{{ $settings->get('shipping.delivery_summary') }}</p>
                        <p>{{ $settings->get('returns.summary') }}</p>
                        <p><a href="{{ route('pages.delivery') }}" class="text-teal underline">Delivery policy</a> · <a href="{{ route('pages.returns') }}" class="text-teal underline">Returns policy</a></p>
                    </div>
                </div>
            </div>
        </section>
    </div>

    @if ($related->isNotEmpty())
        <section class="mt-16" aria-labelledby="related-heading">
            <h2 id="related-heading" class="display text-3xl">You may also like</h2>
            <div class="mt-6 grid grid-cols-2 gap-x-3 gap-y-8 sm:gap-x-5 md:grid-cols-4">
                @foreach ($related as $p)
                    @include('partials.product-card', ['product' => $p])
                @endforeach
            </div>
        </section>
    @endif
</div>
@unless ($isPreview)
    @push('scripts')
        <script>window.addEventListener('load', () => window.sjTrack('view_item', { currency: 'INR', value: {{ $product->minPrice() / 100 }}, items: [{ item_id: @json($product->sku), item_name: @json($product->name), item_category: @json($product->category->name), price: {{ $product->minPrice() / 100 }} }] }));</script>
    @endpush
@endunless
@endsection
