<?php

namespace App\Enums;

use App\Exceptions\InvalidTransition;

/**
 * Enums using this trait implement allowedTransitions(): array<self>.
 */
trait HasTransitions
{
    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    public function assertCanTransitionTo(self $to): void
    {
        if (! $this->canTransitionTo($to)) {
            throw new InvalidTransition(static::class, $this->value, $to->value);
        }
    }
}
