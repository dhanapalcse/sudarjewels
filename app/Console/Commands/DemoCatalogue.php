<?php

namespace App\Console\Commands;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use App\Support\Audit;
use App\Support\CatalogueCache;
use App\Support\Settings;
use Database\Seeders\DemoCatalogueSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Adds or removes the showcase catalogue. Adding never creates users or orders and never
 * changes business settings; removal deletes only products flagged as samples.
 */
class DemoCatalogue extends Command
{
    protected $signature = 'sudar:demo {--remove : Remove all sample products, demo categories and the sample hero image}';

    protected $description = 'Add (or remove) sample products to showcase the storefront';

    public function handle(): int
    {
        return $this->option('remove') ? $this->remove() : $this->add();
    }

    private function add(): int
    {
        if (app()->environment('production') && ! $this->confirm('This adds sample products (labelled "Sample") to the LIVE storefront. Continue?', true)) {
            return self::SUCCESS;
        }
        $this->info('Creating sample catalogue and images (this takes about 30 seconds)…');
        $this->call('db:seed', ['--class' => DemoCatalogueSeeder::class, '--force' => true]);
        Audit::log('demo.catalogue_added', null, ['products' => Product::where('is_sample', true)->count()]);
        $this->info(Product::where('is_sample', true)->count().' sample products are live. Remove them before launch with: php artisan sudar:demo --remove');

        return self::SUCCESS;
    }

    private function remove(): int
    {
        if (! $this->confirm('Remove all sample products, their images and empty demo categories?', true)) {
            return self::SUCCESS;
        }
        $disk = Storage::disk(config('sudar.images.disk'));
        $deleted = $kept = 0;

        foreach (Product::withTrashed()->where('is_sample', true)->with(['images', 'variants'])->get() as $product) {
            if (OrderItem::where('product_id', $product->id)->exists()) {
                // Keep order history intact: hide instead of deleting.
                $product->update(['status' => ProductStatus::Draft]);
                $kept++;
                continue;
            }
            DB::transaction(function () use ($product, $disk) {
                foreach ($product->images as $image) {
                    $paths = array_merge(array_values($image->renditions['webp'] ?? []), isset($image->renditions['jpeg']) ? [$image->renditions['jpeg']] : []);
                    $disk->delete($paths);
                }
                $variantIds = $product->variants()->withTrashed()->pluck('id');
                StockMovement::whereIn('product_variant_id', $variantIds)->delete();
                $product->forceDelete();
            });
            $deleted++;
        }

        // Demo categories that no longer hold any product (subcategories first).
        $removedCategories = 0;
        $slugs = DemoCatalogueSeeder::CATEGORY_SLUGS;
        foreach (Category::whereIn('slug', $slugs)->orderByRaw('parent_id is null')->get() as $category) {
            $inUse = Product::withTrashed()->where(fn ($q) => $q->where('category_id', $category->id)->orWhere('subcategory_id', $category->id))->exists()
                || Category::where('parent_id', $category->id)->exists();
            if (! $inUse) {
                $category->delete();
                $removedCategories++;
            }
        }

        $settings = app(Settings::class);
        $hero = $settings->get('home.hero_image');
        if (is_array($hero) && ($hero['is_sample'] ?? false)) {
            $disk->delete(array_values($hero['webp'] ?? []));
            $settings->set(['home.hero_image' => null]);
        }

        CatalogueCache::flush();
        Audit::log('demo.catalogue_removed', null, ['deleted' => $deleted, 'hidden' => $kept, 'categories' => $removedCategories]);
        $this->info("Removed {$deleted} sample products and {$removedCategories} empty demo categories.");
        if ($kept) {
            $this->warn("{$kept} sample products appear in orders, so they were unpublished (set to Draft) instead of deleted.");
        }

        return self::SUCCESS;
    }
}
