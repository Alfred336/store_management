<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Apply permission-name-based CRUD authorization to one inventory model. */
abstract class InventoryResourcePolicy
{
    abstract protected function resource(): string;

    public function viewAny(User $user): bool
    {
        return $user->can($this->resource().'.view');
    }

    public function view(User $user, Model $model): bool
    {
        return $user->can($this->resource().'.view');
    }

    public function create(User $user): bool
    {
        return $user->can($this->resource().'.create');
    }

    public function update(User $user, Model $model): bool
    {
        return $user->can($this->resource().'.update');
    }

    public function delete(User $user, Model $model): bool
    {
        return $user->can($this->resource().'.delete');
    }
}
