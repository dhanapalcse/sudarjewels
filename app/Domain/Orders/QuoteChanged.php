<?php

namespace App\Domain\Orders;

use App\Exceptions\BusinessRuleException;

class QuoteChanged extends BusinessRuleException
{
    /** @param array<int, array<string, mixed>> $differences */
    public function __construct(public readonly array $differences)
    {
        parent::__construct('Prices, commission rates or availability changed since this order was quoted. Review and apply a quote revision before confirming.');
    }
}
