<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migrate invoicing settings keys introduced in phase-3a.
 *
 * Before: invoicing.default_template / invoicing.default_paper (shared)
 * After:  invoicing.invoice_template  / invoicing.invoice_paper  (A4/A5 invoices)
 *         invoicing.quotation_template / invoicing.quotation_paper (quotations)
 *
 * DO NOT run on production until the app code is deployed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Rename existing default_template → invoice_template
        DB::table('settings')
            ->where('key', 'invoicing.default_template')
            ->update(['key' => 'invoicing.invoice_template', 'updated_at' => now()]);

        // Rename existing default_paper → invoice_paper; strip thermal if stored
        $row = DB::table('settings')->where('key', 'invoicing.default_paper')->first();
        if ($row) {
            $paper = in_array($row->value, ['A4', 'A5'], true) ? $row->value : 'A4';
            DB::table('settings')->where('key', 'invoicing.default_paper')
                ->update(['key' => 'invoicing.invoice_paper', 'value' => $paper, 'updated_at' => now()]);
        }

        // Seed quotation settings from the invoice settings (or defaults)
        $invoiceTemplate = DB::table('settings')->where('key', 'invoicing.invoice_template')->value('value') ?? 'classic';
        $invoicePaper = DB::table('settings')->where('key', 'invoicing.invoice_paper')->value('value') ?? 'A4';

        // Guard: thermal is not valid for quotations
        if ($invoiceTemplate === 'thermal') {
            $invoiceTemplate = 'classic';
        }

        foreach ([
            'invoicing.quotation_template' => $invoiceTemplate,
            'invoicing.quotation_paper' => $invoicePaper,
        ] as $key => $value) {
            DB::table('settings')->upsert(
                [['key' => $key, 'value' => $value, 'created_at' => now(), 'updated_at' => now()]],
                ['key'],
                ['value', 'updated_at']
            );
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'invoicing.invoice_template')
            ->update(['key' => 'invoicing.default_template', 'updated_at' => now()]);
        DB::table('settings')->where('key', 'invoicing.invoice_paper')
            ->update(['key' => 'invoicing.default_paper', 'updated_at' => now()]);
        DB::table('settings')->whereIn('key', ['invoicing.quotation_template', 'invoicing.quotation_paper'])->delete();
    }
};
