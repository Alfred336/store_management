<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Create a product and its packaging conversions atomically. */
class CreateProduct
{
    /** @param array<string, mixed> $data */
    public function __invoke(array $data): Product
    {
        Gate::authorize('create', Product::class);

        $packagingUnits = $data['packaging_units'] ?? [];
        unset($data['packaging_units']);

        return DB::transaction(function () use ($data, $packagingUnits): Product {
            $product = Product::query()->create($data);
            $product->productUnits()->createMany($packagingUnits);

            return $product;
        });
    }
}
