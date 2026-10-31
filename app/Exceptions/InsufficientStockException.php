<?php

namespace App\Exceptions;

use RuntimeException;

class InsufficientStockException extends RuntimeException
{
    public function __construct(public readonly string $availableQuantity)
    {
        parent::__construct("Insufficient stock. Available quantity: {$availableQuantity}.");
    }
}
