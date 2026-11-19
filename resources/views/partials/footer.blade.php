@php
    $social = $settings->socialLinks();
    $icons = ['social.instagram' => 'instagram', 'social.facebook' => 'facebook', 'social.youtube' => 'youtube', 'social.pinterest' => 'pinterest'];
    $phone = $settings->get('business.phone');
    $email = $settings->get('business.email');
    $wa = $settings->whatsappNumber();
@endphp
<footer class="mt-16 border-t border-line bg-pearl">
    <div class="container-page grid gap-10 py-12 sm:grid-cols-2 lg:grid-cols-4">
        <div>
            <p class="font-display text-2xl tracking-[0.18em] text-teal">SUDAR JEWELS</p>
            <p class="mt-3 max-w-xs text-sm text-muted">{{ $settings->get('business.tagline') }}</p>
            @if ($social)
                <ul class="mt-4 flex gap-1" aria-label="Social media">
                    @foreach ($social as $key => $url)
                        <li><a href="{{ $url }}" class="btn-ghost px-2.5" rel="noopener" target="_blank"><x-sj-icon :name="$icons[$key]" /><span class="sr-only">{{ ucfirst($icons[$key]) }}</span></a></li>
                    @endforeach
                </ul>
            @endif
        </div>
        <div>
            <h2 class="text-sm font-semibold">Shop</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="hover:text-teal" href="{{ route('shop') }}">Shop all</a></li>
                @foreach ($navCategories as $navCategory)
                    <li><a class="hover:text-teal" href="{{ route('shop.category', $navCategory) }}">{{ $navCategory->name }}</a></li>
                @endforeach
            </ul>
        </div>
        <div>
            <h2 class="text-sm font-semibold">Help</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="hover:text-teal" href="{{ route('warranty.start') }}">Register warranty</a></li>
                <li><a class="hover:text-teal" href="{{ route('pages.warranty-coverage') }}">Warranty coverage</a></li>
                <li><a class="hover:text-teal" href="{{ route('pages.delivery') }}">Delivery</a></li>
                <li><a class="hover:text-teal" href="{{ route('pages.returns') }}">Returns</a></li>
                <li><a class="hover:text-teal" href="{{ route('pages.privacy') }}">Privacy</a></li>
                <li><a class="hover:text-teal" href="{{ route('pages.terms') }}">Terms</a></li>
                <li><a class="hover:text-teal" href="{{ route('pages.about') }}">About us</a></li>
            </ul>
        </div>
        <div>
            <h2 class="text-sm font-semibold">Contact</h2>
            <ul class="mt-3 space-y-2 text-sm">
                @if ($wa)<li><a class="inline-flex items-center gap-2 hover:text-teal" href="https://wa.me/{{ $wa }}" rel="noopener" target="_blank"><x-sj-icon name="whatsapp" class="size-4" />WhatsApp us</a></li>@endif
                @if ($phone)<li><a class="inline-flex items-center gap-2 hover:text-teal" href="tel:{{ $phone }}"><x-sj-icon name="phone" class="size-4" />{{ $phone }}</a></li>@endif
                @if ($email)<li><a class="inline-flex items-center gap-2 hover:text-teal" href="mailto:{{ $email }}"><x-sj-icon name="mail" class="size-4" />{{ $email }}</a></li>@endif
                <li><a class="hover:text-teal" href="{{ route('pages.contact') }}">Contact page</a></li>
            </ul>
            <h2 class="mt-6 text-sm font-semibold">Affiliates</h2>
            <ul class="mt-3 space-y-2 text-sm">
                <li><a class="hover:text-teal" href="{{ route('affiliate.login') }}">Affiliate login</a></li>
                <li><a class="hover:text-teal" href="{{ route('affiliate.apply') }}">Become an affiliate</a></li>
                <li><a class="hover:text-teal" href="{{ route('pages.affiliate-terms') }}">Affiliate terms</a></li>
            </ul>
        </div>
    </div>
    <div class="border-t border-line">
        <p class="container-page py-5 text-xs text-muted">© {{ now()->year }} {{ $settings->get('business.name') }}. Prices in INR.</p>
    </div>
</footer>
