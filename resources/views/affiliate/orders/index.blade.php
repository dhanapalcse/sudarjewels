@extends('layouts.affiliate')

@section('content')
<div class="flex flex-wrap items-center justify-between gap-3">
    <h1 class="display text-4xl">My orders</h1>
    <a href="{{ route('affiliate.orders.create') }}" class="btn-primary">New order</a>
</div>
<ul class="no-scrollbar mt-4 flex gap-2 overflow-x-auto text-sm">
    <li><a href="{{ route('affiliate.orders.index') }}" class="chip whitespace-nowrap bg-white {{ ! $status ? 'chip-active' : '' }}">All</a></li>
    @foreach (\App\Enums\OrderStatus::cases() as $s)
        <li><a href="{{ route('affiliate.orders.index', ['status' => $s->value]) }}" class="chip whitespace-nowrap bg-white {{ $status === $s->value ? 'chip-active' : '' }}">{{ $s->label() }}</a></li>
    @endforeach
</ul>
@include('affiliate.orders.partials.list', ['orders' => $orders])
<div class="mt-6">{{ $orders->links() }}</div>
@endsection
