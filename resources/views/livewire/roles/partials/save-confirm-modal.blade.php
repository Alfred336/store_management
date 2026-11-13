<flux:modal name="save-confirm" class="w-full max-w-md">
    <div class="space-y-6 p-6">
        <flux:heading size="lg">Confirm permission changes</flux:heading>

        <flux:text>
            This role is assigned to users. The following permissions will be
            <strong>removed</strong> and will take effect on their next request:
        </flux:text>

        @if (count($removedPermissionLabels) > 0)
            <ul class="list-inside list-disc space-y-1 text-sm text-zinc-700 dark:text-zinc-300">
                @foreach ($removedPermissionLabels as $label)
                    <li>{{ $label }}</li>
                @endforeach
            </ul>
        @endif

        <div class="flex justify-end gap-3">
            <flux:button wire:click="cancelSave">Cancel</flux:button>
            <flux:button variant="primary" wire:click="saveConfirmed">Save anyway</flux:button>
        </div>
    </div>
</flux:modal>
