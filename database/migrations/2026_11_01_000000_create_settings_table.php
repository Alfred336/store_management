<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->string('key', 191)->primary();
            $table->text('value');
            $table->timestamps();
        });

        $now = now();
        $defaults = [
            'business.currency' => 'TZS',
            'tax.vat_rate' => '18.00',
            'tax.prices_include_tax' => false,
            'numbering.reset_yearly' => true,
            'numbering.digits' => 6,
            'numbering.invoice_prefix' => 'INV',
            'numbering.receipt_prefix' => 'RCT',
            'numbering.payment_prefix' => 'PAY',
        ];

        DB::table('settings')->insert(array_map(
            fn (string $key, mixed $value): array => [
                'key' => $key,
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            array_keys($defaults),
            array_values($defaults),
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
