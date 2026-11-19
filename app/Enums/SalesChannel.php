<?php

namespace App\Enums;

enum SalesChannel: string
{
    use HasLabel;

    case WhatsApp = 'whatsapp';
    case InPerson = 'in_person';
    case Affiliate = 'affiliate';
    case Phone = 'phone';
    case Social = 'social';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::WhatsApp => 'WhatsApp',
            self::InPerson => 'In person',
            self::Affiliate => 'Affiliate',
            self::Phone => 'Phone',
            self::Social => 'Social media',
            self::Website => 'Website',
        };
    }
}
