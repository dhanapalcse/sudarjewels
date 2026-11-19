@props(['image' => null, 'sizes' => '(min-width: 1024px) 25vw, 50vw', 'eager' => false, 'alt' => '', 'class' => ''])
@if ($image && $image->isPublished())
    <img
        src="{{ $image->url(640) }}"
        srcset="{{ $image->srcset() }}"
        sizes="{{ $sizes }}"
        width="{{ $image->width ?: 1000 }}"
        height="{{ $image->height ?: 1000 }}"
        alt="{{ $alt ?: $image->alt }}"
        @if ($eager) fetchpriority="high" loading="eager" @else loading="lazy" @endif
        decoding="async"
        {{ $attributes->merge(['class' => 'h-full w-full object-cover '.$class]) }}
    >
@else
    <div {{ $attributes->merge(['class' => 'flex h-full w-full items-center justify-center bg-pearl text-muted '.$class]) }} role="img" aria-label="{{ $alt ?: 'Image coming soon' }}">
        <x-sj-icon name="sparkle" class="size-8 text-gold" />
    </div>
@endif
