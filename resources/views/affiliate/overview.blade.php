@extends('layouts.affiliate')

@php($m = fn ($p) => \App\Support\Money::format($p))

@section('content')
<h1 class="display text-4xl">Hello, {{ \Illuminate\Support\Str::before(auth()->user()->name, ' ') }}</h1>
<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="card p-4"><p class="text-xs text-muted">Pending commission</p><p class="mt-1 text-xl font-semibold">{{ $m($balances['pending']) }}</p></div>
    <div class="card p-4"><p class="text-xs text-muted">Payable</p><p class="mt-1 text-xl font-semibold">{{ $m($balances['payable'] + $balances['held']) }}</p></div>
    <div class="card p-4"><p class="text-xs text-muted">Paid to date</p><p class="mt-1 text-xl font-semibold">{{ $m($balances['paid']) }}</p></div>
    <div class="card p-4"><p class="text-xs text-muted">Link visits (30 days)</p><p class="mt-1 text-xl font-semibold">{{ $visits }}</p></div>
</div>
<div class="mt-6 flex flex-wrap gap-3">
    @if ($affiliate->isActive())<a href="{{ route('affiliate.orders.create') }}" class="btn-primary"><x-sj-icon name="plus" class="size-4" />New order</a>@endif
    <a href="{{ route('affiliate.share') }}" class="btn-secondary bg-white"><x-sj-icon name="share" class="size-4" />Share products</a>
</div>
<h2 class="mt-10 text-lg font-semibold">Orders by status</h2>
<ul class="mt-3 flex flex-wrap gap-2 text-sm">
    @foreach (\App\Enums\OrderStatus::cases() as $s)
        <li><a href="{{ route('affiliate.orders.index', ['status' => $s->value]) }}" class="chip bg-white">{{ $s->label() }} · {{ $statusCounts[$s->value] ?? 0 }}</a></li>
    @endforeach
</ul>
<h2 class="mt-10 text-lg font-semibold">Recent orders</h2>
@include('affiliate.orders.partials.list', ['orders' => $recent])
@endsection
