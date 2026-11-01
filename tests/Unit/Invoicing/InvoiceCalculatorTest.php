<?php

use App\Services\Invoicing\InvoiceCalculator;
use App\Services\Money\Money;
use App\Services\Settings\Settings;

test('invoice calculator rounds each exclusive-tax line to two decimals', function () {
    $settings = Mockery::mock(Settings::class);
    $settings->shouldReceive('get')->with('tax.vat_rate', '18.00')->once()->andReturn('18.00');
    $settings->shouldReceive('get')->with('tax.prices_include_tax', false)->once()->andReturn(false);

    $result = (new InvoiceCalculator(new Money, $settings))->calculate([
        ['quantity' => '3.000', 'unit_price' => '10.005', 'discount_amount' => '1.00', 'tax_type' => 'standard'],
        ['quantity' => '1.000', 'unit_price' => '5.00', 'tax_type' => 'exempt'],
        ['quantity' => '1.000', 'unit_price' => '2.00', 'tax_type' => 'zero_rated'],
    ]);

    expect($result['subtotal'])->toBe('37.02')
        ->and($result['discount_total'])->toBe('1.00')
        ->and($result['tax_total'])->toBe('5.22')
        ->and($result['total'])->toBe('41.24')
        ->and($result['lines'][0]['line_subtotal'])->toBe('30.02')
        ->and($result['lines'][0]['line_tax'])->toBe('5.22')
        ->and($result['lines'][1]['line_tax'])->toBe('0.00');
});

test('invoice calculator extracts VAT from inclusive prices and rejects excessive discounts', function () {
    $settings = Mockery::mock(Settings::class);
    $settings->shouldReceive('get')->with('tax.vat_rate', '18.00')->twice()->andReturn('18.00');
    $settings->shouldReceive('get')->with('tax.prices_include_tax', false)->twice()->andReturn(true, false);

    $calculator = new InvoiceCalculator(new Money, $settings);
    $result = $calculator->calculate([
        ['quantity' => '1.000', 'unit_price' => '118.00', 'tax_type' => 'standard'],
    ]);

    expect($result['tax_total'])->toBe('18.00')
        ->and($result['total'])->toBe('118.00');

    expect(fn () => $calculator->calculate([
        ['quantity' => '1.000', 'unit_price' => '10.00', 'discount_amount' => '10.01'],
    ]))->toThrow(InvalidArgumentException::class);
});
