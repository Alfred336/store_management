<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 'workshop' to the allowed location types and enforce a CHECK constraint
 * on locations.type (PostgreSQL only).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_type_check');
        DB::statement("ALTER TABLE locations ADD CONSTRAINT locations_type_check CHECK (type IN ('store','warehouse','branch','workshop'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE locations DROP CONSTRAINT IF EXISTS locations_type_check');
        DB::statement("ALTER TABLE locations ADD CONSTRAINT locations_type_check CHECK (type IN ('store','warehouse','branch'))");
    }
};
