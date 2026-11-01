<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table): void {
            $table->string('type', 30);
            $table->unsignedSmallInteger('year')->default(0);
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();
            $table->primary(['type', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_sequences');
    }
};
