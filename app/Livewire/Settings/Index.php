<?php

namespace App\Livewire\Settings;

use App\Livewire\Actions\Settings\UpdateBusinessSettings;
use App\Models\Setting;
use App\Services\Settings\Settings as SettingsService;
use Flux\Flux;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Maintain company profile, VAT and document numbering preferences. */
#[Title('Business settings')]
class Index extends Component
{
    public string $businessName = '';

    public string $businessEmail = '';

    public string $businessPhone = '';

    public string $businessAddress = '';

    public string $businessTin = '';

    public string $businessVrn = '';

    public string $currency = 'TZS';

    public string $vatRate = '18.00';

    public bool $pricesIncludeTax = false;

    public string $invoicePrefix = 'INV';

    public string $receiptPrefix = 'RCT';

    public string $paymentPrefix = 'PAY';

    public bool $resetYearly = true;

    public string $numberDigits = '6';

    public string $creditLimitMode = 'warn';

    public function mount(SettingsService $settings): void
    {
        $this->authorize('viewAny', Setting::class);

        $this->businessName = (string) $settings->get('business.name', '');
        $this->businessEmail = (string) $settings->get('business.email', '');
        $this->businessPhone = (string) $settings->get('business.phone', '');
        $this->businessAddress = (string) $settings->get('business.address', '');
        $this->businessTin = (string) $settings->get('business.tin', '');
        $this->businessVrn = (string) $settings->get('business.vrn', '');
        $this->currency = (string) $settings->get('business.currency', 'TZS');
        $this->vatRate = (string) $settings->get('tax.vat_rate', '18.00');
        $this->pricesIncludeTax = (bool) $settings->get('tax.prices_include_tax', false);
        $this->invoicePrefix = (string) $settings->get('numbering.invoice_prefix', 'INV');
        $this->receiptPrefix = (string) $settings->get('numbering.receipt_prefix', 'RCT');
        $this->paymentPrefix = (string) $settings->get('numbering.payment_prefix', 'PAY');
        $this->resetYearly = (bool) $settings->get('numbering.reset_yearly', true);
        $this->numberDigits = (string) $settings->get('numbering.digits', '6');
        $this->creditLimitMode = (string) $settings->get('customers.credit_limit_mode', 'warn');
    }

    public function save(UpdateBusinessSettings $updateBusinessSettings): void
    {
        $this->authorize('update', Setting::class);

        $data = $this->validate([
            'businessName' => ['required', 'string', 'max:255'],
            'businessEmail' => ['nullable', 'email', 'max:255'],
            'businessPhone' => ['nullable', 'string', 'max:50'],
            'businessAddress' => ['nullable', 'string', 'max:2000'],
            'businessTin' => ['nullable', 'string', 'max:50'],
            'businessVrn' => ['nullable', 'string', 'max:50'],
            'currency' => ['required', 'string', 'size:3', 'alpha:upper'],
            'vatRate' => ['required', 'numeric', 'decimal:0,2', 'between:0,100'],
            'pricesIncludeTax' => ['required', 'boolean'],
            'invoicePrefix' => ['required', 'string', 'max:12', 'alpha:upper'],
            'receiptPrefix' => ['required', 'string', 'max:12', 'alpha:upper'],
            'paymentPrefix' => ['required', 'string', 'max:12', 'alpha:upper'],
            'resetYearly' => ['required', 'boolean'],
            'numberDigits' => ['required', 'integer', 'between:1,12'],
            'creditLimitMode' => ['required', Rule::in(['warn', 'block'])],
        ]);

        $updateBusinessSettings([
            'business_name' => $data['businessName'],
            'business_email' => $data['businessEmail'] ?: null,
            'business_phone' => $data['businessPhone'] ?: null,
            'business_address' => $data['businessAddress'] ?: null,
            'business_tin' => $data['businessTin'] ?: null,
            'business_vrn' => $data['businessVrn'] ?: null,
            'currency' => $data['currency'],
            'vat_rate' => $data['vatRate'],
            'prices_include_tax' => (bool) $data['pricesIncludeTax'],
            'invoice_prefix' => $data['invoicePrefix'],
            'receipt_prefix' => $data['receiptPrefix'],
            'payment_prefix' => $data['paymentPrefix'],
            'reset_yearly' => (bool) $data['resetYearly'],
            'number_digits' => (int) $data['numberDigits'],
            'credit_limit_mode' => $data['creditLimitMode'],
        ]);

        Flux::toast(variant: 'success', text: 'Business settings saved.');
    }

    public function render(): View
    {
        $this->authorize('viewAny', Setting::class);

        return view('livewire.settings.index');
    }
}
