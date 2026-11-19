@extends('layouts.affiliate')

@php($m = fn ($p) => \App\Support\Money::format($p))

@section('content')
<h1 class="display text-4xl">My earnings</h1>
<div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div class="card p-4"><p class="text-xs text-muted">Pending</p><p class="mt-1 text-xl font-semibold">{{ $m($balances['pending']) }}</p></div>
    <div class="card p-4"><p class="text-xs text-muted">Payable</p><p class="mt-1 text-xl font-semibold">{{ $m($balances['payable']) }}</p></div>
    <div class="card p-4"><p class="text-xs text-muted">In payout</p><p class="mt-1 text-xl font-semibold">{{ $m($balances['held']) }}</p></div>
    <div class="card p-4"><p class="text-xs text-muted">Paid</p><p class="mt-1 text-xl font-semibold">{{ $m($balances['paid']) }}</p></div>
</div>
<p class="mt-4 text-xs text-muted">Pending commission becomes payable once the order is fully paid, delivered and the return window has passed. Refunds after payout appear as adjustments against future earnings.</p>
<div class="mt-6 overflow-x-auto rounded-2xl border border-line bg-white">
    <table class="w-full min-w-[560px] text-left text-sm">
        <thead class="border-b border-line bg-pearl text-xs text-muted"><tr><th class="p-3">Date</th><th class="p-3">Order</th><th class="p-3">Type</th><th class="p-3">Status</th><th class="p-3 text-right">Amount</th></tr></thead>
        <tbody class="divide-y divide-line">
            @forelse ($entries as $e)
                <tr><td class="p-3">{{ $e->created_at->timezone(config('sudar.business.timezone'))->format('j M Y') }}</td><td class="p-3">{{ $e->order?->order_number }}</td><td class="p-3">{{ $e->type->label() }}<span class="block text-xs text-muted">{{ $e->description }}</span></td><td class="p-3">{{ $e->status->label() }}</td><td class="p-3 text-right {{ $e->amount_paise < 0 ? 'text-danger' : '' }}">{{ $m($e->amount_paise) }}</td></tr>
            @empty
                <tr><td colspan="5" class="p-6 text-center text-muted">No commission yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-6">{{ $entries->links() }}</div>
@endsection
