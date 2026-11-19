@extends('layouts.account')

@section('account')
<h1 class="display text-4xl">Hello, {{ \Illuminate\Support\Str::before($customer->name, ' ') }}</h1>
<div class="mt-6 grid gap-4 sm:grid-cols-3">
    <a href="{{ route('account.orders') }}" class="card p-5 hover:border-teal"><p class="text-sm text-muted">Orders</p><p class="mt-1 text-2xl font-semibold">{{ $customer->orders()->where('status', '!=', 'draft')->count() }}</p></a>
    <a href="{{ route('account.warranties') }}" class="card p-5 hover:border-teal"><p class="text-sm text-muted">Registered pieces</p><p class="mt-1 text-2xl font-semibold">{{ $warrantyCount }}</p></a>
    <a href="{{ route('account.claims') }}" class="card p-5 hover:border-teal"><p class="text-sm text-muted">Open claims</p><p class="mt-1 text-2xl font-semibold">{{ $openClaims }}</p></a>
</div>
<h2 class="mt-10 text-lg font-semibold">Recent orders</h2>
@include('account.partials.order-list', ['orders' => $orders])
@endsection
