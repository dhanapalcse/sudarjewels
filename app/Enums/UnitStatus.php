<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

/** Lifecycle of a single purchased unit (one record per quantity of an order item). */
enum UnitStatus: string implements \Filament\Support\Contracts\HasLabel, HasColor
{
    use HasLabel;

    case Allocated = 'allocated';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Returned = 'returned';
    case Refunded = 'refunded';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Allocated, self::Dispatched, self::Delivered], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::Allocated => 'gray',
            self::Dispatched => 'warning',
            self::Delivered => 'success',
            self::Returned, self::Refunded, self::Cancelled => 'danger',
        };
    }
}
