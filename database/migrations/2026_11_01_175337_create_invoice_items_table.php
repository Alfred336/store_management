<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();

            // Snapshots so later product edits never change an issued invoice.
            $table->string('description');
            $table->string('sku', 100)->nullable();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();

            $table->decimal('quantity', 14, 3);       // as entered, in the chosen unit
            $table->decimal('base_quantity', 14, 3);  // converted to the product's base unit
            $table->decimal('unit_price', 14, 2);
            $table->decimal('discount_amount', 14, 2)->default(0);

            $table->string('tax_type', 20)->default('standard');
            $table->decimal('tax_rate', 5, 2)->default(0);
            $table->decimal('line_tax', 14, 2)->default(0);
            $table->decimal('line_total', 14, 2)->default(0);

            $table->timestamps();

            $table->index('invoice_id');
            $table->index('product_id');
        });

        DB::statement('ALTER TABLE invoice_items ADD CONSTRAINT invoice_items_amounts_check
            CHECK (quantity > 0 AND base_quantity > 0 AND unit_price >= 0
                   AND discount_amount >= 0 AND line_tax >= 0 AND line_total >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
