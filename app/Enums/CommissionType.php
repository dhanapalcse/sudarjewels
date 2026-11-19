<?php

namespace App\Enums;

enum CommissionType: string implements \Filament\Support\Contracts\HasLabel
{
    use HasLabel;

    /** Positive amount earned on a confirmed order item. */
    case Earning = 'earning';
    /** Negative amount offsetting an earning that has not been paid out. */
    case Reversal = 'reversal';
    /** Correction against future earnings, e.g. a refund after the earning was paid. */
    case Adjustment = 'adjustment';
}
