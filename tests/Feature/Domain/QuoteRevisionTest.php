<?php

namespace Tests\Feature\Domain;

use App\Domain\Orders\OrderService;
use App\Domain\Orders\QuoteChanged;
use App\Enums\AttributionSource;
use App\Enums\OrderStatus;
use App\Enums\SalesChannel;
use App\Mail\AffiliateQuoteRevised;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class QuoteRevisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_price_change_blocks_confirmation_until_revision_is_recorded(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $affiliate = $this->affiliate();
        $variant = $this->variant(100000, 5);
        $service = app(OrderService::class);

        $order = $service->saveQuote(null, [
            'customer_name' => 'Asha', 'customer_phone' => '9123456789', 'delivery_required' => false,
            'channel' => SalesChannel::Affiliate->value,
            'lines' => [['variant_id' => $variant->id, 'quantity' => 1]],
        ], $affiliate->user, ['affiliate' => $affiliate, 'attribution_source' => AttributionSource::DirectAffiliate, 'status' => OrderStatus::Submitted]);

        $variant->product->update(['price_paise' => 120000]);

        try {
            $service->confirm($order, $admin);
            $this->fail('Confirmation should require a revision');
        } catch (QuoteChanged $e) {
            $this->assertSame('price', $e->differences[0]['field']);
        }

        $revised = $service->reviseQuote($order->refresh(), $admin, 'Price increased');
        $this->assertSame(1, $revised->quote_revision);
        $this->assertSame(120000, $revised->grand_total_paise);
        $this->assertSame(24000, $revised->commission_estimate_paise);
        $this->assertSame(1, $revised->revisions()->count());
        Mail::assertQueued(AffiliateQuoteRevised::class);

        $confirmed = $service->confirm($revised, $admin);
        $this->assertSame(OrderStatus::Confirmed, $confirmed->status);
    }

    public function test_order_items_keep_purchase_snapshot_after_product_edits(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(100000, 5);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 1]]), $admin);
        $variant->product->update(['name' => 'Renamed', 'price_paise' => 1, 'warranty_terms' => 'changed']);
        $variant->update(['sku' => 'NEW-SKU']);

        $item = $order->items()->first();
        $this->assertNotSame('Renamed', $item->product_name);
        $this->assertSame(100000, $item->unit_price_paise);
        $this->assertNotSame('NEW-SKU', $item->sku);
    }
}
