@props(['title', 'id' => null, 'as' => 'h2'])
<div {{ $attributes->merge(['class' => 'flex items-center justify-center gap-3 sm:gap-5']) }}>
    <span class="h-px max-w-40 flex-1 bg-gold/70" aria-hidden="true"></span>
    <{{ $as }} @if ($id) id="{{ $id }}" @endif class="font-display text-3xl italic text-teal-900 sm:text-4xl">{{ $title }}</{{ $as }}>
    <span class="h-px max-w-40 flex-1 bg-gold/70" aria-hidden="true"></span>
</div>
