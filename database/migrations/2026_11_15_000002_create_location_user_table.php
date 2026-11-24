<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot table linking users to their allowed locations.
 *
 * Access rule: a user can access exactly the ACTIVE locations assigned here.
 * Admin role bypasses this table entirely (always sees all locations).
 *
 * Backfill: every existing non-anonymized non-Admin user is assigned to all
 * currently active locations so nothing changes operationally until an admin
 * explicitly narrows access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'location_id']);
        });

        // Backfill: assign every active location to every non-anonymized non-Admin user.
        DB::statement("
            INSERT INTO location_user (user_id, location_id, created_at, updated_at)
            SELECT u.id, l.id, now(), now()
            FROM users u
            CROSS JOIN locations l
            WHERE l.is_active = true
              AND u.anonymized_at IS NULL
              AND NOT EXISTS (
                  SELECT 1
                  FROM model_has_roles mhr
                  JOIN roles r ON r.id = mhr.role_id
                  WHERE mhr.model_id = u.id
                    AND mhr.model_type = 'App\\\\Models\\\\User'
                    AND r.name = 'Admin'
              )
            ON CONFLICT (user_id, location_id) DO NOTHING
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('location_user');
    }
};
