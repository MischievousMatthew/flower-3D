<?php

namespace App\Http\Middleware;

use App\Support\FriendlyErrors;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The last check before an API answer leaves the server. Many actions
 * catch an error and pass its raw text on ("'error' => $e->getMessage()");
 * this swaps any programmer wording for a plain message with a reference
 * code, drops debug details, and keeps the original for the admin log.
 */
class FriendlyApiErrors
{
    /** Fields that may carry an error's text */
    private const TEXT_FIELDS = ['message', 'error', 'detail', 'details', 'reason'];

    /** Debug output Laravel adds when APP_DEBUG is on */
    private const DEBUG_FIELDS = ['exception', 'file', 'line', 'trace'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! $response instanceof JsonResponse) {
            return $response;
        }

        $data = $response->getData(true);
        if (! is_array($data)) {
            return $response;
        }

        $status = $response->getStatusCode();
        $changed = false;
        // Only Laravel's debug output has both; a "file" field on its own is ordinary data
        if (array_key_exists('exception', $data) && array_key_exists('trace', $data)) {
            FriendlyErrors::remember($request, is_string($data['message'] ?? null) ? $data['message'] : null, $response->exception ?? null);
            foreach (self::DEBUG_FIELDS as $field) {
                unset($data[$field]);
            }
            $changed = true;
        }

        $technical = collect(self::TEXT_FIELDS)
            ->first(fn ($f) => isset($data[$f]) && is_string($data[$f]) && FriendlyErrors::isTechnical($data[$f]));

        if ($technical !== null) {
            FriendlyErrors::remember($request, $data[$technical], $response->exception ?? null);
            foreach (self::TEXT_FIELDS as $field) {
                if (isset($data[$field]) && is_string($data[$field]) && FriendlyErrors::isTechnical($data[$field])) {
                    unset($data[$field]);
                }
            }
            $data['message'] = FriendlyErrors::message($request, $response->exception ?? null);
            $data['reference'] = FriendlyErrors::reference($request);
            $changed = true;
        } elseif ($status >= 500 && $status !== 503) {
            // A server error with a readable message still gets a code people can quote
            // (503 is a pause or maintenance with its own message, not a failure)
            $reference = FriendlyErrors::reference($request);
            $message = is_string($data['message'] ?? null) ? trim($data['message']) : '';
            $data['message'] = match (true) {
                str_contains($message, $reference) => $message,
                $message === '' || FriendlyErrors::isFrameworkDefault($message) => FriendlyErrors::message($request, $response->exception ?? null),
                default => rtrim($message, '.') . ". If it keeps happening, contact support and mention code {$reference}.",
            };
            $data['reference'] = $reference;
            $changed = true;
        } elseif (FriendlyErrors::isFrameworkDefault($data['message'] ?? null) && FriendlyErrors::forStatus($status)) {
            $data['message'] = FriendlyErrors::forStatus($status);
            $changed = true;
        }

        if ($changed) {
            $response->setData($data);
        }

        return $response;
    }
}
