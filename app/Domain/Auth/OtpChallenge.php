<?php

namespace App\Domain\Auth;

use App\Enums\OtpPurpose;
use App\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\Cache;

/**
 * Starts an OTP challenge with identical behaviour whether or not the phone/order matched,
 * so responses and rate limits never reveal whether purchase details exist.
 */
class OtpChallenge
{
    public function __construct(private OtpService $otp) {}

    public function begin(string $phone, OtpPurpose $purpose, bool $matched): void
    {
        $cfg = config('sudar.otp');
        $key = 'otp-challenge:'.$purpose->value.':'.hash('sha256', $phone);

        if (Cache::has($key.':cooldown')) {
            throw new BusinessRuleException('Please wait a minute before requesting another code.');
        }
        $count = (int) Cache::get($key.':hour', 0);
        if ($count >= $cfg['max_sends_per_hour']) {
            throw new BusinessRuleException('Too many codes requested for this number. Please try again later.');
        }
        Cache::put($key.':cooldown', 1, now()->addSeconds($cfg['resend_cooldown_seconds']));
        Cache::put($key.':hour', $count + 1, now()->addHour());

        if ($matched) {
            try {
                $this->otp->send($phone, $purpose);
            } catch (BusinessRuleException) {
                // Same outward behaviour as an unmatched request.
            }
        }
    }
}
