<?php

namespace App\Livewire\Actions\Categories;

use App\Models\Category;
use Illuminate\Support\Facades\Gate;

/** Create a category after enforcing the category policy. */
class CreateCategory
{
    /** @param array{name: string} $data */
    public function __invoke(array $data): Category
    {
        Gate::authorize('create', Category::class);

        return Category::query()->create($data);
    }
}
