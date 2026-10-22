<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A scheduling clash the planner can resolve if the person confirms — e.g. "Jane is resting
 * that day" or "John is already on Morning; move him?". The page asks, then sends the request
 * again with the named confirm flag set.
 */
class ScheduleConflict extends RuntimeException
{
    public function __construct(string $message, public string $confirm, public array $details = [])
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'code' => 'schedule_conflict',
            'message' => $this->getMessage(),
            'confirm' => $this->confirm,
            'details' => $this->details,
        ], 409);
    }
}
