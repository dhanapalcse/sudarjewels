<header class="sticky top-0 z-40 border-b border-line bg-white/95 backdrop-blur" x-data="{ search: false }" @keydown.escape.window="search = false">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-2 focus:z-50 focus:rounded focus:bg-white focus:px-3 focus:py-2">Skip to content</a>
    <div class="container-page flex h-14 items-center gap-4 lg:h-[72px]">
        <x-brand />
        <nav class="mx-auto hidden lg:block" aria-label="Categories">
            <ul class="flex items-center gap-8 text-[15px]">
                <li><a href="{{ route('shop') }}" class="py-2 hover:text-teal {{ request()->routeIs('shop') ? 'text-teal' : '' }}" @if (request()->routeIs('shop')) aria-current="page" @endif>Shop All</a></li>
                @foreach ($navCategories as $navCategory)
                    @php($isCurrent = request()->is('category/'.$navCategory->slug))
                    <li><a href="{{ route('shop.category', $navCategory) }}" class="py-2 hover:text-teal {{ $isCurrent ? 'text-teal' : '' }}" @if ($isCurrent) aria-current="page" @endif>{{ $navCategory->name }}</a></li>
                @endforeach
            </ul>
        </nav>
        <form action="{{ route('shop') }}" method="get" role="search" class="relative hidden w-56 xl:w-64 lg:block">
            <label for="header-q-desktop" class="sr-only">Search jewellery</label>
            <x-sj-icon name="search" class="pointer-events-none absolute left-3.5 top-1/2 size-4 -translate-y-1/2 text-muted" />
            <input id="header-q-desktop" type="search" name="q" value="{{ request('q') }}" placeholder="Search jewellery"
                   class="h-10 w-full rounded-full border border-line bg-white pl-10 pr-4 text-sm placeholder:text-muted focus:border-teal focus:outline-none focus:ring-2 focus:ring-teal/20">
        </form>
        <div class="ml-auto flex items-center lg:ml-0">
            <button type="button" class="btn-ghost px-3 lg:hidden" @click="search = !search; $nextTick(() => search && $refs.q.focus())" :aria-expanded="search" aria-controls="site-search">
                <x-sj-icon name="search" /><span class="sr-only">Search</span>
            </button>
            <a href="{{ auth('customer')->check() ? route('account.dashboard') : route('customer.login') }}" class="btn-ghost -mr-3 px-3">
                <x-sj-icon name="user" class="size-6" /><span class="sr-only">{{ auth('customer')->check() ? 'My account' : 'Sign in' }}</span>
            </a>
        </div>
    </div>
    <div id="site-search" x-show="search" x-cloak x-collapse class="border-t border-line bg-white lg:hidden">
        <form action="{{ route('shop') }}" method="get" role="search" class="container-page flex gap-2 py-3">
            <label for="header-q" class="sr-only">Search jewellery</label>
            <input id="header-q" x-ref="q" type="search" name="q" value="{{ request('q') }}" placeholder="Search jewellery" class="field">
            <button class="btn-primary">Search</button>
        </form>
    </div>
</header>
