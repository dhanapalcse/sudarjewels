<?php

namespace App\Domain\Warranty;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Coverage arithmetic, always in the business timezone (Asia/Kolkata):
 *  - starts at the delivery (or in-person handover) moment
 *  - ends at 23:59:59 local time on the same calendar day N months later
 *  - if that day does not exist (e.g. 30 Nov + 3 months), the last day of that month is used
 * Results are returned in UTC for storage.
 *
 * @return array{0: CarbonImmutable, 1: CarbonImmutable}
 */
final class WarrantyCalculator
{
    public static function coverage(CarbonInterface $start, int $months): array
    {
        $tz = config('sudar.business.timezone');
        $local = CarbonImmutable::instance($start)->setTimezone($tz);
        $end = $local->addMonthsNoOverflow($months)->setTime(23, 59, 59);

        return [$local->utc(), $end->utc()];
    }

    /** Coverage for a replacement, according to the configured policy. */
    public static function replacementCoverage(CarbonInterface $deliveredAt, int $months, string $policy, ?CarbonInterface $originalEnd): array
    {
        if ($policy === 'remaining' && $originalEnd) {
            $start = CarbonImmutable::instance($deliveredAt)->utc();
            $end = CarbonImmutable::instance($originalEnd)->utc();

            return [$start, $end->greaterThan($start) ? $end : $start];
        }

        return self::coverage($deliveredAt, $months);
    }
}
