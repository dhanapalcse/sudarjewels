<?php

namespace App\Filament\Resources\Payouts;

use App\Filament\Resources\Affiliates\AffiliateResource;

final class AffiliateResourceProxy
{
    public static function canSee(): bool
    {
        return AffiliateResource::canSeePayoutDetails();
    }
}
