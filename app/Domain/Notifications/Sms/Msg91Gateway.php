<?php

namespace App\Domain\Notifications\Sms;

use App\Enums\OtpPurpose;
use Illuminate\Support\Facades\Http;

/**
 * MSG91 (India) adapter. Requires DLT-approved templates configured in MSG91.
 * Env: MSG91_AUTH_KEY, MSG91_SENDER_ID, MSG91_OTP_TEMPLATE_ID, MSG91_TRANSACTIONAL_TEMPLATE_ID.
 */
class Msg91Gateway implements SmsGateway
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'msg91';
    }

    public function sendOtp(string $phone, string $code, OtpPurpose $purpose): SmsResult
    {
        return $this->flow($this->config['otp_template_id'] ?? null, $phone, ['otp' => $code]);
    }

    public function sendTransactional(string $phone, string $template, array $params): SmsResult
    {
        return $this->flow($this->config['transactional_template_id'] ?? null, $phone, $params + ['template' => $template]);
    }

    private function flow(?string $templateId, string $phone, array $vars): SmsResult
    {
        if (empty($this->config['auth_key']) || ! $templateId) {
            return new SmsResult(false, null, 'MSG91 is not configured', 'failed');
        }
        $response = Http::timeout(10)->withHeaders(['authkey' => $this->config['auth_key']])
            ->post('https://control.msg91.com/api/v5/flow/', [
                'template_id' => $templateId,
                'short_url' => '0',
                'recipients' => [array_merge(['mobiles' => ltrim($phone, '+')], $vars)],
            ]);
        if ($response->successful()) {
            return new SmsResult(true, (string) ($response->json('message') ?? ''));
        }

        return new SmsResult(false, null, 'HTTP '.$response->status(), 'failed');
    }
}
