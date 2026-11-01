<?php

use App\Services\Invoicing\DocumentNumberingService;
use App\Services\Settings\Settings;

test('document numbers use configured prefixes, padded values and annual sequences', function () {
    app(Settings::class)->putMany([
        'numbering.reset_yearly' => true,
        'numbering.digits' => 6,
        'numbering.invoice_prefix' => 'INV',
    ]);

    $numbering = app(DocumentNumberingService::class);

    expect($numbering->next('invoice', 2026))->toBe('INV-2026-000001')
        ->and($numbering->next('invoice', 2026))->toBe('INV-2026-000002')
        ->and($numbering->next('invoice', 2027))->toBe('INV-2027-000001')
        ->and($numbering->next('receipt', 2026))->toBe('RCT-2026-000001')
        ->and($numbering->next('payment', 2026))->toBe('PAY-2026-000001');
});

test('document sequence increments roll back with the enclosing operation', function () {
    $numbering = app(DocumentNumberingService::class);

    try {
        DB::transaction(function () use ($numbering): void {
            expect($numbering->next('invoice', 2026))->toBe('INV-2026-000001');
            throw new RuntimeException('Force transaction rollback.');
        });
    } catch (RuntimeException $exception) {
        expect($exception->getMessage())->toBe('Force transaction rollback.');
    }

    expect($numbering->next('invoice', 2026))->toBe('INV-2026-000001');
});
