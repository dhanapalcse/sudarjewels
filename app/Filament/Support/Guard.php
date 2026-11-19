<?php

namespace App\Filament\Support;

use App\Domain\Orders\QuoteChanged;
use App\Exceptions\BusinessRuleException;
use App\Support\Money;
use Filament\Notifications\Notification;
use Filament\Support\Exceptions\Halt;

/** Runs a domain operation and turns rule violations into clear admin notifications. */
final class Guard
{
    public static function run(callable $operation, ?string $success = null, bool $halt = true): mixed
    {
        try {
            $result = $operation();
            if ($success) {
                Notification::make()->title($success)->success()->send();
            }

            return $result;
        } catch (QuoteChanged $e) {
            $lines = collect($e->differences)->map(fn ($d) => trim(($d['item'] ?? '').' '.$d['field'].': '.self::fmt($d['field'], $d['from']).' → '.self::fmt($d['field'], $d['to'])))->implode("\n");
            Notification::make()->title('Quote changed')->body($e->getMessage()."\n\n".$lines)->danger()->persistent()->send();
        } catch (BusinessRuleException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
        if ($halt) {
            throw new Halt;
        }

        return null;
    }

    private static function fmt(string $field, mixed $value): string
    {
        return match ($field) {
            'price' => Money::format((int) $value),
            'commission rate' => Money::formatRate((int) $value),
            default => is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value,
        };
    }
}
