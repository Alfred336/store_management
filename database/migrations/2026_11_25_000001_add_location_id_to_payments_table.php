<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->unsignedBigInteger('location_id')->nullable()->after('pos_session_id');
            $table->index('location_id');
        });

        // Backfill: set location_id from the invoice of the first allocation (ascending id).
        // Payments with no allocations (unallocated credit) remain null.
        DB::statement('
            UPDATE payments
            SET location_id = (
                SELECT i.location_id
                FROM payment_allocations pa
                JOIN invoices i ON i.id = pa.invoice_id
                WHERE pa.payment_id = payments.id
                ORDER BY pa.id ASC
                LIMIT 1
            )
            WHERE EXISTS (
                SELECT 1 FROM payment_allocations pa2 WHERE pa2.payment_id = payments.id
            )
        ');

        // Add FK after backfill so no existing null rows violate it.
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreign('location_id')
                ->references('id')
                ->on('locations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['location_id']);
            $table->dropIndex(['location_id']);
            $table->dropColumn('location_id');
        });
    }
};
