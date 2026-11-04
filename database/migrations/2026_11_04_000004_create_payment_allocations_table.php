<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->unique(['payment_id', 'invoice_id']);
            $table->index('invoice_id');
        });

        DB::statement('ALTER TABLE payment_allocations ADD CONSTRAINT payment_allocations_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
