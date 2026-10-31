<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-########'),
            'name' => fake()->words(3, true),
            'category_id' => Category::factory(),
            'base_unit_id' => Unit::factory(),
            'reorder_level' => '0.000',
            'track_batches' => false,
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
        ];
    }
}
