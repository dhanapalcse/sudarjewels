<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = ucfirst($this->faker->unique()->word()).' '.$this->faker->numberBetween(1, 9999);

        return ['name' => $name, 'slug' => Str::slug($name), 'is_active' => true, 'sort_order' => 0];
    }
}
