<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained();
            $table->decimal('quantity', 14, 3)->default(0);
            $table->timestamps();
        });

        DB::statement('CREATE UNIQUE INDEX stock_levels_product_location_unbatched_unique ON stock_levels (product_id, location_id) WHERE batch_id IS NULL');
        DB::statement('CREATE UNIQUE INDEX stock_levels_product_location_batch_unique ON stock_levels (product_id, location_id, batch_id) WHERE batch_id IS NOT NULL');
        DB::statement('ALTER TABLE stock_levels ADD CONSTRAINT stock_levels_quantity_nonnegative_check CHECK (quantity >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE stock_levels DROP CONSTRAINT IF EXISTS stock_levels_quantity_nonnegative_check');
        DB::statement('DROP INDEX IF EXISTS stock_levels_product_location_unbatched_unique');
        DB::statement('DROP INDEX IF EXISTS stock_levels_product_location_batch_unique');
        Schema::dropIfExists('stock_levels');
    }
};
