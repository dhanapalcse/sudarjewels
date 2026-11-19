<?php

namespace Database\Seeders;

use App\Domain\Catalogue\Images\ImageProcessor;
use App\Domain\Inventory\InventoryService;
use App\Domain\Orders\FulfilmentService;
use App\Domain\Orders\OrderService;
use App\Domain\Orders\PaymentService;
use App\Domain\Warranty\WarrantyService;
use App\Enums\AffiliateStatus;
use App\Enums\AttributionSource;
use App\Enums\ImageStatus;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\ProductStatus;
use App\Enums\SalesChannel;
use App\Enums\StockMovementType;
use App\Models\Affiliate;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Support\Permissions;
use App\Support\Settings;
use Database\Seeders\Support\SampleImageGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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

        $this->seedHero($settings);
        $variants = $this->seedCatalogue();
        $this->seedOrders($admin, $affiliate, $variants);

        $this->command?->info('Sample logins (password "'.self::PASSWORD.'"): admin@sudarjewels.test (/admin), affiliate@sudarjewels.test (/affiliate/login).');
    }

    private function seedHero(Settings $settings): void
    {
        $binary = SampleImageGenerator::hero();
        $disk = Storage::disk(config('sudar.images.disk'));
        $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver);
        $webp = [];
        foreach ([640, 1024, 1600] as $w) {
            $path = "hero/sample-hero-{$w}.webp";
            $disk->put($path, (string) $manager->read($binary)->scaleDown(width: $w)->toWebp(quality: 80));
            $webp[(string) $w] = $path;
        }
        $settings->set(['home.hero_image' => ['webp' => $webp, 'width' => 1800, 'height' => 1000, 'alt' => 'Sample hero image of gold hoop earrings', 'is_sample' => true]]);
    }

    /** @return array<string, ProductVariant> */
    private function seedCatalogue(): array
    {
        $tree = [
            'earrings' => ['Earrings', ['Studs', 'Hoops', 'Drops']],
            'necklaces' => ['Necklaces', ['Pendants', 'Chokers']],
            'rings' => ['Rings', []],
            'bracelets' => ['Bracelets', []],
        ];
        $cats = [];
        $order = 0;
        foreach ($tree as $slug => [$name, $children]) {
            $parent = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'sort_order' => $order++, 'description' => "Sample {$name} category."]);
            $cats[$slug] = ['parent' => $parent, 'children' => []];
            foreach ($children as $i => $child) {
                $cats[$slug]['children'][] = Category::firstOrCreate(['slug' => Str::slug($child)], ['name' => $child, 'parent_id' => $parent->id, 'sort_order' => $i]);
            }
        }

        $products = [
            ['earrings', 'Pearl Hoops', 89900, 119900, ['Gold', 'Silver'], null, ['new', 'best']],
            ['earrings', 'Seoul Mini Studs', 49900, null, ['Gold', 'Rose gold', 'Silver'], null, ['new']],
            ['earrings', 'Petal Drop Earrings', 79900, null, ['Gold'], null, ['best']],
            ['earrings', 'Twisted Huggies', 69900, 89900, ['Gold', 'Silver'], null, []],
            ['earrings', 'Moon Charm Hoops', 99900, null, ['Gold'], null, ['new']],
            ['necklaces', 'Layered Pearl Necklace', 149900, 179900, ['Gold'], null, ['best']],
            ['necklaces', 'Clover Pendant', 109900, null, ['Gold', 'Silver'], null, ['new']],
            ['necklaces', 'Dainty Choker', 89900, null, ['Gold'], null, []],
            ['necklaces', 'Teal Stone Pendant', 129900, null, ['Gold'], null, ['best']],
            ['rings', 'Solitaire Band', 69900, null, ['Gold', 'Silver'], ['6', '7', '8'], ['best']],
            ['rings', 'Stacking Ring Set', 89900, 99900, ['Gold'], ['6', '7', '8'], ['new']],
            ['rings', 'Pearl Dome Ring', 79900, null, ['Gold'], ['Adjustable'], []],
            ['bracelets', 'Tennis Bracelet', 159900, 189900, ['Gold', 'Silver'], null, ['best']],
            ['bracelets', 'Charm Chain Bracelet', 99900, null, ['Gold'], null, ['new']],
            ['bracelets', 'Pearl Cuff', 119900, null, ['Gold'], null, []],
            ['earrings', 'Classic Ear Cuff (no commission)', 39900, null, ['Gold'], null, []],
        ];

        $processor = app(ImageProcessor::class);
        $inventory = app(InventoryService::class);
        $variantsBySku = [];
        foreach ($products as $i => [$catSlug, $name, $price, $regular, $finishes, $sizes, $flags]) {
            $children = $cats[$catSlug]['children'];
            $slug = Str::slug($name);
            $product = Product::withTrashed()->firstOrCreate(['slug' => Str::slug(str_replace(' (no commission)', '', $name))], [
                'category_id' => $cats[$catSlug]['parent']->id,
                'subcategory_id' => $children ? $children[$i % count($children)]->id : null,
                'name' => str_replace(' (no commission)', '', $name),
                'sku' => 'SJ-'.strtoupper(substr($catSlug, 0, 2)).'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'short_description' => "Sample product: a lightweight {$name} with an anti-tarnish finish for daily wear.",
                'description' => "This is sample catalogue content for development.\n\nReplace it with the real product description, dimensions and styling notes.",
                'materials' => 'Sample: stainless steel base with anti-tarnish plating; simulated pearls/stones.',
                'care_instructions' => "Keep dry and away from perfume.\nStore in the pouch provided.\nWipe gently with a soft cloth.",
                'price_paise' => $price,
                'regular_price_paise' => $regular,
                'status' => ProductStatus::Published,
                'is_featured' => $i < 4,
                'is_new_arrival' => in_array('new', $flags, true),
                'is_best_seller' => in_array('best', $flags, true),
                'commission_enabled' => ! str_contains($name, 'no commission'),
                'commission_rate_bp' => $name === 'Layered Pearl Necklace' ? 1500 : null,
                'warranty_eligible' => true,
                'is_sample' => true,
                'published_at' => now()->subDays(30 - $i),
                'seo_description' => "Sample: {$name} from SUDAR JEWELS.",
            ]);
            $n = 0;
            foreach ($finishes as $finish) {
                foreach ($sizes ?? [null] as $size) {
                    $sku = $product->sku.'-'.strtoupper(substr(Str::slug($finish), 0, 2)).($size ? '-'.Str::slug($size) : '');
                    $variant = ProductVariant::firstOrCreate(['sku' => $sku], [
                        'product_id' => $product->id, 'finish' => $finish, 'size' => $size, 'sort_order' => $n++,
                    ]);
                    if ($variant->wasRecentlyCreated) {
                        $inventory->adjust($variant, ($i + $n) % 7 === 0 ? 0 : 3 + (($i * 3 + $n) % 9), "seed:initial:{$variant->id}", null, 'Sample opening stock', StockMovementType::Initial);
                    }
                    $variantsBySku[$sku] = $variant->refresh();
                }
            }
            if (! $product->images()->exists()) {
                foreach ([0, 1] as $k) {
                    $image = $product->images()->create(['source' => 'sample', 'status' => ImageStatus::Processing, 'alt' => "Sample image of {$product->name}", 'is_main' => $k === 0, 'sort_order' => $k]);
                    $processor->publish($image, SampleImageGenerator::product($catSlug, $i * 2 + $k + 1));
                }
            }
        }

        return $variantsBySku;
    }

    /** @param array<string, ProductVariant> $variants */
    private function seedOrders(User $admin, Affiliate $affiliate, array $variants): void
    {
        if (\App\Models\Order::exists()) {
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
