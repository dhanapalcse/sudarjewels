<?php

namespace App\Support;

/**
 * Money is always integer paise. Rates are integer basis points (1 bp = 0.01 %).
 * Rounding: half-up to the nearest paisa (amounts here are never negative).
 */
final class Money
{
    public static function format(?int $paise, bool $showZeroDecimals = false): string
    {
        $paise ??= 0;
        $negative = $paise < 0;
        $abs = abs($paise);
        $rupees = intdiv($abs, 100);
        $fraction = $abs % 100;

        $formatted = self::indianGrouping($rupees);
        if ($fraction > 0 || $showZeroDecimals) {
            $formatted .= '.'.str_pad((string) $fraction, 2, '0', STR_PAD_LEFT);
        }

        return ($negative ? '−' : '').'₹'.$formatted;
    }

    /** 1234567 -> 12,34,567 */
    public static function indianGrouping(int $value): string
    {
        $s = (string) $value;
        if (strlen($s) <= 3) {
            return $s;
        }
        $last3 = substr($s, -3);
        $rest = substr($s, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);

        return $rest.','.$last3;
    }

    /** "1,299.50" / "1299.5" / 1299 -> 129950 paise */
    public static function toPaise(string|int|float|null $rupees): int
    {
        if ($rupees === null || $rupees === '') {
            return 0;
        }
        $clean = str_replace([',', '₹', ' '], '', (string) $rupees);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $clean)) {
            throw new \InvalidArgumentException('Invalid amount.');
        }
        [$whole, $frac] = array_pad(explode('.', $clean), 2, '0');

        return (int) $whole * 100 + (int) str_pad($frac, 2, '0');
    }

    public static function toRupees(?int $paise): ?string
    {
        if ($paise === null) {
            return null;
        }

        return number_format($paise / 100, 2, '.', '');
    }

    /** Percentage of an amount using basis points, half-up. */
    public static function applyRate(int $amountPaise, int $rateBp): int
    {
        if ($amountPaise <= 0 || $rateBp <= 0) {
            return 0;
        }

        return intdiv($amountPaise * $rateBp + 5000, 10000);
    }

    /** Proportional share, half-up: amount * numerator / denominator. */
    public static function proportion(int $amountPaise, int $numerator, int $denominator): int
    {
        if ($denominator <= 0 || $numerator <= 0 || $amountPaise === 0) {
            return 0;
        }
        $sign = $amountPaise < 0 ? -1 : 1;

        return $sign * intdiv(abs($amountPaise) * $numerator * 2 + $denominator, $denominator * 2);
    }

    /**
     * Split $total across $weights proportionally using the largest-remainder method,
     * so the parts always sum exactly to $total. Ties go to the earlier weight.
     *
     * @param  array<int|string, int>  $weights
     * @return array<int|string, int>
     */
    public static function allocate(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        $result = array_fill_keys(array_keys($weights), 0);
        if ($total <= 0 || $sum <= 0) {
            return $result;
        }
        $remainders = [];
        $allocated = 0;
        foreach ($weights as $key => $weight) {
            $exact = $total * $weight;
            $result[$key] = intdiv($exact, $sum);
            $remainders[$key] = $exact % $sum;
            $allocated += $result[$key];
        }
        $left = $total - $allocated;
        $keys = array_keys($remainders);
        usort($keys, fn ($a, $b) => [$remainders[$b], array_search($a, array_keys($weights))] <=> [$remainders[$a], array_search($b, array_keys($weights))]);
        foreach ($keys as $key) {
            if ($left <= 0) {
                break;
            }
            $result[$key]++;
            $left--;
        }

        return $result;
    }

    public static function formatRate(?int $bp): string
    {
        if ($bp === null) {
            return '—';
        }

        return rtrim(rtrim(number_format($bp / 100, 2, '.', ''), '0'), '.').'%';
    }
}
