<?php

/*
|--------------------------------------------------------------------------
| SUDAR JEWELS business configuration
|--------------------------------------------------------------------------
|
| Values here are *defaults*. Anything a business user may need to change
| at runtime (contact details, commission rate, return window, policies)
| is stored in the `settings` table and edited from Admin → Business
| settings. Secrets (API keys, service-account credentials) stay in the
| environment and are never stored in the database or rendered in markup.
|
*/

return [

    'business' => [
        'name' => 'SUDAR JEWELS',
        'domain' => 'sudarjewels.com',
        'currency' => 'INR',
        'timezone' => 'Asia/Kolkata',
        'country_code' => '91',
    ],

    'order_number_prefix' => env('ORDER_NUMBER_PREFIX', 'SJ'),

    'commission' => [
        // Basis points: 2000 = 20.00 %
        'default_rate_bp' => 2000,
    ],

    'referral' => [
        'query_parameter' => 'ref',
        'cookie' => 'sj_ref',
        'window_days' => 30,
    ],

    'warranty' => [
        'months' => 3,
        // 'remaining' = replacement inherits the original unit's remaining coverage
        // 'fresh'     = replacement starts a new full coverage period on delivery
        'replacement_policy' => 'remaining',
    ],

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 10,
        'max_attempts' => 5,
        'resend_cooldown_seconds' => 60,
        'max_sends_per_hour' => 5,
        'lookup_attempts_per_minute' => 6,
    ],

    'inventory' => [
        'low_stock_threshold' => 3,
    ],

    'images' => [
        'disk' => env('MEDIA_DISK', 'media'),
        'cdn_url' => env('MEDIA_CDN_URL'),
        'widths' => [320, 640, 960, 1400],
        'quality' => 80,
        'min_width' => 600,
        'min_height' => 600,
        'max_width' => 8000,
        'max_height' => 8000,
        'max_bytes' => 15 * 1024 * 1024,
        'allowed_mimes' => ['image/jpeg', 'image/png', 'image/webp'],
    ],

    'google_drive' => [
        // Absolute path to a service-account JSON key file kept outside the web root.
        'service_account_json' => env('GOOGLE_DRIVE_SERVICE_ACCOUNT_JSON'),
        'enabled' => (bool) env('GOOGLE_DRIVE_IMPORT_ENABLED', false),
    ],

    'notifications' => [
        // test | msg91 | twilio
        'sms_driver' => env('SMS_DRIVER', 'test'),
        'msg91' => [
            'auth_key' => env('MSG91_AUTH_KEY'),
            'sender_id' => env('MSG91_SENDER_ID'),
            'otp_template_id' => env('MSG91_OTP_TEMPLATE_ID'),
            'transactional_template_id' => env('MSG91_TRANSACTIONAL_TEMPLATE_ID'),
        ],
        'twilio' => [
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],

    'analytics' => [
        'ga4_measurement_id' => env('GA4_MEASUREMENT_ID'),
        // Measurement Protocol secret for server-side purchase events (optional)
        'ga4_api_secret' => env('GA4_API_SECRET'),
    ],

];
