<?php

namespace App\Livewire\Actions\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\Gate;

/** Delete a category only when it has no products. */
class DeleteCategory
{
    public function __invoke(Category $category): bool
    {
        Gate::authorize('delete', $category);

        if ($category->products()->exists()) {
            return false;
        }

        return (bool) $category->delete();
    }
}
