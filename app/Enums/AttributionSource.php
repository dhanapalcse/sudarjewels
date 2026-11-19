<?php

namespace App\Enums;

enum AttributionSource: string implements \Filament\Support\Contracts\HasLabel
{
    use HasLabel;

    case ReferralLink = 'referral_link';
    case ExplicitCode = 'explicit_code';
    case DirectAffiliate = 'direct_affiliate';
    case AdminAssigned = 'admin_assigned';
}
