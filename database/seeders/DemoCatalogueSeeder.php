<?php

namespace Database\Seeders;

use App\Domain\Catalogue\Images\ImageProcessor;
use App\Domain\Inventory\InventoryService;
use App\Enums\ImageStatus;
use App\Enums\ProductStatus;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\CatalogueCache;
use App\Support\Settings;
use Database\Seeders\Support\SampleImageGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

/**
 * Showcase catalogue: categories, sample products with variants, stock and labelled
 * "SAMPLE IMAGE" pictures. Safe on a live server: creates no users or orders and never
 * changes business settings (the hero image is only added when none is set).
 * Remove it with `php artisan sudar:demo --remove`.
 */
class DemoCatalogueSeeder extends Seeder
{
    /** Category slugs created by the demo (used for clean removal). */
    public const CATEGORY_SLUGS = ['earrings', 'necklaces', 'rings', 'bracelets', 'studs', 'hoops', 'drops', 'pendants', 'chokers'];

    public function run(): void
    {
        // Generating ~32 images in one process needs more than the default CLI limit.
        ini_set('memory_limit', '512M');
        self::seedHero();
        self::seedCatalogue();
        CatalogueCache::flush();
    }

    /** Adds the sample hero image only if the business hasn't uploaded one (or $force). */
    public static function seedHero(bool $force = false): void
    {
        $settings = app(Settings::class);
        if (! $force && is_array($settings->get('home.hero_image'))) {
            return;
        }
        $binary = SampleImageGenerator::hero();
        $disk = Storage::disk(config('sudar.images.disk'));
        $manager = new ImageManager(new Driver);
        $webp = [];
        foreach ([640, 1024, 1600] as $w) {
            $path = "hero/sample-hero-{$w}.webp";
            $disk->put($path, (string) $manager->read($binary)->scaleDown(width: $w)->toWebp(quality: 80), ['visibility' => 'public']);
            $webp[(string) $w] = $path;
        }
        $settings->set(['home.hero_image' => ['webp' => $webp, 'width' => 1800, 'height' => 1000, 'alt' => 'Sample hero image of gold hoop earrings', 'is_sample' => true]]);
    }

    /** @return array<string, ProductVariant> variants keyed by SKU */
    public static function seedCatalogue(): array
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
            $parent = Category::firstOrCreate(['slug' => $slug], ['name' => $name, 'sort_order' => $order++, 'description' => "Anti-tarnish {$name} for everyday wear."]);
            $cats[$slug] = ['parent' => $parent, 'children' => []];
            foreach ($children as $i => $child) {
                $cats[$slug]['children'][] = Category::firstOrCreate(['slug' => Str::slug($child)], ['name' => $child, 'parent_id' => $parent->id, 'sort_order' => $i]);
            }
        }

        // [category, name, price, regular price, finishes, sizes, flags, one-line description]
        $products = [
            ['earrings', 'Pearl Hoops', 89900, 119900, ['Gold', 'Silver'], null, ['new', 'best'], 'Polished hoops finished with a single freshwater-style pearl drop.'],
            ['earrings', 'Seoul Mini Studs', 49900, null, ['Gold', 'Rose gold', 'Silver'], null, ['new'], 'Tiny, comfortable studs for all-day wear and second piercings.'],
            ['earrings', 'Petal Drop Earrings', 79900, null, ['Gold'], null, ['best'], 'Soft petal drops that catch the light with every movement.'],
            ['earrings', 'Twisted Huggies', 69900, 89900, ['Gold', 'Silver'], null, [], 'Close-fitting twisted huggies with a secure click closure.'],
            ['earrings', 'Moon Charm Hoops', 99900, null, ['Gold'], null, ['new'], 'Slim hoops with a removable crescent-moon charm.'],
            ['necklaces', 'Layered Pearl Necklace', 149900, 179900, ['Gold'], null, ['best'], 'Two delicate layers — a fine chain and a strand of pearls.'],
            ['necklaces', 'Clover Pendant', 109900, null, ['Gold', 'Silver'], null, ['new'], 'A four-leaf clover pendant on an adjustable everyday chain.'],
            ['necklaces', 'Dainty Choker', 89900, null, ['Gold'], null, [], 'A barely-there choker that layers beautifully.'],
            ['necklaces', 'Teal Stone Pendant', 129900, null, ['Gold'], null, ['best'], 'A deep teal stone set in a minimal gold frame.'],
            ['rings', 'Solitaire Band', 69900, null, ['Gold', 'Silver'], ['6', '7', '8'], ['best'], 'A classic band with a single sparkling stone.'],
            ['rings', 'Stacking Ring Set', 89900, 99900, ['Gold'], ['6', '7', '8'], ['new'], 'Three slim rings to wear together or apart.'],
            ['rings', 'Pearl Dome Ring', 79900, null, ['Gold'], ['Adjustable'], [], 'A sculpted dome ring crowned with a pearl.'],
            ['bracelets', 'Tennis Bracelet', 159900, 189900, ['Gold', 'Silver'], null, ['best'], 'A row of bright stones on a flexible, secure bracelet.'],
            ['bracelets', 'Charm Chain Bracelet', 99900, null, ['Gold'], null, ['new'], 'A fine chain with small pearl and star charms.'],
            ['bracelets', 'Pearl Cuff', 119900, null, ['Gold'], null, [], 'An open cuff with pearls at each end.'],
            ['earrings', 'Classic Ear Cuff', 39900, null, ['Gold'], null, [], 'No-piercing ear cuff that slides comfortably into place.'],
        ];

        $processor = app(ImageProcessor::class);
        $inventory = app(InventoryService::class);
        $variantsBySku = [];
        foreach ($products as $i => [$catSlug, $name, $price, $regular, $finishes, $sizes, $flags, $blurb]) {
            $children = $cats[$catSlug]['children'];
            $product = Product::withTrashed()->firstOrCreate(['slug' => Str::slug($name)], [
                'category_id' => $cats[$catSlug]['parent']->id,
                'subcategory_id' => $children ? $children[$i % count($children)]->id : null,
                'name' => $name,
                'sku' => 'SJ-'.strtoupper(substr($catSlug, 0, 2)).'-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'short_description' => $blurb.' Anti-tarnish finish made for daily wear.',
                'description' => "{$blurb}\n\nKorean-inspired and lightweight, with an anti-tarnish finish that keeps its shine through everyday wear. Hypoallergenic and nickel-free.\n\nSample listing for demonstration — replace with the real product details.",
                'materials' => 'Stainless steel base, 18K gold or rhodium anti-tarnish plating, simulated pearls/stones. (Sample details.)',
                'care_instructions' => "Keep dry and away from perfume and lotions.\nStore in the pouch provided.\nWipe gently with a soft cloth after wear.",
                'price_paise' => $price,
                'regular_price_paise' => $regular,
                'status' => ProductStatus::Published,
                'is_featured' => $i < 4,
                'is_new_arrival' => in_array('new', $flags, true),
                'is_best_seller' => in_array('best', $flags, true),
                'commission_enabled' => $name !== 'Classic Ear Cuff',
                'commission_rate_bp' => $name === 'Layered Pearl Necklace' ? 1500 : null,
                'warranty_eligible' => true,
                'is_sample' => true,
                'published_at' => now()->subDays(30 - $i),
                'seo_description' => "{$name} — {$blurb}",
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
                gc_collect_cycles();
            }
        }

        return $variantsBySku;
    }
}
