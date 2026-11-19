<?php

namespace App\Domain\Affiliates;

use App\Enums\AttributionSource;
use App\Models\Affiliate;
use Carbon\CarbonInterface;

final class Attribution
{
    public function __construct(
        public readonly Affiliate $affiliate,
        public readonly AttributionSource $source,
        public readonly CarbonInterface $capturedAt,
    ) {}
}
