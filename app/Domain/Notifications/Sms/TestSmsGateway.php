<?php

namespace App\Domain\Notifications\Sms;

use App\Enums\OtpPurpose;
use App\Models\NotificationLog;
use App\Support\Mask;
use Illuminate\Support\Facades\Cache;

/**
 * TEST DRIVER — local development only. Nothing is delivered.
 * The latest OTP is held briefly in cache so the local UI can display it in a clearly
 * labelled developer banner; it is never written to logs or the database.
 */
class TestSmsGateway implements SmsGateway
{
    public function name(): string
    {
        return 'test';
    }

    public function sendOtp(string $phone, string $code, OtpPurpose $purpose): SmsResult
    {
        Cache::put(self::cacheKey($phone), $code, now()->addMinutes(10));
        $this->log($phone, 'otp.'.$purpose->value);

        return new SmsResult(true, null, null, 'test');
    }

    public function sendTransactional(string $phone, string $template, array $params): SmsResult
    {
        $this->log($phone, $template);

        return new SmsResult(true, null, null, 'test');
    }

    public static function cacheKey(string $phone): string
    {
        return 'test-sms-otp:'.hash('sha256', $phone);
    }

    public static function peek(string $phone): ?string
    {
        return app()->environment('local', 'testing') ? Cache::get(self::cacheKey($phone)) : null;
    }

    private function log(string $phone, string $template): void
    {
        NotificationLog::create([
            'channel' => 'sms', 'driver' => 'test', 'template' => $template,
            'recipient_masked' => Mask::phone($phone), 'recipient_hash' => hash('sha256', $phone),
            'status' => 'test', 'sent_at' => now(),
        ]);
    }
}
