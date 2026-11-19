<?php

namespace App\Enums;

enum PayoutMethod: string implements \Filament\Support\Contracts\HasLabel
{
    use HasLabel;

    case BankTransfer = 'bank_transfer';
    case Upi = 'upi';

    public function label(): string
    {
        return $this === self::Upi ? 'UPI' : 'Bank transfer';
    }
}
