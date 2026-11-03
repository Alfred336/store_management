<?php

namespace App\Livewire\Actions\Settings;

use App\Models\Setting;
use App\Services\Settings\Settings;
use Illuminate\Support\Facades\Gate;

/** Update company profile and application-wide tax/numbering preferences. */
class UpdateBusinessSettings
{
    /** @param array<string, mixed> $data */
    public function __invoke(array $data): void
    {
        Gate::authorize('update', Setting::class);

        app(Settings::class)->putMany([
            'business.name' => $data['business_name'],
            'business.email' => $data['business_email'],
            'business.phone' => $data['business_phone'],
            'business.address' => $data['business_address'],
            'business.tin' => $data['business_tin'],
            'business.vrn' => $data['business_vrn'],
            'business.currency' => $data['currency'],
            'tax.vat_rate' => $data['vat_rate'],
            'tax.prices_include_tax' => $data['prices_include_tax'],
            'numbering.invoice_prefix' => $data['invoice_prefix'],
            'numbering.receipt_prefix' => $data['receipt_prefix'],
            'numbering.payment_prefix' => $data['payment_prefix'],
            'numbering.reset_yearly' => $data['reset_yearly'],
            'numbering.digits' => $data['number_digits'],
            'customers.credit_limit_mode' => $data['credit_limit_mode'],
            'business.bank_details' => $data['bank_details'],
            'business.footer_terms' => $data['footer_terms'],
            'invoicing.default_template' => $data['default_template'],
            'invoicing.default_paper' => $data['default_paper'],
            'pos.receipt_width' => $data['receipt_width'],
        ]);
    }
}
