<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

/**
 * Payment status is derived from the payment and refund ledgers
 * (see PaymentService::refreshPaymentStatus); transitions are still validated.
 */
enum PaymentStatus: string implements \Filament\Support\Contracts\HasLabel, HasColor
{
    use HasLabel, HasTransitions;

    case Unpaid = 'unpaid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    /** @return array<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Unpaid => [self::PartiallyPaid, self::Paid],
            self::PartiallyPaid => [self::Unpaid, self::Paid, self::PartiallyRefunded, self::Refunded],
            self::Paid => [self::PartiallyPaid, self::PartiallyRefunded, self::Refunded],
            self::PartiallyRefunded => [self::Refunded, self::Paid],
            self::Refunded => [],
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Unpaid => 'gray',
            self::PartiallyPaid => 'warning',
            self::Paid => 'success',
            self::PartiallyRefunded => 'warning',
            self::Refunded => 'danger',
        };
    }
}
