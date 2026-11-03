<?php

namespace App\Exceptions;

/**
 * Not enough stock left for an order (someone else bought it first). The
 * message is written for the customer.
 */
class OutOfStockException extends \RuntimeException
{
    public function __construct(public readonly string $productName, public readonly int $available)
    {
        parent::__construct($available > 0
            ? "Only {$available} {$productName} left. Lower the quantity and try again."
            : "{$productName} just sold out. Remove it from your order to continue.");
    }
}
