<flux:modal name="location-details" class="w-full max-w-lg">
    @if ($selected)
        <div class="space-y-5"><div><flux:heading size="lg">{{ $selected->name }}</flux:heading><flux:subheading class="capitalize">{{ $selected->type }}</flux:subheading></div>
            <dl class="grid grid-cols-2 gap-4 text-sm"><div><dt class="text-zinc-500">Status</dt><dd class="mt-1 font-medium">{{ $selected->is_active ? 'Active' : 'Inactive' }}</dd></div><div><dt class="text-zinc-500">Stock levels</dt><dd class="mt-1 font-medium">{{ $selected->stock_levels_count }}</dd></div><div><dt class="text-zinc-500">Movements</dt><dd class="mt-1 font-medium">{{ $selected->stock_movements_count }}</dd></div></dl>
            <flux:modal.close><flux:button variant="primary">Done</flux:button></flux:modal.close>
        </div>
    @endif
</flux:modal>