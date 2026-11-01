<flux:modal name="category-details" class="w-full max-w-lg">
    @if ($selected)
        <div class="space-y-5"><div><flux:heading size="lg">{{ $selected->name }}</flux:heading><flux:subheading>Category details</flux:subheading></div>
            <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ $selected->products_count }} associated products</p>
            <flux:modal.close><flux:button variant="primary">Done</flux:button></flux:modal.close>
        </div>
    @endif
</flux:modal>