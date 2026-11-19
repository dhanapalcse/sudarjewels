<?php

namespace Tests\Feature\Domain;

use App\Domain\Catalogue\CommissionRateResolver;
use App\Models\Product;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommissionRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_unset_override_inherits_global_rate(): void
    {
        $product = Product::factory()->create(['commission_rate_bp' => null]);
        $this->assertSame(2000, app(CommissionRateResolver::class)->forProduct($product)['rate_bp']);

        app(Settings::class)->set(['commission.default_rate_bp' => 1500]);
        $this->assertSame(1500, app(CommissionRateResolver::class)->forProduct($product)['rate_bp']);
    }

    public function test_explicit_zero_override_stays_zero(): void
    {
        $product = Product::factory()->create(['commission_rate_bp' => 0]);
        $rate = app(CommissionRateResolver::class)->forProduct($product);
        $this->assertTrue($rate['enabled']);
        $this->assertSame(0, $rate['rate_bp']);
        $this->assertSame('product', $rate['source']);
    }

    public function test_disabled_commission_overrides_global_default_and_override(): void
    {
        $product = Product::factory()->create(['commission_enabled' => false, 'commission_rate_bp' => 3000]);
        $rate = app(CommissionRateResolver::class)->forProduct($product);
        $this->assertFalse($rate['enabled']);
        $this->assertSame(0, $rate['rate_bp']);
    }

    public function test_order_commission_uses_rules_and_excludes_shipping_and_tax(): void
    {
        app(Settings::class)->set(['tax.gst_rate_bp' => 300, 'tax.prices_include_tax' => true]);
        $admin = $this->admin();
        $affiliate = $this->affiliate();
        $inherit = $this->variant(103000, 5);
        $zero = $this->variant(50000, 5, ['commission_rate_bp' => 0]);
        $off = $this->variant(50000, 5, ['commission_enabled' => false]);

        $order = $this->order($admin, [[$inherit, 1], [$zero, 1], [$off, 1]], $affiliate, ['delivery_required' => true, 'address_line1' => 'x', 'city' => 'Chennai', 'state' => 'TN', 'postal_code' => '600001', 'shipping_paise' => 9900]);
        $items = $order->items->keyBy('product_variant_id');

        // 1,030.00 incl. 3% GST => 1,000.00 net => 20% = 200.00
        $this->assertSame(100000, $items[$inherit->id]->commission_eligible_paise);
        $this->assertSame(20000, $items[$inherit->id]->commission_paise);
        $this->assertSame(0, $items[$zero->id]->commission_paise);
        $this->assertSame(0, $items[$off->id]->commission_paise);
        $this->assertSame(20000, $order->commission_estimate_paise);
        $this->assertSame(9900, $order->shipping_paise);
    }

    public function test_order_discount_is_allocated_before_commission(): void
    {
        $admin = $this->admin();
        $affiliate = $this->affiliate();
        $a = $this->variant(30000, 5);
        $b = $this->variant(10000, 5);
        $order = $this->order($admin, [[$a, 1], [$b, 1]], $affiliate, ['order_discount_paise' => 4000]);
        $items = $order->items->keyBy('product_variant_id');
        $this->assertSame(3000, $items[$a->id]->allocated_discount_paise);
        $this->assertSame(1000, $items[$b->id]->allocated_discount_paise);
        $this->assertSame(5400, $items[$a->id]->commission_paise); // 20% of 27,000
        $this->assertSame(1800, $items[$b->id]->commission_paise);
        $this->assertSame(36000, $order->grand_total_paise);
    }
}
