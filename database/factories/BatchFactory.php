<?php

namespace Database\Factories;

use App\Models\Batch;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Batch> */
class BatchFactory extends Factory
{
    protected $model = Batch::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'batch_number' => fake()->unique()->bothify('BATCH-####'),
            'expiry_date' => null,
        ];
    }
}
