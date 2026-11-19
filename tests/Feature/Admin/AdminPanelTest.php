<?php

namespace Tests\Feature\Admin;

use App\Domain\Commissions\CommissionService;
use App\Domain\Orders\FulfilmentService;
use App\Domain\Orders\OrderService;
use App\Domain\Orders\PaymentService;
use App\Domain\Warranty\ClaimService;
use App\Domain\Warranty\WarrantyService;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Payouts\Pages\CreatePayout;
use App\Models\AffiliatePayout;
use App\Models\CommissionEntry;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private function withMfa(User $user): User
    {
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');

        return $user;
    }

    private function seedData(): array
    {
        $admin = $this->withMfa($this->admin());
        $affiliate = $this->affiliate();
        $variant = $this->variant(100000, 10);
        $order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 1]], $affiliate), $admin);
        app(PaymentService::class)->recordPayment($order, $order->grand_total_paise, PaymentMethod::Upi, 'R', now(), $admin);
        app(FulfilmentService::class)->handover($order, $order->units()->pluck('id')->all(), now()->subDays(10), $admin);
        $reg = app(WarrantyService::class)->register($order, 'A', '9876543210');
        $claim = app(ClaimService::class)->submit($order->customer, $order->units()->first(), 'Plating came off after a few days.');
        app(Settings::class)->set(['commission.return_window_days' => 7]);
        app(CommissionService::class)->markEligibleAsPayable();

        return compact('admin', 'affiliate', 'variant', 'order', 'reg', 'claim');
    }

    public function test_every_admin_page_renders_for_super_admin(): void
    {
        $d = $this->seedData();
        $this->actingAs($d['admin']);
        $urls = [
            '/admin', '/admin/orders', '/admin/orders/create', '/admin/orders/'.$d['order']->id,
            '/admin/products', '/admin/products/create', '/admin/products/'.$d['variant']->product->slug.'/edit',
            '/admin/categories', '/admin/inventory', '/admin/stock-movements', '/admin/payments', '/admin/refunds',
            '/admin/customers', '/admin/customers/'.$d['order']->customer_id.'/edit',
            '/admin/affiliates', '/admin/affiliates/create', '/admin/affiliates/'.$d['affiliate']->id.'/edit',
            '/admin/commission-entries', '/admin/payouts', '/admin/payouts/create',
            '/admin/warranty-registrations', '/admin/warranty-registrations/'.$d['reg']->uuid,
            '/admin/claims', '/admin/claims/'.$d['claim']->id,
            '/admin/pages', '/admin/staff', '/admin/roles', '/admin/audit-logs', '/admin/settings', '/admin/reports',
        ];
        foreach ($urls as $url) {
            $this->assertSame(200, $this->get($url)->status(), "GET {$url}");
        }
    }

    public function test_staff_permissions_scope_the_panel(): void
    {
        $this->seedData();
        $packing = $this->withMfa($this->staff('fulfilment'));
        $this->actingAs($packing);
        $this->get('/admin/orders')->assertOk();
        $this->get('/admin/payouts')->assertForbidden();
        $this->get('/admin/settings')->assertForbidden();
        $this->get('/admin/staff')->assertForbidden();
        $this->get('/admin/affiliates')->assertForbidden();

        $affiliate = $this->affiliate();
        $this->actingAs($affiliate->user)->get('/admin')->assertForbidden();
    }

    public function test_admin_must_set_up_two_factor(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->get('/admin/orders')->assertRedirect();
    }

    public function test_confirm_action_runs_through_domain_service(): void
    {
        $admin = $this->withMfa($this->admin());
        $variant = $this->variant(50000, 3);
        $order = $this->order($admin, [[$variant, 2]]);
        $this->actingAs($admin);

        Livewire::test(ViewOrder::class, ['record' => $order->getRouteKey()])->callAction('confirm')->assertHasNoActionErrors();

        $this->assertSame(OrderStatus::Confirmed, $order->refresh()->status);
        $this->assertSame(2, $variant->refresh()->stock_reserved);
    }

    public function test_payout_creation_from_admin(): void
    {
        $d = $this->seedData();
        $this->actingAs($d['admin']);
        $ids = CommissionEntry::where('status', 'payable')->pluck('id')->map(fn ($id) => (string) $id)->all();

        Livewire::test(CreatePayout::class)
            ->fillForm(['affiliate_id' => $d['affiliate']->id, 'entry_ids' => $ids])
            ->call('create')->assertHasNoFormErrors();

        $this->assertSame(1, AffiliatePayout::count());
        $this->assertSame(20000, AffiliatePayout::first()->amount_paise);
    }
}
