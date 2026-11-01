<?php

namespace App\Policies;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('invoices.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.view');
    }

    public function create(User $user): bool
    {
        return $user->can('invoices.create');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft && $user->can('invoices.update');
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $invoice->status === InvoiceStatus::Draft && $user->can('invoices.update');
    }

    public function issue(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.issue');
    }

    public function void(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.void');
    }

    public function print(User $user, Invoice $invoice): bool
    {
        return $user->can('invoices.print');
    }
}
