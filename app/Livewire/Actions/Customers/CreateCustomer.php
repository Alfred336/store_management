<?php

namespace App\Livewire\Actions\Customers;

use App\Models\Customer;
use App\Services\Invoicing\DocumentNumberingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Create a customer and allocate its unique code in the same transaction. */
class CreateCustomer
{
    /** @param array<string, mixed> $data */
    public function __invoke(array $data): Customer
    {
        Gate::authorize('create', Customer::class);

        if (strcasecmp(trim((string) ($data['name'] ?? '')), 'Walk-in Customer') === 0) {
            throw ValidationException::withMessages(['name' => 'The Walk-in Customer name is reserved for the protected system record.']);
        }

        return DB::transaction(fn (): Customer => Customer::query()->create([
            ...$data,
            'code' => app(DocumentNumberingService::class)->next('customer'),
            'is_walk_in' => false,
        ]));
    }
}
