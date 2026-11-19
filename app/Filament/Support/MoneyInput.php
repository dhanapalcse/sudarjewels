<?php

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Forms\Components\TextInput;

/** Rupee input bound to an integer-paise attribute. */
final class MoneyInput
{
    public static function make(string $name, string $label, bool $nullable = false): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('₹')
            ->numeric()
            ->minValue(0)
            ->step('0.01')
            ->formatStateUsing(fn ($state) => $state === null || $state === '' ? null : Money::toRupees((int) $state))
            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? ($nullable ? null : 0) : Money::toPaise((string) $state));
    }

    /** Percent input bound to basis points (20 % ↔ 2000). */
    public static function percent(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->suffix('%')
            ->numeric()
            ->minValue(0)
            ->maxValue(100)
            ->step('0.01')
            ->formatStateUsing(fn ($state) => $state === null || $state === '' ? null : rtrim(rtrim(number_format($state / 100, 2, '.', ''), '0'), '.'))
            ->dehydrateStateUsing(fn ($state) => $state === null || $state === '' ? null : (int) round(((float) $state) * 100));
    }
}
