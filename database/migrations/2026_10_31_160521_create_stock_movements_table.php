<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('location_id')->constrained();
            $table->foreignId('batch_id')->nullable()->constrained();
            $table->string('type', 20);
            $table->decimal('quantity', 14, 3);
            $table->string('reference')->nullable();
            $table->text('reason')->nullable();
            $table->string('person_name')->nullable();
            $table->foreignId('user_id')->nullable()->constrained();
            $table->timestamp('moved_at');
            $table->timestamps();
            $table->index(['product_id', 'moved_at']);
            $table->index(['location_id', 'moved_at']);
            $table->index('type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
