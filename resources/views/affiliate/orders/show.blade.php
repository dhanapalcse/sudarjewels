@extends('layouts.affiliate')

@php($m = fn ($p) => \App\Support\Money::format($p))
@php($tz = config('sudar.business.timezone'))

@section('content')
<a href="{{ route('affiliate.orders.index') }}" class="text-sm text-teal underline">← My orders</a>
<div class="mt-3 flex flex-wrap items-center gap-2">
    <h1 class="display text-4xl">{{ $order->order_number }}</h1>
    @include('partials.status-badge', ['status' => $order->status])
    @include('partials.status-badge', ['status' => $order->payment_status])
    @include('partials.status-badge', ['status' => $order->fulfilment_status])
</div>
<p class="mt-1 text-sm text-muted">{{ $order->customer_name }} · {{ $order->created_at->timezone($tz)->format('j M Y') }}</p>

@php($unack = $order->revisions->whereNull('acknowledged_at'))
@if ($order->revisions->isNotEmpty())
    <div class="mt-6 rounded-xl border border-gold bg-white p-4 text-sm" role="status">
        <p class="font-semibold">Quote revised (revision {{ $order->quote_revision }})</p>
        @foreach ($order->revisions as $rev)
            <div class="mt-2">
                <p class="text-muted">{{ $rev->created_at->timezone($tz)->format('j M Y, g:i a') }}@if ($rev->reason) — {{ $rev->reason }}@endif</p>
                <ul class="list-disc pl-5">
                    @foreach ($rev->changes['items'] ?? [] as $chg)
                        <li>{{ $chg['item'] ?? 'Order' }}: {{ $chg['field'] }} {{ in_array($chg['field'], ['price']) ? $m((int) $chg['from']).' → '.$m((int) $chg['to']) : ($chg['field'] === 'commission rate' ? \App\Support\Money::formatRate((int) $chg['from']).' → '.\App\Support\Money::formatRate((int) $chg['to']) : $chg['from'].' → '.$chg['to']) }}</li>
                    @endforeach
                </ul>
                <p>New total {{ $m($rev->changes['totals']['after']['grand_total_paise'] ?? 0) }} · commission {{ $m($rev->changes['totals']['after']['commission_estimate_paise'] ?? 0) }}</p>
            </div>
        @endforeach
        @if ($unack->isNotEmpty())
            <form method="post" action="{{ route('affiliate.orders.acknowledge', $order) }}" class="mt-3">@csrf<button class="btn-secondary min-h-10">I've informed the customer</button></form>
        @endif
    </div>
@endif

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
    <div class="card divide-y divide-line">
        @foreach ($order->items as $item)
            <div class="flex justify-between gap-3 p-4 text-sm">
                <div><p class="font-medium">{{ $item->quantity }} × {{ $item->product_name }}</p><p class="text-muted">{{ $item->variant_name }} · {{ $item->sku }} · commission {{ \App\Support\Money::formatRate($item->commission_enabled ? $item->commission_rate_bp : 0) }}</p></div>
                <p class="text-right">{{ $m($item->line_total_paise) }}<span class="block text-teal">{{ $m($item->commission_paise) }}</span></p>
            </div>
        @endforeach
    </div>
    <aside class="space-y-4">
        <dl class="card space-y-1 p-4 text-sm">
            <div class="flex justify-between"><dt class="text-muted">Shipping</dt><dd>{{ $order->shipping_paise ? $m($order->shipping_paise) : 'Free' }}</dd></div>
            <div class="flex justify-between font-semibold"><dt>Total</dt><dd>{{ $m($order->grand_total_paise) }}</dd></div>
            <div class="flex justify-between text-teal"><dt>Estimated commission</dt><dd>{{ $m($order->commission_estimate_paise) }}</dd></div>
        </dl>
        @if ($order->commissionEntries->isNotEmpty())
            <div class="card p-4 text-sm">
                <p class="font-medium">Commission</p>
                <ul class="mt-2 space-y-1">
                    @foreach ($order->commissionEntries as $e)
                        <li class="flex justify-between gap-2"><span>{{ $e->type->label() }} · {{ $e->status->label() }}</span><span>{{ $m($e->amount_paise) }}</span></li>
                    @endforeach
                </ul>
            </div>
        @endif
        @if ($canEdit)
            <a href="{{ route('affiliate.orders.edit', $order) }}" class="btn-secondary w-full bg-white">Edit order</a>
        @elseif (in_array($order->status->value, ['confirmed', 'completed']))
            <form method="post" action="{{ route('affiliate.orders.change-request', $order) }}" class="card space-y-3 p-4">
                @csrf
                <label for="message" class="field-label">Request a change</label>
                <textarea id="message" name="message" rows="3" required class="field" placeholder="Describe what needs to change"></textarea>
                @error('message')<p class="field-error">{{ $message }}</p>@enderror
                <button class="btn-secondary w-full">Send to the team</button>
            </form>
            @foreach ($order->changeRequests as $cr)
                <div class="rounded-xl bg-white p-3 text-xs"><p><strong>{{ ucfirst($cr->status) }}</strong> · {{ $cr->created_at->timezone($tz)->format('j M') }}</p><p class="mt-1">{{ $cr->message }}</p>@if ($cr->response)<p class="mt-1 text-teal">Team: {{ $cr->response }}</p>@endif</div>
            @endforeach
        @endif
    </aside>
</div>
@endsection
