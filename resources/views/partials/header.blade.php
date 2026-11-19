<header class="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur" x-data="{ menu: false, search: false }" @keydown.escape.window="menu = false; search = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>
    <div class="container-page flex h-14 items-center gap-2 lg:h-16">
        <button type="button" class="btn-ghost -ml-3 px-3 lg:hidden" @click="menu = true" aria-controls="mobile-menu" :aria-expanded="menu">
            <x-sj-icon name="menu" /><span class="sr-only">Open menu</span>
        </button>
        <a href="{{ route('home') }}" class="font-display text-xl tracking-[0.18em] text-teal sm:text-2xl" aria-label="{{ $settings->get('business.name') }} home">
            SUDAR <span class="text-charcoal">JEWELS</span>
        </a>
        <nav class="ml-8 hidden lg:block" aria-label="Categories">
            <ul class="flex items-center gap-6 text-sm">
                <li><a href="{{ route('shop') }}" class="py-2 hover:text-teal {{ request()->routeIs('shop') ? 'text-teal' : '' }}">Shop all</a></li>
                @foreach ($navCategories as $navCategory)
                    <li><a href="{{ route('shop.category', $navCategory) }}" class="py-2 hover:text-teal {{ request()->is('category/'.$navCategory->slug) ? 'text-teal' : '' }}">{{ $navCategory->name }}</a></li>
                @endforeach
            </ul>
        </nav>
        <div class="ml-auto flex items-center">
            <button type="button" class="btn-ghost px-3" @click="search = !search; $nextTick(() => search && $refs.q.focus())" :aria-expanded="search" aria-controls="site-search">
                <x-sj-icon name="search" /><span class="sr-only">Search</span>
            </button>
            <a href="{{ auth('customer')->check() ? route('account.dashboard') : route('customer.login') }}" class="btn-ghost -mr-3 px-3">
                <x-sj-icon name="user" /><span class="sr-only">{{ auth('customer')->check() ? 'My account' : 'Sign in' }}</span>
            </a>
        </div>
    </div>
    <div id="site-search" x-show="search" x-cloak x-collapse class="border-t border-line bg-white">
        <form action="{{ route('shop') }}" method="get" role="search" class="container-page flex gap-2 py-3">
            <label for="header-q" class="sr-only">Search jewellery</label>
            <input id="header-q" x-ref="q" type="search" name="q" value="{{ request('q') }}" placeholder="Search earrings, rings, SKU…" class="field">
            <button class="btn-primary">Search</button>
        </form>
    </div>

    {{-- Mobile menu --}}
    <div x-show="menu" x-cloak class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
        <div class="absolute inset-0 bg-charcoal/40" @click="menu = false"></div>
        <nav id="mobile-menu" class="absolute inset-y-0 left-0 flex w-80 max-w-[85%] flex-col bg-white shadow-xl" x-trap.noscroll="menu"
             x-transition:enter="transition duration-200" x-transition:enter-start="-translate-x-full" x-transition:enter-end="translate-x-0">
            <div class="flex h-14 items-center justify-between border-b border-line px-4">
                <span class="font-display text-xl tracking-[0.18em] text-teal">SUDAR</span>
                <button type="button" class="btn-ghost -mr-3 px-3" @click="menu = false"><x-sj-icon name="close" /><span class="sr-only">Close menu</span></button>
            </div>
            <ul class="flex-1 overflow-y-auto py-2 text-base">
                <li><a class="flex min-h-12 items-center px-4 hover:bg-pearl" href="{{ route('shop') }}">Shop all</a></li>
                @foreach ($navCategories as $navCategory)
                    <li><a class="flex min-h-12 items-center px-4 hover:bg-pearl" href="{{ route('shop.category', $navCategory) }}">{{ $navCategory->name }}</a></li>
                @endforeach
                <li class="mt-2 border-t border-line pt-2"><a class="flex min-h-12 items-center gap-2 px-4 hover:bg-pearl" href="{{ route('warranty.start') }}"><x-sj-icon name="shield" class="size-5 text-teal" />Register warranty</a></li>
                <li><a class="flex min-h-12 items-center px-4 hover:bg-pearl" href="{{ route('pages.about') }}">About</a></li>
                <li><a class="flex min-h-12 items-center px-4 hover:bg-pearl" href="{{ route('pages.contact') }}">Contact</a></li>
                <li><a class="flex min-h-12 items-center px-4 hover:bg-pearl" href="{{ auth('customer')->check() ? route('account.dashboard') : route('customer.login') }}">My account</a></li>
            </ul>
        </nav>
    </div>
</header>
