<?php

namespace App\Exceptions;

class InvalidTransition extends BusinessRuleException
{
    public function __construct(string $enum, string $from, string $to)
    {
        $name = class_basename($enum);
        parent::__construct("{$name} cannot change from \"{$from}\" to \"{$to}\".");
    }
}
