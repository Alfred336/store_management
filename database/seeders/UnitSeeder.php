<?php

namespace Database\Seeders;

use App\Models\Unit;
use Illuminate\Database\Seeder;

class UnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach ([
            ['name' => 'Piece', 'abbreviation' => 'pc'],
            ['name' => 'Carton', 'abbreviation' => 'ctn'],
            ['name' => 'Kg', 'abbreviation' => 'kg'],
            ['name' => 'Litre', 'abbreviation' => 'L'],
            ['name' => 'Box', 'abbreviation' => 'box'],
        ] as $unit) {
            Unit::query()->updateOrCreate(['name' => $unit['name']], $unit);
        }
    }
}
