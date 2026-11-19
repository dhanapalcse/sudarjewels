<?php

namespace App\Domain\Notifications\Sms;

use App\Enums\OtpPurpose;

interface SmsGateway
{
    public function name(): string;

    public function sendOtp(string $phone, string $code, OtpPurpose $purpose): SmsResult;

    /** @param array<string, string> $params */
    public function sendTransactional(string $phone, string $template, array $params): SmsResult;
}
