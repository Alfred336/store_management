<?php

namespace App\Services\Invoicing;

use App\Services\Money\Money;
use App\Services\Settings\Settings;
use InvalidArgumentException;

/** Calculate invoice amounts using decimal strings and per-line rounding. */
class InvoiceCalculator
{
    public function __construct(private readonly Money $money, private readonly Settings $settings) {}

    /**
     * @param  array<int, array{quantity: string|int, unit_price: string|int, discount_amount?: string|int, tax_type?: string}>  $lines
     * @return array{subtotal: string, discount_total: string, tax_total: string, total: string, lines: array<int, array{line_subtotal: string, discount_amount: string, line_tax: string, line_total: string} & array<string, mixed>>}
     */
    public function calculate(array $lines): array
    {
        $vatRate = $this->money->decimal((string) $this->settings->get('tax.vat_rate', '18.00'));
        $pricesIncludeTax = (bool) $this->settings->get('tax.prices_include_tax', false);
        $subtotal = '0.00';
        $discountTotal = '0.00';
        $taxTotal = '0.00';
        $grandTotal = '0.00';
        $calculatedLines = [];

        foreach ($lines as $line) {
            $quantity = $this->money->decimal($line['quantity']);
            $unitPrice = $this->money->decimal($line['unit_price']);
            $discount = $this->money->round($line['discount_amount'] ?? '0');
            $lineSubtotal = $this->money->round(bcmul($quantity, $unitPrice, 8));

            if (bccomp($quantity, '0', 3) <= 0 || bccomp($unitPrice, '0', 2) < 0) {
                throw new InvalidArgumentException('Invoice quantity must be positive and unit price cannot be negative.');
            }

            if (bccomp($discount, '0', 2) < 0 || $this->money->compare($discount, $lineSubtotal) > 0) {
                throw new InvalidArgumentException('A line discount must be between zero and the line subtotal.');
            }

            $taxType = $line['tax_type'] ?? 'standard';

            if (! in_array($taxType, ['standard', 'exempt', 'zero_rated'], true)) {
                throw new InvalidArgumentException('Unsupported product tax type.');
            }

            $netBeforeTax = $this->money->sub($lineSubtotal, $discount);
            $lineTax = '0.00';

            if ($taxType === 'standard' && bccomp($vatRate, '0', 2) > 0) {
                if ($pricesIncludeTax) {
                    $divisor = bcadd('1', bcdiv($vatRate, '100', 8), 8);
                    $exclusive = bcdiv($netBeforeTax, $divisor, 8);
                    $lineTax = $this->money->round(bcsub($netBeforeTax, $exclusive, 8));
                } else {
                    $lineTax = $this->money->percent($netBeforeTax, $vatRate);
                }
            }

            $lineTotal = $pricesIncludeTax
                ? $netBeforeTax
                : $this->money->add($netBeforeTax, $lineTax);

            $subtotal = $this->money->add($subtotal, $lineSubtotal);
            $discountTotal = $this->money->add($discountTotal, $discount);
            $taxTotal = $this->money->add($taxTotal, $lineTax);
            $grandTotal = $this->money->add($grandTotal, $lineTotal);
            $calculatedLines[] = array_merge($line, [
                'line_subtotal' => $lineSubtotal,
                'discount_amount' => $discount,
                'line_tax' => $lineTax,
                'line_total' => $lineTotal,
            ]);
        }

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'tax_total' => $taxTotal,
            'total' => $grandTotal,
            'lines' => $calculatedLines,
        ];
    }
}
