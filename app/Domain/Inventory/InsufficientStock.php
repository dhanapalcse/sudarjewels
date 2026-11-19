<?php

namespace App\Domain\Inventory;

use App\Exceptions\BusinessRuleException;

class InsufficientStock extends BusinessRuleException
{
    public function __construct(string $label, int $requested, int $available)
    {
        parent::__construct("Not enough stock for {$label}: requested {$requested}, available {$available}.");
    }
}
