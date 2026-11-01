<?php

namespace Database\Seeders;

use App\Actions\Authorization\EnsureSystemRoles;
use Illuminate\Database\Seeder;

/** Ensure the baseline roles and inventory permissions exist. */
class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(EnsureSystemRoles::class)();
    }
}
