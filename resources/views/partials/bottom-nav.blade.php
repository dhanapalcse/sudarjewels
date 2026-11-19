@php
    $items = [
        ['Home', 'home', route('home'), request()->routeIs('home')],
        ['Shop', 'bag', route('shop'), request()->routeIs('shop', 'shop.category', 'products.show')],
        ['Warranty', 'shield', route('warranty.start'), request()->is('warranty*')],
        ['Account', 'user', auth('customer')->check() ? route('account.dashboard') : route('customer.login'), request()->is('account*')],
    ];
@endphp
<nav class="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur lg:hidden" aria-label="Main">
    <ul class="grid grid-cols-4">
        @foreach ($items as [$label, $icon, $url, $active])
            <li>
                <a href="{{ $url }}" class="flex min-h-16 flex-col items-center justify-center gap-1 text-xs {{ $active ? 'font-medium text-teal' : 'text-muted hover:text-teal' }}" @if ($active) aria-current="page" @endif>
                    <x-sj-icon :name="$icon" class="size-6" />{{ $label }}
                </a>
            </li>
        @endforeach
    </ul>
</nav>
