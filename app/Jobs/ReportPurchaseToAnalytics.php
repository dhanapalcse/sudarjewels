<?php

namespace App\Jobs;

use App\Models\Order;
use App\Support\Settings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/**
 * Sends a GA4 "purchase" event (Measurement Protocol) once per order when it becomes fully paid.
 * Contains no names, phone numbers, addresses or warranty data.
 */
class ReportPurchaseToAnalytics implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public int $orderId) {}

    public function handle(Settings $settings): void
    {
        $measurementId = $settings->ga4Id();
        $secret = config('sudar.analytics.ga4_api_secret');
        if (! $measurementId || ! $secret) {
            return;
        }
        // Atomic claim prevents duplicate reporting across retries/workers.
        if (Order::whereKey($this->orderId)->whereNull('analytics_reported_at')->update(['analytics_reported_at' => now()]) !== 1) {
            return;
        }
        $order = Order::with('items')->findOrFail($this->orderId);

        $response = Http::timeout(10)->post('https://www.google-analytics.com/mp/collect?'.http_build_query([
            'measurement_id' => $measurementId, 'api_secret' => $secret,
        ]), [
            'client_id' => 'srv.'.substr(hash('sha256', 'order'.$order->id.config('app.key')), 0, 16),
            'events' => [[
                'name' => 'purchase',
                'params' => [
                    'transaction_id' => $order->order_number,
                    'currency' => 'INR',
                    'value' => $order->grand_total_paise / 100,
                    'shipping' => $order->shipping_paise / 100,
                    'tax' => $order->tax_paise / 100,
                    'items' => $order->items->map(fn ($i) => [
                        'item_id' => $i->sku, 'item_name' => $i->product_name, 'item_variant' => $i->variant_name,
                        'price' => $i->unit_price_paise / 100, 'quantity' => $i->quantity,
                    ])->all(),
                ],
            ]],
        ]);

        if (! $response->successful()) {
            Order::whereKey($this->orderId)->update(['analytics_reported_at' => null]);
            $response->throw();
        }
    }
}
