<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

/**
 * Order-level fulfilment status, derived from the order's purchased units
 * except for "processing", which staff set manually while packing.
 */
enum FulfilmentStatus: string implements \Filament\Support\Contracts\HasLabel, HasColor
{
    use HasLabel, HasTransitions;

    case Unfulfilled = 'unfulfilled';
    case Processing = 'processing';
    case Dispatched = 'dispatched';
    case Delivered = 'delivered';
    case Returned = 'returned';

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Unfulfilled => [self::Processing, self::Dispatched, self::Delivered],
            self::Processing => [self::Unfulfilled, self::Dispatched, self::Delivered],
            self::Dispatched => [self::Delivered, self::Returned],
            self::Delivered => [self::Dispatched, self::Returned],
            self::Returned => [],
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unfulfilled => 'gray',
            self::Processing => 'info',
            self::Dispatched => 'warning',
            self::Delivered => 'success',
            self::Returned => 'danger',
        };
    }
}
