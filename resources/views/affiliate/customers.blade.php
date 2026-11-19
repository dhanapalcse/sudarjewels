@extends('layouts.affiliate')

@section('content')
<h1 class="display text-4xl">My customers</h1>
<p class="mt-2 text-sm text-muted">Customers from orders assigned to you, with the details you entered.</p>
@if ($customers->isEmpty())
    <p class="mt-6 rounded-xl bg-white p-6 text-sm text-muted">No customers yet.</p>
@else
    <ul class="mt-6 divide-y divide-line rounded-2xl border border-line bg-white">
        @foreach ($customers as $c)
            <li class="flex flex-wrap items-center justify-between gap-2 p-4 text-sm">
                <span><span class="font-medium">{{ $c->customer_name }}</span><span class="block text-muted">{{ \App\Support\Phone::display($c->customer_phone) }}</span></span>
                <span class="text-muted">{{ $c->orders_count }} {{ \Illuminate\Support\Str::plural('order', $c->orders_count) }} · last {{ \Illuminate\Support\Carbon::parse($c->last_order_at)->timezone(config('sudar.business.timezone'))->format('j M Y') }}</span>
            </li>
        @endforeach
    </ul>
    <div class="mt-6">{{ $customers->links() }}</div>
@endif
@endsection
