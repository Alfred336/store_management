<div class="mx-auto w-full max-w-5xl space-y-6 p-6 lg:p-10">
    <div>
        <flux:heading size="xl">Business settings</flux:heading>
        <flux:subheading>Company profile, tax defaults, currency and document numbering.</flux:subheading>
    </div>

    <form wire:submit="save" class="space-y-8">
        <section class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 md:grid-cols-2">
            <flux:heading class="md:col-span-2">Company profile</flux:heading>
            <flux:input wire:model="businessName" label="Business name" required />
            <flux:input wire:model="businessEmail" label="Email" type="email" />
            <flux:input wire:model="businessPhone" label="Phone" />
            <flux:input wire:model="currency" label="Currency code" maxlength="3" required />
            <flux:input wire:model="businessTin" label="TIN" />
            <flux:input wire:model="businessVrn" label="VRN" />
            <flux:textarea wire:model="businessAddress" label="Address" rows="3" class="md:col-span-2" />
        </section>

        <section class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 md:grid-cols-2">
            <flux:heading class="md:col-span-2">Tax defaults</flux:heading>
            <flux:input wire:model="vatRate" label="Standard VAT rate (%)" type="number" min="0" max="100" step="0.01" required />
            <flux:checkbox wire:model="pricesIncludeTax" label="Product prices include tax" />
        </section>

        <section class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 md:grid-cols-2">
            <flux:heading class="md:col-span-2">Document numbering</flux:heading>
            <flux:input wire:model="invoicePrefix" label="Invoice prefix" required />
            <flux:input wire:model="receiptPrefix" label="Receipt prefix" required />
            <flux:input wire:model="paymentPrefix" label="Payment prefix" required />
            <flux:input wire:model="quotationPrefix" label="Quotation prefix" required />
            <flux:input wire:model="numberDigits" label="Sequence digits" type="number" min="1" max="12" required />
            <flux:checkbox wire:model="resetYearly" label="Reset sequence each year" />
        </section>

        <section class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 md:grid-cols-2">
            <flux:heading class="md:col-span-2">Quotations</flux:heading>
            <flux:input wire:model="quotationValidityDays" label="Default validity (days)" type="number" min="1" max="365" required />
            <flux:textarea wire:model="quotationDefaultTerms" label="Default quotation terms" rows="3" class="md:col-span-2" placeholder="Standard terms or conditions to pre-fill on new quotations…" />
        </section>

        <section class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 md:grid-cols-2">
            <flux:heading class="md:col-span-2">Customer credit control</flux:heading>
            <flux:select wire:model="creditLimitMode" label="When a credit sale exceeds the limit">
                <option value="warn">Warn and allow</option>
                <option value="block">Block the sale</option>
            </flux:select>
        </section>

        <section class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-6 dark:border-zinc-700 dark:bg-zinc-900 md:grid-cols-2">
            <flux:heading class="md:col-span-2">Invoice printing defaults</flux:heading>
            <flux:select wire:model.live="defaultTemplate" label="Default template">
                <option value="classic">Classic</option>
                <option value="modern">Modern</option>
                <option value="minimal">Minimal</option>
                <option value="compact">Compact (A5)</option>
                <option value="thermal">Thermal receipt</option>
            </flux:select>
            <flux:select wire:model="defaultPaper" label="Default paper size">
                <option value="A4">A4</option>
                <option value="A5">A5</option>
                <option value="80mm">80 mm receipt</option>
                <option value="58mm">58 mm receipt</option>
            </flux:select>
            <flux:select wire:model="receiptWidth" label="POS receipt width">
                <option value="80mm">80 mm</option>
                <option value="58mm">58 mm</option>
            </flux:select>
            <flux:textarea wire:model="bankDetails" label="Bank details (shown on invoices)" rows="3" class="md:col-span-2" />
            <flux:textarea wire:model="footerTerms" label="Default footer / payment terms" rows="2" class="md:col-span-2" />
        </section>

        <div class="flex justify-end"><flux:button variant="primary" type="submit">Save settings</flux:button></div>
    </form>
</div>