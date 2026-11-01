<?php

namespace App\Livewire\Actions\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Update an editable customer; the protected walk-in record is immutable. */
class UpdateCustomer
{
    /** @param array<string, mixed> $data */
    public function __invoke(Customer $customer, array $data): Customer
    {
        Gate::authorize('update', $customer);

        if ($customer->is_walk_in) {
            throw ValidationException::withMessages(['customer' => 'The Walk-in Customer record is protected.']);
        }

        return DB::transaction(function () use ($customer, $data): Customer {
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_walk_in) {
                throw ValidationException::withMessages(['customer' => 'The Walk-in Customer record is protected.']);
            }

            $locked->update($data);

            return $locked;
        });
    }
}
