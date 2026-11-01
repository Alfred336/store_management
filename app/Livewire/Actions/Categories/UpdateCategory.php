<?php

namespace App\Livewire\Actions\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\Gate;

/** Update a category after enforcing its policy. */
class UpdateCategory
{
    /** @param array{name: string} $data */
    public function __invoke(Category $category, array $data): Category
    {
        Gate::authorize('update', $category);
        $category->update($data);

        return $category;
    }
}
