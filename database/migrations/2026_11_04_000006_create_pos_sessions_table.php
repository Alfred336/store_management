<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_cash', 14, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->decimal('closing_cash_counted', 14, 2)->nullable();
            $table->decimal('expected_cash', 14, 2)->nullable();
            $table->decimal('difference', 14, 2)->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->string('status')->default('open');
            $table->timestamps();
        });

        // PostgreSQL CHECK: status must be 'open' or 'closed'
        DB::statement("ALTER TABLE pos_sessions ADD CONSTRAINT pos_sessions_status_check CHECK (status IN ('open', 'closed'))");

        // Partial unique index: at most one open session per user
        DB::statement("CREATE UNIQUE INDEX pos_sessions_user_open_unique ON pos_sessions (user_id) WHERE status = 'open'");
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_sessions');
    }
};
