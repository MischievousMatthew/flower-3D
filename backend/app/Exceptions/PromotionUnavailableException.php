<?php

namespace App\Exceptions;

/**
 * A promotion the order was priced with can't be used any more: its last spot
 * was just taken, or this customer already used it. The message is written
 * for the customer.
 */
class PromotionUnavailableException extends \RuntimeException
{
    public function __construct(public readonly string $promotionName, bool $alreadyUsed = false)
    {
        parent::__construct($alreadyUsed
            ? "You've already used the “{$promotionName}” promotion. Refresh to see the regular price, then place your order again."
            : "The “{$promotionName}” promotion just ran out. Refresh to see the regular price, then place your order again.");
    }
}
