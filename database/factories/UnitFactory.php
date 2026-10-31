<?php

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Unit> */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'abbreviation' => fake()->unique()->lexify('???'),
        ];
    }
}
