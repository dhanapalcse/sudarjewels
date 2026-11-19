<?php

namespace App\Enums;

enum StockMovementType: string implements \Filament\Support\Contracts\HasLabel
{
    use HasLabel;

    case Initial = 'initial';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case ReturnRestock = 'return_restock';
    case ReplacementIssue = 'replacement_issue';
}
