<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Setup\SetupStatus;
use Illuminate\Database\Seeder;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = getenv('ADMIN_SEED_PASSWORD');

        if ($password === false || $password === '') {
            throw new RuntimeException('Set ADMIN_SEED_PASSWORD before running the database seeders.');
        }

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => $password,
                'email_verified_at' => now(),
            ],
        );

        $admin->assignRole('Admin');
        app(SetupStatus::class)->forget();
    }
}
