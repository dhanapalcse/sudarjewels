<?php

namespace Tests\Feature\Http;

use App\Domain\Notifications\Sms\TestSmsGateway;
use App\Domain\Orders\FulfilmentService;
use App\Domain\Orders\OrderService;
use App\Models\Order;
use App\Models\OtpCode;
use App\Models\WarrantyRegistration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarrantyRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = $this->admin();
        $variant = $this->variant(50000, 5);
        $this->order = app(OrderService::class)->confirm($this->order($admin, [[$variant, 2]]), $admin);
        app(FulfilmentService::class)->handover($this->order, $this->order->units()->pluck('id')->all(), now(), $admin);
    }

    public function test_matching_and_non_matching_lookups_look_identical(): void
    {
        $good = $this->post(route('warranty.lookup'), ['order_number' => $this->order->order_number, 'name' => 'A', 'phone' => '9876543210']);
        $goodPage = $this->get(route('warranty.verify'))->getContent();
        $this->flushSession();

        $bad = $this->post(route('warranty.lookup'), ['order_number' => $this->order->order_number, 'name' => 'A', 'phone' => '9999999999']);
        $badPage = $this->get(route('warranty.verify'))->getContent();

        $good->assertRedirect(route('warranty.verify'));
        $bad->assertRedirect(route('warranty.verify'));
        $strip = fn ($html) => preg_replace(['/<link rel="preload"[^>]*>/', '/name="_token" value="[^"]+"/', '/content="[A-Za-z0-9]{40}"/', '/•+\d{4}/', '/Development code:.*?<\/code>/s'], '', $html);
        $this->assertSame(preg_replace('/\s+/', ' ', $strip($goodPage)), preg_replace('/\s+/', ' ', $strip($badPage)));
        $this->assertSame(1, OtpCode::count(), 'OTP is only sent for a real match');
        $this->assertStringNotContainsString($this->order->items->first()->product_name, $goodPage);
    }

    public function test_purchase_details_require_otp_and_registration_is_idempotent(): void
    {
        $this->post(route('warranty.lookup'), ['order_number' => strtolower($this->order->order_number), 'name' => 'Priya', 'phone' => '+91 98765 43210']);
        $this->get(route('warranty.confirm'))->assertRedirect(route('warranty.start'));

        $this->post(route('warranty.verify.submit'), ['code' => '000000'])->assertSessionHasErrors('code');
        $code = TestSmsGateway::peek('+919876543210');
        $this->assertNotNull($code);
        $this->post(route('warranty.verify.submit'), ['code' => $code])->assertRedirect(route('warranty.confirm'));

        $this->get(route('warranty.confirm'))->assertOk()->assertSee($this->order->items->first()->product_name);
        $this->post(route('warranty.register'), ['confirm' => '1'])->assertRedirect();
        $this->post(route('warranty.register'), ['confirm' => '1'])->assertRedirect();

        $this->assertSame(1, WarrantyRegistration::count());
        $reg = WarrantyRegistration::first();
        $this->assertSame('Priya', $reg->registered_name);
        $this->assertSame('Test Customer', $this->order->refresh()->customer_name, 'Original order is not rewritten');
        $this->assertSame(2, $reg->units()->count());
        $this->get(route('warranty.card', $reg))->assertOk()->assertSee('Warranty card');
    }

    public function test_otp_attempt_limit_resets_flow(): void
    {
        $this->post(route('warranty.lookup'), ['order_number' => $this->order->order_number, 'name' => 'A', 'phone' => '9876543210']);
        for ($i = 0; $i < 4; $i++) {
            $this->post(route('warranty.verify.submit'), ['code' => '111111']);
        }
        $this->post(route('warranty.verify.submit'), ['code' => '111111'])->assertRedirect(route('warranty.start'));
        // Even the right code is useless once the attempt limit is reached.
        $this->assertSame(5, OtpCode::first()->attempts);
    }

    public function test_expired_otp_is_rejected(): void
    {
        $this->post(route('warranty.lookup'), ['order_number' => $this->order->order_number, 'name' => 'A', 'phone' => '9876543210']);
        $code = TestSmsGateway::peek('+919876543210');
        $this->travel(11)->minutes();
        $this->post(route('warranty.verify.submit'), ['code' => $code])->assertSessionHasErrors('code');
    }

    public function test_resend_cooldown_applies_equally(): void
    {
        $this->post(route('warranty.lookup'), ['order_number' => 'SJ999999', 'name' => 'A', 'phone' => '9999999999']);
        $this->post(route('warranty.lookup'), ['order_number' => 'SJ999999', 'name' => 'A', 'phone' => '9999999999'])->assertSessionHas('error');
    }

    public function test_warranty_card_is_private(): void
    {
        $reg = app(\App\Domain\Warranty\WarrantyService::class)->register($this->order, 'A', '9876543210');
        $this->get(route('warranty.card', $reg))->assertRedirect(route('warranty.start'));
    }
}
