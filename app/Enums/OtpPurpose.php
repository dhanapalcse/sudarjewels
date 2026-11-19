<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case CustomerLogin = 'customer_login';
    case WarrantyLookup = 'warranty_lookup';
}
