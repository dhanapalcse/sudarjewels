<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Shared helpers for string-backed enums used in forms, filters and badges.
 */
trait HasLabel
{
    public function label(): string
    {
        return Str::of($this->value)->replace('_', ' ')->ucfirst()->toString();
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string|array|null
    {
        return method_exists($this, 'color') ? $this->color() : null;
    }

    /** @return array<string, string> value => label */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
