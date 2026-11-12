<?php

use App\Models\Quotation;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            // Link back to the quotation that was converted to this invoice.
            $table->foreignId('quotation_id')
                ->nullable()
                ->after('pos_session_id')
                ->constrained('quotations')
                ->nullOnDelete();

            $table->index('quotation_id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeignIdFor(Quotation::class);
            $table->dropColumn('quotation_id');
        });
    }
};
