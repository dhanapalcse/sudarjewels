<?php

namespace Database\Seeders;

use App\Domain\Orders\FulfilmentService;
use App\Domain\Orders\OrderService;
use App\Domain\Orders\PaymentService;
use App\Domain\Warranty\WarrantyService;
use App\Enums\AffiliateStatus;
use App\Enums\AttributionSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\SalesChannel;
use App\Models\Affiliate;
use App\Models\Order;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Settings;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT DATA ONLY. Every product is flagged is_sample and every image is labelled
 * "SAMPLE IMAGE". Never run in production (the seeder refuses).
 */
class DevelopmentSeeder extends Seeder
{
    public const PASSWORD = 'password';

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('DevelopmentSeeder must not run in production.');
        }

        $settings = app(Settings::class);
        $settings->set([
            // Placeholder values for local testing only — replace in Admin → Business settings.
            'business.whatsapp_number' => '+919000000000',
            'business.email' => 'hello@sudarjewels.test',
            'business.phone' => '+91 90000 00000',
            'business.address' => "Sample address line\nChennai, Tamil Nadu 600001",
            'social.instagram' => 'https://instagram.com/',
            'shipping.flat_fee_paise' => 7900,
            'shipping.free_above_paise' => 149900,
        ]);

        $admin = User::firstOrCreate(['email' => 'admin@sudarjewels.test'], ['name' => 'Sample Admin', 'password' => self::PASSWORD]);
        $admin->assignRole(Permissions::SUPER_ADMIN);
        User::firstOrCreate(['email' => 'manager@sudarjewels.test'], ['name' => 'Sample Manager', 'password' => self::PASSWORD])->assignRole('manager');
        User::firstOrCreate(['email' => 'packing@sudarjewels.test'], ['name' => 'Sample Packing', 'password' => self::PASSWORD])->assignRole('fulfilment');

        $affUser = User::firstOrCreate(['email' => 'affiliate@sudarjewels.test'], ['name' => 'Sample Affiliate', 'password' => self::PASSWORD, 'phone' => '+919811111111']);
        $affUser->assignRole(Permissions::AFFILIATE);
        $affiliate = Affiliate::firstOrCreate(['user_id' => $affUser->id], [
            'code' => 'SUDAR101', 'status' => AffiliateStatus::Active, 'approved_at' => now(), 'approved_by_id' => $admin->id,
            'city' => 'Chennai', 'terms_accepted_at' => now(), 'payout_method' => 'upi', 'payout_upi_id' => 'sample@upi',
        ]);
        $pendingUser = User::firstOrCreate(['email' => 'pending@sudarjewels.test'], ['name' => 'Pending Affiliate', 'password' => self::PASSWORD]);
        $pendingUser->assignRole(Permissions::AFFILIATE);
        Affiliate::firstOrCreate(['user_id' => $pendingUser->id], ['code' => 'SUDAR102', 'status' => AffiliateStatus::Pending, 'terms_accepted_at' => now()]);

        ini_set('memory_limit', '512M');
        DemoCatalogueSeeder::seedHero(force: true);
        $variants = DemoCatalogueSeeder::seedCatalogue();
        $this->seedOrders($admin, $affiliate, $variants);

        $this->command?->info('Sample logins (password "'.self::PASSWORD.'"): admin@sudarjewels.test (/admin), affiliate@sudarjewels.test (/affiliate/login).');
    }

    /** @param array<string, ProductVariant> $variants */
    private function seedOrders(User $admin, Affiliate $affiliate, array $variants): void
    {
        if (Order::exists()) {
            return;
        }
        $orders = app(OrderService::class);
        $payments = app(PaymentService::class);
        $fulfilment = app(FulfilmentService::class);
        $list = array_values(array_filter($variants, fn (ProductVariant $v) => $v->available() > 2));
        $addr = ['delivery_required' => true, 'address_line1' => 'Sample flat 1, Sample street', 'city' => 'Chennai', 'state' => 'Tamil Nadu', 'postal_code' => '600001'];

        // 1. Delivered WhatsApp order, fully paid, warranty registered (customer phone +91 98765 43210).
        $o1 = $orders->saveQuote(null, $addr + ['customer_name' => 'Sample Customer', 'customer_phone' => '9876543210', 'channel' => SalesChannel::WhatsApp->value,
            'lines' => [['variant_id' => $list[0]->id, 'quantity' => 2], ['variant_id' => $list[3]->id, 'quantity' => 1]]], $admin, ['allow_overrides' => true]);
        $o1 = $orders->confirm($o1, $admin);
        $payments->recordPayment($o1, $o1->grand_total_paise, PaymentMethod::Upi, 'SAMPLE-UPI-001', now()->subDays(12), $admin);
        $s = $fulfilment->dispatch($o1, $o1->units()->pluck('id')->all(), ['courier' => 'Sample Courier', 'tracking_number' => 'SAMPLE123'], $admin);
        $fulfilment->markDelivered($s, now()->subDays(10), $admin);
        app(WarrantyService::class)->register($o1, 'Sample Customer', '9876543210');

        // 2. Affiliate order: confirmed, paid, delivered → pending commission.
        $o2 = $orders->saveQuote(null, $addr + ['customer_name' => 'Sample Referred Customer', 'customer_phone' => '9123456780', 'channel' => SalesChannel::Affiliate->value,
            'lines' => [['variant_id' => $list[5]->id, 'quantity' => 1]]], $affiliate->user, ['affiliate' => $affiliate, 'attribution_source' => AttributionSource::DirectAffiliate, 'status' => OrderStatus::Submitted]);
        $o2 = $orders->confirm($o2, $admin);
        $payments->recordPayment($o2, $o2->grand_total_paise, PaymentMethod::BankTransfer, 'SAMPLE-NEFT-002', now()->subDays(5), $admin);
        $s2 = $fulfilment->dispatch($o2, $o2->units()->pluck('id')->all(), ['courier' => 'Sample Courier'], $admin);
        $fulfilment->markDelivered($s2, now()->subDays(3), $admin);

        // 3. Affiliate quote awaiting admin confirmation.
        $orders->saveQuote(null, $addr + ['customer_name' => 'Sample Quote Customer', 'customer_phone' => '9123456781', 'channel' => SalesChannel::Affiliate->value,
            'lines' => [['variant_id' => $list[2]->id, 'quantity' => 1], ['variant_id' => $list[7]->id, 'quantity' => 2]]], $affiliate->user, ['affiliate' => $affiliate, 'attribution_source' => AttributionSource::DirectAffiliate, 'status' => OrderStatus::Submitted]);

        // 4. In-person sale handed over today.
        $o4 = $orders->saveQuote(null, ['customer_name' => 'Sample Walk-in', 'customer_phone' => '9000011111', 'delivery_required' => false, 'channel' => SalesChannel::InPerson->value,
            'lines' => [['variant_id' => $list[9]->id, 'quantity' => 1]]], $admin, ['allow_overrides' => true]);
        $o4 = $orders->confirm($o4, $admin);
        $payments->recordPayment($o4, $o4->grand_total_paise, PaymentMethod::Cash, null, now(), $admin);
        $fulfilment->handover($o4, $o4->units()->pluck('id')->all(), now(), $admin);
    }
}
