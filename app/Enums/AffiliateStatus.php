<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum AffiliateStatus: string implements \Filament\Support\Contracts\HasLabel, HasColor
{
    use HasLabel;

    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Rejected = 'rejected';

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Suspended, self::Rejected => 'danger',
        };
    }
}
