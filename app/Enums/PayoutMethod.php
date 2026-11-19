<?php

namespace App\Enums;

enum PayoutMethod: string
{
    use HasLabel;

    case BankTransfer = 'bank_transfer';
    case Upi = 'upi';

    public function label(): string
    {
        return $this === self::Upi ? 'UPI' : 'Bank transfer';
    }
}
