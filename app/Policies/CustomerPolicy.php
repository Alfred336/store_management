<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

/** Authorize customer operations and enforce the immutable walk-in record. */
class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('customers.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        return $user->can('customers.view');
    }

    public function create(User $user): bool
    {
        return $user->can('customers.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        return ! $customer->is_walk_in && $user->can('customers.update');
    }

    public function delete(User $user, Customer $customer): bool
    {
        return ! $customer->is_walk_in && $user->can('customers.delete');
    }
}
