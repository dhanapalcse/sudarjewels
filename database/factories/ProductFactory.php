<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Sample '.ucfirst($this->faker->unique()->words(2, true));

        return [
            'category_id' => Category::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'sku' => 'SJ-'.strtoupper(Str::random(6)),
            'price_paise' => 99900,
            'status' => ProductStatus::Published,
            'commission_enabled' => true,
            'commission_rate_bp' => null,
            'warranty_eligible' => true,
            'is_sample' => true,
            'published_at' => now(),
        ];
    }
}
