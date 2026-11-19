<?php

namespace App\Enums;

enum PaymentMethod: string
{
    use HasLabel;

    case Upi = 'upi';
    case BankTransfer = 'bank_transfer';
    case Cash = 'cash';
    case Card = 'card';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Upi => 'UPI',
            self::BankTransfer => 'Bank transfer',
            self::Cash => 'Cash',
            self::Card => 'Card',
            self::Other => 'Other',
        };
    }
}
