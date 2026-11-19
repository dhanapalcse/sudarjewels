@extends('layouts.account')

@php($tz = config('sudar.business.timezone'))

@section('account')
<a href="{{ route('account.orders') }}" class="text-sm text-teal underline">← All orders</a>
<div class="mt-3 flex flex-wrap items-center gap-3">
    <h1 class="display text-4xl">{{ $order->order_number }}</h1>
    @include('partials.status-badge', ['status' => $order->status])
    @include('partials.status-badge', ['status' => $order->payment_status])
    @include('partials.status-badge', ['status' => $order->fulfilment_status])
</div>
<p class="mt-1 text-sm text-muted">Placed {{ $order->created_at->timezone($tz)->format('j M Y') }}</p>

<div class="mt-6 grid gap-6 lg:grid-cols-[1fr_320px]">
    <div class="card divide-y divide-line">
        @foreach ($order->items as $item)
            <div class="flex justify-between gap-4 p-4">
                <div>
                    <p class="font-medium">{{ $item->product_name }}</p>
                    <p class="text-sm text-muted">{{ $item->variant_name }} · {{ $item->sku }} · Qty {{ $item->quantity }}</p>
                    <p class="mt-1 text-xs text-muted">
                        @foreach ($item->units as $unit){{ $unit->status->label() }}@if ($unit->warranty_status->value === 'registered') (warranty until {{ $unit->warranty_ends_at?->timezone($tz)->format('j M Y') }})@endif{{ $loop->last ? '' : ' · ' }}@endforeach
                    </p>
                </div>
                <p class="text-right font-medium">{{ \App\Support\Money::format($item->line_total_paise) }}</p>
            </div>
        @endforeach
    </div>
    <aside class="space-y-4">
        <dl class="card space-y-2 p-4 text-sm">
            <div class="flex justify-between"><dt class="text-muted">Subtotal</dt><dd>{{ \App\Support\Money::format($order->subtotal_paise) }}</dd></div>
            @if ($order->discount_total_paise)<div class="flex justify-between"><dt class="text-muted">Discount</dt><dd>−{{ \App\Support\Money::format($order->discount_total_paise) }}</dd></div>@endif
            <div class="flex justify-between"><dt class="text-muted">Shipping</dt><dd>{{ $order->shipping_paise ? \App\Support\Money::format($order->shipping_paise) : 'Free' }}</dd></div>
            <div class="flex justify-between border-t border-line pt-2 font-semibold"><dt>Total</dt><dd>{{ \App\Support\Money::format($order->grand_total_paise) }}</dd></div>
        </dl>
        @foreach ($order->shipments as $shipment)
            <div class="card p-4 text-sm">
                <p class="font-medium">{{ $shipment->is_handover ? 'Handed over' : 'Shipment' }}</p>
                @if ($shipment->courier)<p class="text-muted">{{ $shipment->courier }} {{ $shipment->tracking_number }}</p>@endif
                @if ($shipment->tracking_url)<a href="{{ $shipment->tracking_url }}" class="text-teal underline" target="_blank" rel="noopener">Track</a>@endif
                <p class="text-muted">{{ $shipment->delivered_at ? 'Delivered '.$shipment->delivered_at->timezone($tz)->format('j M Y') : 'Dispatched '.$shipment->dispatched_at?->timezone($tz)->format('j M Y') }}</p>
            </div>
        @endforeach
        @if ($order->warrantyRegistration)
            <a href="{{ route('warranty.card', $order->warrantyRegistration) }}" class="btn-secondary w-full">View warranty card</a>
        @else
            <a href="{{ route('warranty.start') }}" class="btn-secondary w-full">Register warranty</a>
        @endif
    </aside>
</div>
@endsection
