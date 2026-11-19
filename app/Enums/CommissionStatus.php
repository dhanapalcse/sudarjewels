<?php

namespace App\Enums;

enum CommissionStatus: string
{
    use HasLabel;

    case Pending = 'pending';
    case Payable = 'payable';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Payable => 'info',
            self::Paid => 'success',
            self::Cancelled => 'gray',
        };
    }
}
