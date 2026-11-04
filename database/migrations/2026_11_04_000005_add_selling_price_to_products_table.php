<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->decimal('selling_price', 14, 2)->default(0)->after('reorder_level');
        });

        DB::statement('ALTER TABLE products ADD CONSTRAINT products_selling_price_check CHECK (selling_price >= 0)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE products DROP CONSTRAINT IF EXISTS products_selling_price_check');
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('selling_price');
        });
    }
};
