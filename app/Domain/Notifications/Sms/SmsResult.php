<?php

namespace App\Domain\Notifications\Sms;

final class SmsResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?string $providerId = null,
        public readonly ?string $error = null,
        public readonly string $status = 'sent',
    ) {}
}
