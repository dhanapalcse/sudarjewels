<?php

namespace App\Enums;

enum ReservationStatus: string
{
    use HasLabel;

    case Active = 'active';
    case Fulfilled = 'fulfilled';
    case Released = 'released';
}
