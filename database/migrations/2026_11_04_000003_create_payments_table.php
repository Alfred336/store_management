<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->string('number')->unique();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('method', 30);
            $table->string('provider', 100)->nullable();
            $table->string('reference', 150)->nullable();
            $table->decimal('amount', 14, 2);
            $table->timestamp('received_at');
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->foreignId('reversed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->text('reverse_reason')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->timestamps();

            $table->index('customer_id');
        });

        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_method_check CHECK (method IN ('cash', 'mobile_money', 'bank_transfer', 'cheque', 'card'))");
        DB::statement("ALTER TABLE payments ADD CONSTRAINT payments_status_check CHECK (status IN ('active', 'reversed'))");
        DB::statement('ALTER TABLE payments ADD CONSTRAINT payments_amount_positive CHECK (amount > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
