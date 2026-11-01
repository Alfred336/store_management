<?php

namespace App\Livewire\Actions\Customers;

use App\Models\Customer;
use App\Services\Invoicing\DocumentNumberingService;
use Illuminate\Support\Facades\DB;

/** Create the single protected Walk-in Customer idempotently. */
class EnsureWalkInCustomer
{
    public function __invoke(): Customer
    {
        return DB::transaction(function (): Customer {
            // Serialize concurrent setup/seeder runs on an always-present settings row.
            DB::table('settings')->where('key', 'business.currency')->lockForUpdate()->first();

            $existing = Customer::query()->where('is_walk_in', true)->first();

            if ($existing !== null) {
                return $existing;
            }

            return Customer::query()->create([
                'code' => app(DocumentNumberingService::class)->next('customer'),
                'name' => 'Walk-in Customer',
                'type' => 'individual',
                'is_active' => true,
                'is_walk_in' => true,
            ]);
        });
    }
}
