<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotations', function (Blueprint $table) {
            $table->id();
            // Assigned only when the quotation is sent; drafts have no number.
            $table->string('number', 40)->nullable()->unique();
            $table->string('status', 20)->default('draft');

            // Either customer_id OR prospect_name must be provided (enforced by CHECK).
            $table->foreignId('customer_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('prospect_name', 255)->nullable();
            $table->string('prospect_phone', 50)->nullable();
            $table->string('prospect_email', 255)->nullable();
            $table->text('prospect_address')->nullable();

            $table->foreignId('location_id')->constrained()->restrictOnDelete();

            $table->date('quote_date');
            $table->date('valid_until');
            $table->string('currency', 3)->default('TZS');
            $table->boolean('prices_include_tax')->default(false);

            $table->decimal('subtotal', 14, 2)->default(0);
            $table->decimal('discount_total', 14, 2)->default(0);
            $table->decimal('tax_total', 14, 2)->default(0);
            $table->decimal('total', 14, 2)->default(0);

            $table->text('notes')->nullable();
            $table->text('terms')->nullable();

            $table->integer('revision')->default(1);
            $table->foreignId('revised_from_id')->nullable()->constrained('quotations')->nullOnDelete();
            $table->foreignId('converted_invoice_id')->nullable()->constrained('invoices')->nullOnDelete();

            // Snapshot: written when the quotation is sent.
            $table->string('customer_name', 255)->nullable();
            $table->string('customer_phone', 50)->nullable();
            $table->text('customer_address')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('reject_reason')->nullable();

            $table->timestamps();

            $table->index(['customer_id', 'status']);
            $table->index('status');
            $table->index('quote_date');
            $table->index('valid_until');
        });

        // "Expired" is computed (status=sent AND valid_until < today), not stored.
        DB::statement("ALTER TABLE quotations ADD CONSTRAINT quotations_status_check
            CHECK (status IN ('draft','sent','accepted','rejected','converted'))");
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_customer_or_prospect_check
            CHECK (customer_id IS NOT NULL OR prospect_name IS NOT NULL)');
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_amounts_check
            CHECK (subtotal >= 0 AND discount_total >= 0 AND tax_total >= 0 AND total >= 0)');
        DB::statement('ALTER TABLE quotations ADD CONSTRAINT quotations_valid_until_check
            CHECK (valid_until >= quote_date)');
    }

    public function down(): void
    {
        Schema::dropIfExists('quotations');
    }
};
