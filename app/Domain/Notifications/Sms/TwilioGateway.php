<?php

namespace App\Domain\Notifications\Sms;

use App\Enums\OtpPurpose;
use Illuminate\Support\Facades\Http;

/** Twilio SMS adapter. Env: TWILIO_SID, TWILIO_TOKEN, TWILIO_FROM. */
class TwilioGateway implements SmsGateway
{
    public function __construct(private array $config) {}

    public function name(): string
    {
        return 'twilio';
    }

    public function sendOtp(string $phone, string $code, OtpPurpose $purpose): SmsResult
    {
        return $this->send($phone, "{$code} is your SUDAR JEWELS verification code. It expires in ".config('sudar.otp.ttl_minutes').' minutes. Do not share it.');
    }

    public function sendTransactional(string $phone, string $template, array $params): SmsResult
    {
        return $this->send($phone, Templates::render($template, $params));
    }

    private function send(string $phone, string $body): SmsResult
    {
        if (empty($this->config['sid']) || empty($this->config['token']) || empty($this->config['from'])) {
            return new SmsResult(false, null, 'Twilio is not configured', 'failed');
        }
        $response = Http::timeout(10)->asForm()->withBasicAuth($this->config['sid'], $this->config['token'])
            ->post("https://api.twilio.com/2010-04-01/Accounts/{$this->config['sid']}/Messages.json", [
                'To' => $phone, 'From' => $this->config['from'], 'Body' => $body,
            ]);

        return $response->successful()
            ? new SmsResult(true, $response->json('sid'))
            : new SmsResult(false, null, 'HTTP '.$response->status(), 'failed');
    }
}
