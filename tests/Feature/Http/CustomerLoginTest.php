<?php

namespace Tests\Feature\Http;

use App\Domain\Notifications\Sms\TestSmsGateway;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_login_and_order_scoping(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(50000, 5);
        $mine = $this->order($admin, [[$variant, 1]], null, ['customer_phone' => '9876543210', 'status' => 'submitted']);
        $mine->update(['status' => 'submitted']);
        $theirs = $this->order($admin, [[$variant, 1]], null, ['customer_phone' => '9123456789']);
        $theirs->update(['status' => 'submitted']);

        $this->post(route('customer.login.send'), ['phone' => '98765 43210'])->assertRedirect(route('customer.login.verify'));
        $code = TestSmsGateway::peek('+919876543210');
        $this->post(route('customer.login.verify.submit'), ['code' => $code])->assertRedirect(route('account.dashboard'));
        $this->assertAuthenticatedAs(Customer::where('phone', '+919876543210')->first(), 'customer');

        $this->get(route('account.orders.show', $mine))->assertOk();
        $this->get(route('account.orders.show', $theirs))->assertNotFound();
    }

    public function test_unknown_number_gets_same_response_and_no_otp(): void
    {
        $this->post(route('customer.login.send'), ['phone' => '9000000001'])->assertRedirect(route('customer.login.verify'));
        $this->assertNull(TestSmsGateway::peek('+919000000001'));
        $this->post(route('customer.login.verify.submit'), ['code' => '123456'])->assertSessionHasErrors('code');
        $this->assertGuest('customer');
    }

    public function test_marketing_consent_is_separate_and_recorded(): void
    {
        $customer = Customer::factory()->create();
        $this->actingAs($customer, 'customer')->put(route('account.profile.update'), ['name' => 'New', 'marketing_opt_in' => '1'])->assertRedirect();
        $customer->refresh();
        $this->assertTrue($customer->marketing_opt_in);
        $this->assertNotNull($customer->marketing_consent_at);
    }
}
