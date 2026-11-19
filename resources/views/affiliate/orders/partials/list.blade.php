@if ($orders->isEmpty())
    <p class="mt-3 rounded-xl bg-white p-6 text-sm text-muted">No orders yet.</p>
@else
    <ul class="mt-3 divide-y divide-line rounded-2xl border border-line bg-white">
        @foreach ($orders as $order)
            <li><a href="{{ route('affiliate.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-2 p-4 hover:bg-pearl">
                <span><span class="font-medium">{{ $order->order_number }}</span> · {{ $order->customer_name }}
                    <span class="block text-sm text-muted">{{ $order->created_at->timezone(config('sudar.business.timezone'))->format('j M Y') }} · {{ \App\Support\Money::format($order->grand_total_paise) }} · est. commission {{ \App\Support\Money::format($order->commission_estimate_paise) }}</span></span>
                <span class="flex gap-2">@include('partials.status-badge', ['status' => $order->status]) @include('partials.status-badge', ['status' => $order->fulfilment_status])</span>
            </a></li>
        @endforeach
    </ul>
@endif
