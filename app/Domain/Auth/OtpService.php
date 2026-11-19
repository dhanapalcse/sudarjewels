<?php

namespace App\Domain\Auth;

use App\Domain\Notifications\Sms\SmsGateway;
use App\Enums\OtpPurpose;
use App\Exceptions\BusinessRuleException;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;

/**
 * One-time passcodes. Codes are hashed at rest, never logged, expire, allow a limited
 * number of attempts, and have a resend cooldown plus an hourly send cap per phone.
 */
class OtpService
{
    public function __construct(private SmsGateway $sms) {}

    public function send(string $phone, OtpPurpose $purpose): void
    {
        $cfg = config('sudar.otp');
        $last = OtpCode::where('phone', $phone)->where('purpose', $purpose->value)->latest('id')->first();
        if ($last && $last->created_at->gt(now()->subSeconds($cfg['resend_cooldown_seconds']))) {
            $wait = $cfg['resend_cooldown_seconds'] - (int) $last->created_at->diffInSeconds(now());
            throw new BusinessRuleException('Please wait '.max(1, $wait).' seconds before requesting another code.');
        }
        $sentLastHour = OtpCode::where('phone', $phone)->where('purpose', $purpose->value)->where('created_at', '>', now()->subHour())->count();
        if ($sentLastHour >= $cfg['max_sends_per_hour']) {
            throw new BusinessRuleException('Too many codes requested. Please try again later.');
        }

        OtpCode::where('phone', $phone)->where('purpose', $purpose->value)->whereNull('consumed_at')->update(['expires_at' => now()]);

        $code = str_pad((string) random_int(0, 10 ** $cfg['length'] - 1), $cfg['length'], '0', STR_PAD_LEFT);
        OtpCode::create([
            'purpose' => $purpose,
            'phone' => $phone,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes($cfg['ttl_minutes']),
        ]);

        $this->sms->sendOtp($phone, $code, $purpose);
    }

    public function verify(string $phone, OtpPurpose $purpose, string $code): bool
    {
        $otp = OtpCode::where('phone', $phone)->where('purpose', $purpose->value)
            ->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first();
        if (! $otp || $otp->attempts >= config('sudar.otp.max_attempts')) {
            return false;
        }
        $otp->increment('attempts');
        if (! Hash::check(preg_replace('/\D/', '', $code), $otp->code_hash)) {
            return false;
        }

        return OtpCode::whereKey($otp->id)->whereNull('consumed_at')->update(['consumed_at' => now()]) === 1;
    }
}
