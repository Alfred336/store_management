<?php

namespace Database\Seeders;

use App\Livewire\Actions\Customers\EnsureWalkInCustomer;
use Illuminate\Database\Seeder;

class WalkInCustomerSeeder extends Seeder
{
    public function run(): void
    {
        app(EnsureWalkInCustomer::class)();
    }
}
