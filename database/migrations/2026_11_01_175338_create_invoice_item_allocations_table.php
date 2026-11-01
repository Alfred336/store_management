<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per stock movement made for an invoice line.
        // A line sold from two batches has two rows. Voiding reads these rows
        // to return exactly the same quantities to the same batches.
        Schema::create('invoice_item_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('base_quantity', 14, 3);
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('return_movement_id')->nullable()
                ->constrained('stock_movements')->restrictOnDelete();
            $table->timestamps();

            $table->index('invoice_item_id');
        });

        DB::statement('ALTER TABLE invoice_item_allocations ADD CONSTRAINT invoice_item_allocations_qty_check
            CHECK (base_quantity > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_item_allocations');
    }
};