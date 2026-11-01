<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('users')->exists()) {
            DB::table('settings')->insertOrIgnore([
                'key' => 'setup_completed_at',
                'value' => json_encode(now()->toIso8601String(), JSON_THROW_ON_ERROR),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'setup_completed_at')->delete();
    }
};
