<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;

enum ImageStatus: string implements \Filament\Support\Contracts\HasLabel, HasColor
{
    use HasLabel;

    case Queued = 'queued';
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'gray',
            self::Processing => 'warning',
            self::Ready => 'success',
            self::Failed => 'danger',
        };
    }
}
