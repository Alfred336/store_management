<?php

namespace App\Livewire\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\Gate;

/** Soft-delete a product only if it has no stock movement history. */
class DeleteProduct
{
    public function __invoke(Product $product): bool
    {
        Gate::authorize('delete', $product);

        if ($product->stockMovements()->exists()) {
            return false;
        }

        return (bool) $product->delete();
    }
}
