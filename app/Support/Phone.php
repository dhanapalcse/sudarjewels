<?php

namespace App\Support;

/**
 * Indian mobile normalisation to E.164 (+91XXXXXXXXXX).
 */
final class Phone
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $input);
        if ($digits === null || $digits === '') {
            return null;
        }
        if (strlen($digits) === 12 && str_starts_with($digits, '91')) {
            $digits = substr($digits, 2);
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }
        if (! preg_match('/^[6-9]\d{9}$/', $digits)) {
            return null;
        }

        return '+91'.$digits;
    }

    public static function isValid(?string $input): bool
    {
        return self::normalize($input) !== null;
    }

    /** +919876543210 -> 919876543210 (wa.me format) */
    public static function forWhatsApp(?string $input): ?string
    {
        $n = self::normalize($input);

        return $n ? ltrim($n, '+') : null;
    }

    public static function display(?string $e164): string
    {
        if (! $e164) {
            return '';
        }
        $local = substr($e164, -10);

        return '+91 '.substr($local, 0, 5).' '.substr($local, 5);
    }
}
