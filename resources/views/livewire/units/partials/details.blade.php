<flux:modal name="unit-details" class="w-full max-w-lg">
    @if ($selected)
        <div class="space-y-5"><div><flux:heading size="lg">{{ $selected->name }}</flux:heading><flux:subheading>Unit details</flux:subheading></div>
            <dl class="grid grid-cols-2 gap-4 text-sm"><div><dt class="text-zinc-500">Abbreviation</dt><dd class="mt-1 font-medium">{{ $selected->abbreviation }}</dd></div><div><dt class="text-zinc-500">Base products</dt><dd class="mt-1 font-medium">{{ $selected->products_count }}</dd></div></dl>
            <flux:modal.close><flux:button variant="primary">Done</flux:button></flux:modal.close>
        </div>
    @endif
</flux:modal>