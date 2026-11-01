<?php

namespace App\Http\Controllers\Customers;

use App\Livewire\Actions\Customers\GetCustomerStatement;
use App\Models\Customer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/** Render and download a customer's invoice/payment statement as PDF. */
class CustomerStatementController
{
    public function __invoke(Customer $customer, GetCustomerStatement $getCustomerStatement): Response
    {
        Gate::authorize('view', $customer);
        $statement = $getCustomerStatement($customer, null);

        return Pdf::loadView('pdf.customers.statement', compact('customer', 'statement'))
            ->setPaper('a4')
            ->download("customer-statement-{$customer->code}.pdf");
    }
}
