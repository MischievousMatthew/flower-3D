<?php

namespace App\Http\Middleware;

use App\Models\ApiErrorLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/** Keeps a record of API requests that ended in a server error (5xx) */
class LogApiErrors
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($response->getStatusCode() < 500) {
            return;
        }

        try {
            $exception = $response->exception ?? null;
            $payload = json_decode((string) $response->getContent(), true);
            $message = $exception?->getMessage() ?: ($payload['message'] ?? $payload['error'] ?? null);
            $started = $request->attributes->get('started_at');
            $user = $request->user();

            ApiErrorLog::create([
                'method' => $request->method(),
                'path' => Str::limit('/' . ltrim($request->path(), '/'), 250, ''),
                'status_code' => $response->getStatusCode(),
                'exception' => $exception ? Str::limit(get_class($exception), 185, '') : null,
                'message' => $message ? Str::limit(trim(preg_replace('/\s+/', ' ', (string) $message)), 495) : null,
                'user_id' => $user instanceof \App\Models\User ? $user->id : null,
                'ip_address' => $request->ip(),
                'duration_ms' => $started ? (int) round((microtime(true) - $started) * 1000) : null,
            ]);
        } catch (\Throwable) {
            // If the database itself is the problem there's nowhere to record it;
            // the Laravel log still has it.
        }
    }
}
