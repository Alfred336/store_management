<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly string $availableQuantity,
        public readonly ?int $productId = null,
        public readonly ?int $locationId = null,
    ) {
        parent::__construct("Insufficient stock. Available quantity: {$availableQuantity}.");
    }
}
