<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            // Assigned only when the invoice is issued; drafts have no number.
            // PostgreSQL allows many NULLs in a unique index, so drafts never clash.
            $table->string('number', 40)->nullable()->unique();
            $table->string('channel', 10)->default('invoice');
            $table->string('status', 20)->default('draft');

            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();

            $table->date('issue_date')->nullable();
            $table->date('due_date')->nullable();
            $table->string('currency', 3)->default('TZS');
            $table->boolean('prices_include_tax')->default(false);

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);
            // Maintained only by the payment Actions (Phase 4).
            $table->decimal('amount_paid', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->string('template_key', 30)->nullable();

            // Customer snapshot, written when the invoice is issued.
            $table->string('customer_name')->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->text('customer_address')->nullable();
            $table->string('customer_tin', 50)->nullable();
            $table->string('customer_vrn', 50)->nullable();

            $table->string('idempotency_key', 64)->nullable()->unique();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->text('void_reason')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('issue_date');
            $table->index('status');
        });

        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_status_check
            CHECK (status IN ('draft','issued','partially_paid','paid','void'))");
        DB::statement("ALTER TABLE invoices ADD CONSTRAINT invoices_channel_check
            CHECK (channel IN ('invoice','pos'))");
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_amounts_check
            CHECK (total >= 0 AND amount_paid >= 0 AND amount_paid <= total)');
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};