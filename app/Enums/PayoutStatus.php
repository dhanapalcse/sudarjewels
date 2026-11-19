<?php

namespace App\Enums;

enum PayoutStatus: string
{
    use HasLabel, HasTransitions;

    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Processing => [self::Completed, self::Failed, self::Cancelled],
            default => [],
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Processing => 'warning',
            self::Completed => 'success',
            self::Failed, self::Cancelled => 'danger',
        };
    }
}
