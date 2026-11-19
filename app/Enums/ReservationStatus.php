<?php

namespace App\Enums;

enum ReservationStatus: string implements \Filament\Support\Contracts\HasLabel
{
    use HasLabel;

    case Active = 'active';
    case Fulfilled = 'fulfilled';
    case Released = 'released';
}
