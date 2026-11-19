<x-mail::message>
# Quote revised

Order **{{ $order->order_number }}** for {{ $order->customer_name }} was revised before confirmation because prices or commission rates changed.

- New total: **{{ \App\Support\Money::format($order->grand_total_paise) }}**
- Estimated commission: **{{ \App\Support\Money::format($order->commission_estimate_paise) }}**

<x-mail::button :url="route('affiliate.orders.show', $order)">
Review the order
</x-mail::button>

SUDAR JEWELS
</x-mail::message>
