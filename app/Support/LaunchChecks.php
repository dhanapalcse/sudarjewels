<?php

namespace App\Support;

use App\Domain\Catalogue\Images\GoogleDriveClient;
use App\Models\Page;
use App\Models\Product;

/** Configuration the business must complete before public launch. */
final class LaunchChecks
{
    /** @return array<int, array{key:string, label:string, ok:bool, help:string}> */
    public static function run(): array
    {
        $s = app(Settings::class);
        $wa = $s->get('business.whatsapp_number');
        $driveConfigured = app(GoogleDriveClient::class)->isConfigured();

        return [
            ['key' => 'whatsapp', 'label' => 'WhatsApp number', 'ok' => filled($wa) && ! str_contains((string) $wa, '9000000000'), 'help' => 'Set the real business WhatsApp number in Business settings.'],
            ['key' => 'contact', 'label' => 'Contact details', 'ok' => filled($s->get('business.email')) && ! str_ends_with((string) $s->get('business.email'), '.test'), 'help' => 'Add the real business email (and phone/address) in Business settings.'],
            ['key' => 'return_window', 'label' => 'Commission return window', 'ok' => $s->get('commission.return_window_days') !== null, 'help' => 'Set the return window (days) so commission can become payable automatically.'],
            ['key' => 'policies', 'label' => 'Policy pages', 'ok' => ! Page::where('needs_review', true)->exists(), 'help' => 'Replace placeholder text on: '.Page::where('needs_review', true)->pluck('title')->implode(', ')],
            ['key' => 'samples', 'label' => 'Sample products', 'ok' => ! Product::where('is_sample', true)->where('status', 'published')->exists(), 'help' => 'Unpublish or replace sample products and images.'],
            ['key' => 'sms', 'label' => 'SMS provider', 'ok' => config('sudar.notifications.sms_driver') !== 'test', 'help' => 'Set SMS_DRIVER=msg91 or twilio with credentials in .env (test driver sends nothing).'],
            ['key' => 'hero', 'label' => 'Homepage hero image', 'ok' => ! ($s->get('home.hero_image')['is_sample'] ?? true), 'help' => 'Upload a real hero photograph in Business settings → Homepage.'],
            ['key' => 'drive', 'label' => 'Google Drive import (optional)', 'ok' => $driveConfigured || ! config('sudar.google_drive.enabled'), 'help' => 'GOOGLE_DRIVE_IMPORT_ENABLED is on but the service-account key file is missing or unreadable.'],
            ['key' => 'debug', 'label' => 'Debug mode', 'ok' => ! (app()->environment('production') && config('app.debug')), 'help' => 'APP_DEBUG must be false in production.'],
            ['key' => 'mfa', 'label' => 'Admin two-factor', 'ok' => (bool) config('sudar.admin_require_2fa'), 'help' => 'ADMIN_REQUIRE_2FA should be true.'],
        ];
    }
}
