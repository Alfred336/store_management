<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('settings')->insertOrIgnore([
            'key' => 'numbering.customer_prefix',
            'value' => json_encode('CUS', JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('settings')->insertOrIgnore([
            'key' => 'customers.credit_limit_mode',
            'value' => json_encode('warn', JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->string('type', 20)->default('individual');
            $table->string('phone', 50)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('tin', 50)->nullable();
            $table->text('notes')->nullable();
            $table->decimal('credit_limit', 14, 2)->default(0);
            $table->unsignedSmallInteger('payment_terms_days')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_walk_in')->default(false);
            $table->timestamps();

            $table->index(['name', 'is_active']);
            $table->index(['type', 'is_active']);
        });

        DB::statement('CREATE UNIQUE INDEX customers_single_walk_in_unique ON customers (is_walk_in) WHERE is_walk_in = true');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS customers_single_walk_in_unique');
        Schema::dropIfExists('customers');
    }
};
