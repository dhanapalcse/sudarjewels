<?php

namespace Tests\Feature\Http;

use App\Domain\Orders\FulfilmentService;
use App\Domain\Orders\OrderService;
use App\Domain\Warranty\ClaimService;
use App\Domain\Warranty\WarrantyService;
use App\Models\Affiliate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliate_pages_render(): void
    {
        $me = $this->affiliate();
        $admin = $this->admin();
        $variant = $this->variant(50000, 5);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 1]], $me), $admin);
        $this->actingAs($me->user);

        foreach (['affiliate.dashboard', 'affiliate.catalogue', 'affiliate.share', 'affiliate.customers', 'affiliate.earnings', 'affiliate.payouts', 'affiliate.warranty', 'affiliate.profile', 'affiliate.help', 'affiliate.orders.index', 'affiliate.orders.create'] as $route) {
            $this->assertSame(200, $this->get(route($route))->status(), $route);
        }
        $this->get(route('affiliate.orders.show', $order))->assertOk()->assertSee($order->order_number);
    }

    public function test_affiliate_payout_details_are_stored_encrypted_and_masked(): void
    {
        $me = $this->affiliate();
        $this->actingAs($me->user)->put(route('affiliate.profile.update'), [
            'name' => 'Asha', 'payout_method' => 'bank_transfer', 'payout_account_name' => 'Asha K', 'payout_account_number' => '123456789012', 'payout_ifsc' => 'HDFC0001234',
        ])->assertRedirect();
        $raw = \DB::table('affiliates')->where('id', $me->id)->value('payout_account_number');
        $this->assertStringNotContainsString('123456789012', (string) $raw);
        $this->get(route('affiliate.profile'))->assertDontSee('123456789012')->assertSee('••••9012');
    }

    public function test_customer_portal_pages_render(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(50000, 5);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 1]]), $admin);
        app(FulfilmentService::class)->handover($order, $order->units()->pluck('id')->all(), now(), $admin);
        $reg = app(WarrantyService::class)->register($order, 'A', '9876543210');
        $claim = app(ClaimService::class)->submit($order->customer, $order->units()->first(), 'The clasp broke on first use, please help.');
        $this->actingAs($order->customer, 'customer');

        foreach (['account.dashboard', 'account.orders', 'account.warranties', 'account.claims', 'account.claims.create', 'account.profile'] as $route) {
            $this->assertSame(200, $this->get(route($route))->status(), $route);
        }
        $this->get(route('account.orders.show', $order))->assertOk();
        $this->get(route('account.claims.show', $claim))->assertOk()->assertSee($claim->claim_number);
        $this->get(route('warranty.card', $reg))->assertOk()->assertSee($order->order_number);
    }

    public function test_affiliate_application_creates_pending_account(): void
    {
        $this->seedRoles();
        $this->post(route('affiliate.apply.submit'), [
            'name' => 'New Partner', 'email' => 'np@example.com', 'phone' => '9811122233',
            'password' => 'Partner12345', 'password_confirmation' => 'Partner12345', 'terms' => '1',
        ])->assertRedirect(route('affiliate.dashboard'));
        $aff = Affiliate::firstOrFail();
        $this->assertSame('pending', $aff->status->value);
        $this->get(route('affiliate.orders.create'))->assertRedirect(route('affiliate.dashboard'));
    }
}
