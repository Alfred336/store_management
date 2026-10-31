<?php

namespace Database\Seeders;

use App\Models\Location;
use Illuminate\Database\Seeder;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Location::query()->updateOrCreate(
            ['name' => 'Main Store'],
            ['type' => 'store', 'is_active' => true],
        );
    }
}
