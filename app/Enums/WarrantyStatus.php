<?php

namespace App\Enums;

enum WarrantyStatus: string
{
    use HasLabel;

    case NotEligible = 'not_eligible';
    case Unregistered = 'unregistered';
    case Registered = 'registered';
    case Void = 'void';
}
