@extends('layouts.affiliate')

@section('content')
<h1 class="display text-4xl">Warranty status</h1>
<p class="mt-2 text-sm text-muted">Registration status for pieces sold through your orders.</p>
<ul class="mt-6 divide-y divide-line rounded-2xl border border-line bg-white">
    @forelse ($units as $u)
        <li class="flex flex-wrap items-center justify-between gap-2 p-4 text-sm">
            <span><span class="font-medium">{{ $u->orderItem->product_name }}</span><span class="block text-muted">{{ $u->order->order_number }} · {{ $u->order->customer_name }} · {{ $u->status->label() }}</span></span>
            <span>{{ $u->warranty_status->label() }}@if ($u->warranty_ends_at) <span class="block text-xs text-muted">until {{ $u->warranty_ends_at->timezone(config('sudar.business.timezone'))->format('j M Y') }}</span>@endif</span>
        </li>
    @empty
        <li class="p-6 text-center text-sm text-muted">Nothing to show yet.</li>
    @endforelse
</ul>
<div class="mt-6">{{ $units->links() }}</div>
@endsection
