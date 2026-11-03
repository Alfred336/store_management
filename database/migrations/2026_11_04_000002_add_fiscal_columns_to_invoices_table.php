<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->string('fiscal_number', 50)->nullable()->after('void_reason');
            $table->string('fiscal_verification_code', 100)->nullable()->after('fiscal_number');
            $table->text('fiscal_qr_payload')->nullable()->after('fiscal_verification_code');
            $table->string('fiscal_status', 30)->nullable()->after('fiscal_qr_payload');
            $table->timestamp('fiscal_submitted_at')->nullable()->after('fiscal_status');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table): void {
            $table->dropColumn([
                'fiscal_number',
                'fiscal_verification_code',
                'fiscal_qr_payload',
                'fiscal_status',
                'fiscal_submitted_at',
            ]);
        });
    }
};
