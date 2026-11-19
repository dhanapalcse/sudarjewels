@props(['size' => 'text-[1.4rem] sm:text-[1.75rem]'])
<a href="{{ route('home') }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 text-teal']) }} aria-label="{{ app(\App\Support\Settings::class)->get('business.name') }} home">
    <x-sj-icon name="star4" class="size-6 text-gold sm:size-7" />
    <span class="font-display {{ $size }} tracking-wide">SUDAR JEWELS</span>
</a>
