<flux:modal name="customer-details" class="w-full max-w-3xl" variant="flyout" position="right" scroll="body">
    @if ($selectedCustomer && $statement)
        <div class="space-y-6">
            <div class="flex items-start justify-between gap-4">
                <div><flux:heading size="lg">{{ $selectedCustomer->name }}</flux:heading><flux:subheading>{{ $selectedCustomer->code }} · {{ ucfirst($selectedCustomer->type) }}</flux:subheading></div>
                @if($selectedCustomer->is_walk_in)<span class="rounded-full bg-sky-500/10 px-2.5 py-1 text-xs text-sky-700 dark:text-sky-300">Protected system customer</span>@endif
            </div>
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div><dt class="text-zinc-500">Phone</dt><dd class="mt-1 font-medium">{{ $selectedCustomer->phone ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">Email</dt><dd class="mt-1 font-medium">{{ $selectedCustomer->email ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">TIN</dt><dd class="mt-1 font-medium">{{ $selectedCustomer->tin ?: '—' }}</dd></div>
                <div><dt class="text-zinc-500">Credit limit</dt><dd class="mt-1 font-medium">{{ $selectedCustomer->credit_limit }} {{ app(\App\Services\Settings\Settings::class)->get('business.currency', 'TZS') }}</dd></div>
                <div><dt class="text-zinc-500">Payment terms</dt><dd class="mt-1 font-medium">{{ $selectedCustomer->payment_terms_days }} days</dd></div>
                <div><dt class="text-zinc-500">Outstanding balance</dt><dd class="mt-1 font-semibold">{{ $statement['balance'] }} {{ app(\App\Services\Settings\Settings::class)->get('business.currency', 'TZS') }}</dd></div>
                <div><dt class="text-zinc-500">Total spent</dt><dd class="mt-1 font-medium">{{ $statement['total_spent'] }} {{ app(\App\Services\Settings\Settings::class)->get('business.currency', 'TZS') }}</dd></div>
                <div><dt class="text-zinc-500">Last purchase</dt><dd class="mt-1 font-medium">{{ $statement['last_purchase_date'] ?: '—' }}</dd></div>
                <div class="sm:col-span-2"><dt class="text-zinc-500">Address</dt><dd class="mt-1 whitespace-pre-line font-medium">{{ $selectedCustomer->address ?: '—' }}</dd></div>
            </dl>
            @if ($selectedCustomer->notes)<div><flux:text class="font-medium">Notes</flux:text><p class="mt-1 whitespace-pre-line text-sm">{{ $selectedCustomer->notes }}</p></div>@endif
            <section class="space-y-2">
                <flux:heading>Recent invoices</flux:heading>
                @forelse ($statement['invoices'] as $invoice)
                    <div class="flex justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800"><span>{{ $invoice['number'] ?? '—' }} · {{ $invoice['issue_date'] ?? '—' }}</span><span>{{ $invoice['total'] }} · {{ $invoice['status'] }}</span></div>
                @empty
                    <p class="text-sm text-zinc-500">No invoices yet.</p>
                @endforelse
            </section>
            <div class="flex justify-end"><flux:button :href="route('customers.statement', $selectedCustomer)" target="_blank" icon="document-arrow-down">Download statement PDF</flux:button></div>
            <section class="space-y-2">
                <flux:heading>Recent payments</flux:heading>
                @forelse ($statement['payments'] as $payment)
                    <div class="flex justify-between gap-3 rounded-lg bg-zinc-50 px-3 py-2 text-sm dark:bg-zinc-800"><span>{{ $payment['number'] }}@if(!empty($payment['reference'])) · {{ $payment['reference'] }}@endif · {{ $payment['received_at'] }}</span><span>{{ $payment['amount'] }} · {{ $payment['method'] }}</span></div>
                @empty
                    <p class="text-sm text-zinc-500">No payments yet.</p>
                @endforelse
            </section>
            <div class="flex justify-end"><flux:button variant="primary" wire:click="closeDetails">Done</flux:button></div>
        </div>
    @endif
</flux:modal>