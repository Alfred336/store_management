<?php

namespace App\Livewire\Actions\Customers;

use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Delete an unreferenced customer, preserving records with financial history. */
class DeleteCustomer
{
    public function __invoke(Customer $customer): void
    {
        Gate::authorize('delete', $customer);

        if ($customer->is_walk_in) {
            throw ValidationException::withMessages(['customer' => 'The Walk-in Customer record cannot be deleted.']);
        }

        DB::transaction(function () use ($customer): void {
            $locked = Customer::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();

            if ($locked->is_walk_in) {
                throw ValidationException::withMessages(['customer' => 'The Walk-in Customer record cannot be deleted.']);
            }

            if ($locked->invoices()->exists()) {
                throw ValidationException::withMessages([
                    'customer' => 'This customer has transaction history. Deactivate the customer instead.',
                ]);
            }

            // removed in Phase 4 when payments exist
            if (Schema::hasTable('payments') && Schema::hasColumn('payments', 'customer_id')
                && DB::table('payments')->where('customer_id', $locked->id)->exists()) {
                throw ValidationException::withMessages([
                    'customer' => 'This customer has transaction history. Deactivate the customer instead.',
                ]);
            }

            $locked->delete();
        });
    }
}
