@php($privateArea = true)
<!DOCTYPE html>
<html lang="en-IN">
<head>
    @include('partials.head', ['seo' => $seo ?? \App\Support\Seo::make('Affiliate', null, ['noindex' => true])])
</head>
<body class="min-h-screen bg-pearl pb-20 lg:pb-0">
    @php($aff = auth()->user()->affiliate)
    <header class="sticky top-0 z-30 border-b border-line bg-white">
        <div class="container-page flex h-14 items-center gap-4">
            <a href="{{ route('affiliate.dashboard') }}" class="font-display text-lg tracking-[0.18em] text-teal">SUDAR <span class="font-sans text-xs tracking-normal text-muted">Affiliates</span></a>
            <nav class="ml-6 hidden gap-5 text-sm lg:flex" aria-label="Affiliate">
                @foreach (['affiliate.dashboard' => 'Overview', 'affiliate.catalogue' => 'Catalogue', 'affiliate.orders.index' => 'Orders', 'affiliate.customers' => 'Customers', 'affiliate.share' => 'Share', 'affiliate.earnings' => 'Earnings', 'affiliate.payouts' => 'Payouts', 'affiliate.warranty' => 'Warranty', 'affiliate.profile' => 'Profile', 'affiliate.help' => 'Help'] as $r => $l)
                    <a href="{{ route($r) }}" class="py-2 {{ request()->routeIs($r) ? 'text-teal' : 'hover:text-teal' }}" @if (request()->routeIs($r)) aria-current="page" @endif>{{ $l }}</a>
                @endforeach
            </nav>
            <div class="ml-auto flex items-center gap-2">
                <span class="hidden text-sm text-muted sm:inline">Code <strong class="text-charcoal">{{ $aff->code }}</strong></span>
                <form method="post" action="{{ route('affiliate.logout') }}">@csrf<button class="btn-ghost px-3"><x-sj-icon name="logout" /><span class="sr-only">Sign out</span></button></form>
            </div>
        </div>
    </header>
    @if (! $aff->isActive())
        <div class="container-page mt-4"><p class="rounded-xl border border-gold bg-white px-4 py-3 text-sm" role="note">Your affiliate account is <strong>{{ $aff->status->label() }}</strong>. You can browse the catalogue, but creating orders is available once the team approves and activates your account.</p></div>
    @endif
    @include('partials.flash')
    <main id="main" class="container-page py-6">
        @yield('content')
    </main>
    <nav class="fixed inset-x-0 bottom-0 z-30 grid grid-cols-5 border-t border-line bg-white lg:hidden" aria-label="Affiliate">
        @foreach ([['affiliate.dashboard', 'home', 'Home'], ['affiliate.catalogue', 'search', 'Catalogue'], ['affiliate.orders.create', 'plus', 'New order'], ['affiliate.orders.index', 'bag', 'Orders'], ['affiliate.earnings', 'wallet', 'Earnings']] as [$r, $i, $l])
            <a href="{{ route($r) }}" class="flex min-h-16 flex-col items-center justify-center gap-1 text-xs {{ request()->routeIs($r) ? 'text-teal' : 'text-muted' }}" @if (request()->routeIs($r)) aria-current="page" @endif><x-sj-icon :name="$i" />{{ $l }}</a>
        @endforeach
    </nav>
</body>
</html>
