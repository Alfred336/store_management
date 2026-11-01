<div class="space-y-4">
    <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white p-2 shadow-sm dark:border-zinc-700 dark:bg-zinc-900">
        <flux:table>
            <flux:table.columns>
                <flux:table.column><button type="button" wire:click="sort('code')">Code</button></flux:table.column>
                <flux:table.column><button type="button" wire:click="sort('name')">Customer</button></flux:table.column>
                <flux:table.column>Contact</flux:table.column>
                <flux:table.column>Type</flux:table.column>
                <flux:table.column>Credit limit</flux:table.column>
                <flux:table.column>Status</flux:table.column>
                <flux:table.column>Actions</flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @forelse ($customers as $customer)
                    <flux:table.row :key="$customer->id">
                        <flux:table.cell>{{ $customer->code }} @if($customer->is_walk_in)<span class="ms-1 text-xs text-zinc-500">System</span>@endif</flux:table.cell>
                        <flux:table.cell><button type="button" class="text-start font-medium hover:underline" wire:click="show({{ $customer->id }})">{{ $customer->name }}</button></flux:table.cell>
                        <flux:table.cell>{{ $customer->phone ?: ($customer->email ?: '—') }}</flux:table.cell>
                        <flux:table.cell class="capitalize">{{ $customer->type }}</flux:table.cell>
                        <flux:table.cell>{{ $customer->credit_limit }} {{ app(\App\Services\Settings\Settings::class)->get('business.currency', 'TZS') }}</flux:table.cell>
                        <flux:table.cell><span class="rounded-full px-2.5 py-1 text-xs {{ $customer->is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-zinc-500/10 text-zinc-600 dark:text-zinc-300' }}">{{ $customer->is_active ? 'Active' : 'Inactive' }}</span></flux:table.cell>
                        <flux:table.cell>
                            <div class="flex items-center gap-2">
                                @can('update', $customer)<flux:button size="sm" variant="ghost" wire:click="openForm({{ $customer->id }})">Edit</flux:button>@endcan
                                @can('delete', $customer)<flux:button size="sm" variant="danger" wire:click="confirmDelete({{ $customer->id }})">Delete</flux:button>@endcan
                            </div>
                        </flux:table.cell>
                    </flux:table.row>
                @empty
                    <flux:table.row><flux:table.cell colspan="7" class="py-10 text-center text-zinc-500">No customers found.</flux:table.cell></flux:table.row>
                @endforelse
            </flux:table.rows>
        </flux:table>
    </div>
    {{ $customers->links() }}
</div>