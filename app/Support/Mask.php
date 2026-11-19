<?php

namespace App\Support;

final class Mask
{
    public static function phone(?string $phone): string
    {
        if (! $phone) {
            return '';
        }

        return str_repeat('•', max(0, strlen($phone) - 4)).substr($phone, -4);
    }

    public static function tail(?string $value, int $keep = 4): string
    {
        if (! $value) {
            return '';
        }

        return '••••'.substr($value, -$keep);
    }

    public static function upi(?string $upi): string
    {
        if (! $upi || ! str_contains($upi, '@')) {
            return self::tail($upi);
        }
        [$user, $bank] = explode('@', $upi, 2);

        return substr($user, 0, 2).'•••@'.$bank;
    }
}
