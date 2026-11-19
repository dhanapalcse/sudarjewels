<?php

namespace Tests\Feature\Http;

use App\Domain\Orders\OrderService;
use App\Models\NotificationLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_confirmation_message_is_sent_once_and_logs_contain_no_otp_or_full_phone(): void
    {
        $admin = $this->admin();
        $variant = $this->variant(50000, 5);
        $order = $this->order($admin, [[$variant, 1]]);
        app(OrderService::class)->confirm($order, $admin);
        // Re-dispatch the same event (retry): still one log.
        app(\App\Domain\Notifications\Notifier::class)->orderConfirmed($order->refresh());

        $logs = NotificationLog::where('template', 'order_confirmed')->get();
        $this->assertCount(1, $logs);
        $this->assertStringNotContainsString('9876543210', $logs->first()->recipient_masked);
        $this->assertSame('test', $logs->first()->status);
    }

    public function test_test_driver_is_refused_in_production(): void
    {
        $this->app->forgetInstance(\App\Domain\Notifications\Sms\SmsGateway::class);
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        $this->app->make(\App\Domain\Notifications\Sms\SmsGateway::class);
    }
}
