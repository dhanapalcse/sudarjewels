@extends('layouts.app')

@section('content')
<div class="container-page pt-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-sm text-muted">Signed in as <span class="text-charcoal">{{ auth('customer')->user()->name }}</span></p>
        <form method="post" action="{{ route('customer.logout') }}">@csrf<button class="text-sm text-teal underline underline-offset-2">Sign out</button></form>
    </div>
    <nav class="no-scrollbar -mx-4 mt-4 overflow-x-auto border-b border-line px-4" aria-label="Account">
        <ul class="flex gap-6 text-sm">
            @foreach ([
                'account.dashboard' => 'Overview', 'account.orders' => 'My orders', 'account.warranties' => 'Warranties',
                'warranty.start' => 'Register warranty', 'account.claims' => 'Claims', 'account.profile' => 'Profile',
            ] as $route => $label)
                @php($active = request()->routeIs($route) || ($route !== 'account.dashboard' && request()->routeIs($route.'.*')))
                <li><a href="{{ route($route) }}" class="block whitespace-nowrap border-b-2 py-3 {{ $active ? 'border-teal text-teal' : 'border-transparent hover:text-teal' }}" @if ($active) aria-current="page" @endif>{{ $label }}</a></li>
            @endforeach
        </ul>
    </nav>
    <div class="py-8">
        @yield('account')
    </div>
</div>
@endsection
