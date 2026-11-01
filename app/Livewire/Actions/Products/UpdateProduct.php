<?php

namespace App\Livewire\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Update a product and packaging conversions atomically, retaining stock identity after movement. */
class UpdateProduct
{
    /** @param array<string, mixed> $data */
    public function __invoke(Product $product, array $data): Product
    {
        Gate::authorize('update', $product);

        $packagingUnits = $data['packaging_units'] ?? [];
        unset($data['packaging_units']);

        return DB::transaction(function () use ($product, $data, $packagingUnits): Product {
            $lockedProduct = Product::query()->whereKey($product->id)->lockForUpdate()->firstOrFail();

            if ($lockedProduct->stockMovements()->exists()) {
                $errors = [];

                if ((int) $data['base_unit_id'] !== $lockedProduct->base_unit_id) {
                    $errors['base_unit_id'] = ['The base unit cannot be changed after stock movements exist.'];
                }

                if ((bool) $data['track_batches'] !== $lockedProduct->track_batches) {
                    $errors['track_batches'] = ['Batch tracking cannot be changed after stock movements exist.'];
                }

                if ($errors !== []) {
                    throw ValidationException::withMessages($errors);
                }
            }

            $lockedProduct->update($data);
            $lockedProduct->productUnits()->delete();
            $lockedProduct->productUnits()->createMany($packagingUnits);

            return $lockedProduct;
        });
    }
}
