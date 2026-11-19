<?php

namespace App\Enums;

enum ImageStatus: string
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
