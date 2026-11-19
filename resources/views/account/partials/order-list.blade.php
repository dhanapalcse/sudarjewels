@if ($orders->isEmpty())
    <p class="mt-4 rounded-xl bg-pearl p-6 text-sm text-muted">No orders yet. <a href="{{ route('shop') }}" class="text-teal underline">Browse the collection</a>.</p>
@else
    <ul class="mt-4 divide-y divide-line rounded-2xl border border-line">
        @foreach ($orders as $order)
            <li>
                <a href="{{ route('account.orders.show', $order) }}" class="flex flex-wrap items-center justify-between gap-2 p-4 hover:bg-pearl">
                    <span><span class="font-medium">{{ $order->order_number }}</span> <span class="block text-sm text-muted">{{ $order->created_at->timezone(config('sudar.business.timezone'))->format('j M Y') }} · {{ \App\Support\Money::format($order->grand_total_paise) }}</span></span>
                    <span class="flex gap-2">@include('partials.status-badge', ['status' => $order->status]) @include('partials.status-badge', ['status' => $order->fulfilment_status])</span>
                </a>
            </li>
        @endforeach
    </ul>
@endif
