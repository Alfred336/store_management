<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->restrictOnDelete();
            $table->string('template_key', 30);
            $table->string('paper', 10);
            $table->string('action', 20)->default('print');
            $table->boolean('is_duplicate')->default(false);
            $table->string('path')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('invoice_id');
        });

        DB::statement("ALTER TABLE invoice_documents ADD CONSTRAINT invoice_documents_action_check
            CHECK (action IN ('print', 'download', 'save'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_documents');
    }
};
