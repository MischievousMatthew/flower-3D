<?php

namespace App\Services;

use RuntimeException;

/**
 * A code problem the person can act on (wait, ask for a new code, re-type it).
 * The message is safe to show; `status` is the HTTP status to answer with and
 * `retryAfter` the seconds to wait, when waiting is the fix.
 */
class OtpProblem extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 422, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message);
    }
}
