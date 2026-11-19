<?php

namespace Tests\Feature\Http;

use App\Domain\Orders\OrderService;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AffiliateOrderTest extends TestCase
{
    use RefreshDatabase;

    private function payload(int $variantId, array $extra = []): array
    {
        return array_merge([
            'idempotency_key' => (string) Str::uuid(),
            'lines' => [['variant_id' => $variantId, 'quantity' => 2]],
            'customer_name' => 'Meena',
            'customer_phone' => '98765 11111',
            'delivery_required' => '1',
            'address_line1' => '12 Lake Road', 'city' => 'Chennai', 'state' => 'Tamil Nadu', 'postal_code' => '600020',
        ], $extra);
    }

    public function test_tampered_affiliate_id_and_prices_are_ignored(): void
    {
        $me = $this->affiliate();
        $other = $this->affiliate();
        $variant = $this->variant(100000, 5);

        $this->actingAs($me->user)->post(route('affiliate.orders.store'), $this->payload($variant->id, [
            'affiliate_id' => $other->id,
            'lines' => [['variant_id' => $variant->id, 'quantity' => 2, 'unit_price_paise' => 1, 'item_discount_paise' => 99999]],
            'grand_total_paise' => 100, 'commission_rate_bp' => 9000, 'shipping_paise' => 0, 'order_discount_paise' => 50000,
        ]))->assertRedirect();

        $order = Order::firstOrFail();
        $this->assertSame($me->id, $order->affiliate_id);
        $this->assertSame($me->user->id, $order->created_by_id);
        $this->assertSame(OrderStatus::Submitted, $order->status);
        $this->assertSame(200000, $order->subtotal_paise);
        $this->assertSame(0, $order->discount_total_paise);
        $this->assertSame(40000, $order->commission_estimate_paise);
        $this->assertSame('+919876511111', $order->customer_phone);
        $this->assertSame('affiliate', $order->channel->value);
    }

    public function test_duplicate_submission_creates_one_order(): void
    {
        $me = $this->affiliate();
        $variant = $this->variant(50000, 5);
        $payload = $this->payload($variant->id);

        $this->actingAs($me->user)->post(route('affiliate.orders.store'), $payload);
        $this->actingAs($me->user)->post(route('affiliate.orders.store'), $payload);

        $this->assertSame(1, Order::count());
    }

    public function test_pending_affiliate_cannot_create_orders(): void
    {
        $pending = $this->affiliate(['status' => 'pending']);
        $variant = $this->variant(50000, 5);

        $this->actingAs($pending->user)->get(route('affiliate.orders.create'))->assertRedirect(route('affiliate.dashboard'));
        $this->actingAs($pending->user)->post(route('affiliate.orders.store'), $this->payload($variant->id))->assertRedirect(route('affiliate.dashboard'));
        $this->assertSame(0, Order::count());
    }

    public function test_affiliates_cannot_see_or_edit_other_affiliates_orders_or_customers(): void
    {
        $me = $this->affiliate();
        $other = $this->affiliate();
        $variant = $this->variant(50000, 5);
        $this->actingAs($other->user)->post(route('affiliate.orders.store'), $this->payload($variant->id, ['customer_name' => 'Secret Customer']));
        $order = Order::firstOrFail();

        $this->actingAs($me->user)->get(route('affiliate.orders.show', $order))->assertForbidden();
        $this->actingAs($me->user)->get(route('affiliate.orders.edit', $order))->assertForbidden();
        $this->actingAs($me->user)->put(route('affiliate.orders.update', $order), $this->payload($variant->id))->assertForbidden();
        $this->actingAs($me->user)->get(route('affiliate.customers'))->assertOk()->assertDontSee('Secret Customer');
        $this->actingAs($me->user)->get(route('affiliate.orders.index'))->assertDontSee($order->order_number);
    }

    public function test_existing_customer_match_does_not_reveal_other_purchase_history(): void
    {
        $admin = $this->admin();
        $me = $this->affiliate();
        $variant = $this->variant(50000, 5);
        // Earlier direct order by the same phone, with a different name and address.
        $this->order($admin, [[$variant, 1]], null, ['customer_name' => 'Private Name', 'customer_phone' => '9876511111', 'delivery_required' => true, 'address_line1' => 'Hidden Street 9', 'city' => 'Madurai', 'state' => 'TN', 'postal_code' => '625001']);

        $this->actingAs($me->user)->post(route('affiliate.orders.store'), $this->payload($variant->id));
        $mine = Order::where('affiliate_id', $me->id)->firstOrFail();
        $this->assertNotNull($mine->customer_id); // internally associated

        $this->actingAs($me->user)->get(route('affiliate.customers'))->assertDontSee('Private Name')->assertDontSee('Hidden Street');
        $this->actingAs($me->user)->get(route('affiliate.orders.show', $mine))->assertOk()->assertDontSee('Hidden Street')->assertDontSee('Private Name');
    }

    public function test_confirmed_orders_cannot_be_edited_by_affiliate(): void
    {
        $admin = $this->admin();
        $me = $this->affiliate();
        $variant = $this->variant(50000, 5);
        $this->actingAs($me->user)->post(route('affiliate.orders.store'), $this->payload($variant->id));
        $order = Order::firstOrFail();
        app(OrderService::class)->confirm($order, $admin);

        $this->actingAs($me->user)->put(route('affiliate.orders.update', $order), $this->payload($variant->id))->assertForbidden();
        $this->actingAs($me->user)->post(route('affiliate.orders.change-request', $order), ['message' => 'Please change the address'])->assertRedirect();
        $this->assertSame(1, $order->changeRequests()->count());
    }

    public function test_quote_endpoint_returns_server_totals(): void
    {
        $me = $this->affiliate();
        $variant = $this->variant(100000, 5, ['commission_rate_bp' => 0]);
        $this->actingAs($me->user)->postJson(route('affiliate.orders.quote'), ['lines' => [['variant_id' => $variant->id, 'quantity' => 1]], 'delivery_required' => false])
            ->assertOk()->assertJsonPath('totals.grand_total_paise', 100000)->assertJsonPath('totals.commission_estimate_paise', 0);
    }

    public function test_customer_cannot_use_affiliate_area_and_staff_affiliate_guard(): void
    {
        $this->get(route('affiliate.dashboard'))->assertRedirect(route('affiliate.login'));
        $staff = $this->staff('sales');
        $this->actingAs($staff)->get(route('affiliate.dashboard'))->assertForbidden();
    }
}
