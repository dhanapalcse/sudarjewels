<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum OrderStatus: string implements \Filament\Support\Contracts\HasLabel, HasColor
{
    use HasLabel, HasTransitions;

    case Draft = 'draft';
    case Submitted = 'submitted';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';
    case Completed = 'completed';

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Submitted, self::Confirmed, self::Cancelled],
            self::Submitted => [self::Draft, self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Completed, self::Cancelled],
            self::Cancelled, self::Completed => [],
        };
    }

    public function isEditableQuote(): bool
    {
        return in_array($this, [self::Draft, self::Submitted], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'info',
            self::Confirmed => 'primary',
            self::Cancelled => 'danger',
            self::Completed => 'success',
        };
    }
}
