<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('amount_tendered', 14, 2)->nullable()->after('amount');
            $table->decimal('change_given', 14, 2)->nullable()->after('amount_tendered');
            $table->foreignId('pos_session_id')
                ->nullable()
                ->after('change_given')
                ->constrained('pos_sessions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pos_session_id');
            $table->dropColumn(['amount_tendered', 'change_given']);
        });
    }
};
