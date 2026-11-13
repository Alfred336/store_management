<?php

use App\Livewire\Actions\Authorization\EnsureSystemRoles;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        app(EnsureSystemRoles::class)();
    }

    public function down(): void {}
};
