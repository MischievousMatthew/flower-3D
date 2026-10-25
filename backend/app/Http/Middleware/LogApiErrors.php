<?php

namespace App\Http\Middleware;

use App\Models\ApiErrorLog;
use App\Models\Employee;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps a record of API requests that ended in a server error (5xx), and of
 * any programmer error that was caught and turned into a plain message
 * (App\Support\FriendlyErrors), under the reference code the person saw.
 */
class LogApiErrors
{
    /** Errors older than this are cleared now and then, to keep the table small */
    private const KEEP_DAYS = 90;

    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        $caught = $request->attributes->has('error_original');
        if ($response->getStatusCode() < 500 && ! $caught) {
            return;
        }

        try {
            $exception = $request->attributes->get('error_exception') ?? $response->exception ?? null;
            $payload = json_decode((string) $response->getContent(), true);
            $message = $request->attributes->get('error_original') ?: $exception?->getMessage() ?: ($payload['message'] ?? $payload['error'] ?? null);
            $started = $request->attributes->get('started_at');
            [$type, $name, $userId, $employeeId] = $this->account($request->user());

            ApiErrorLog::create([
                'method' => $request->method(),
                'path' => Str::limit('/' . ltrim($request->path(), '/'), 250, ''),
                'action' => $request->route() ? Str::limit($request->route()->getActionName(), 185, '') : null,
                'status_code' => $response->getStatusCode(),
                'exception' => $exception ? Str::limit(get_class($exception), 185, '') : null,
                'message' => $message ? Str::limit(trim(preg_replace('/\s+/', ' ', (string) $message)), 495) : null,
                'user_id' => $userId,
                'ip_address' => $request->ip(),
                'duration_ms' => $started ? (int) round((microtime(true) - $started) * 1000) : null,
                'reference' => $request->attributes->get('error_reference') ?? ($payload['reference'] ?? null),
                'account_type' => $type,
                'account_name' => $name ? Str::limit($name, 117) : null,
                'employee_id' => $employeeId,
                'location' => $exception ? Str::limit(basename($exception->getFile()) . ':' . $exception->getLine(), 185, '') : null,
                'shown_message' => is_string($payload['message'] ?? null) ? Str::limit($payload['message'], 297) : null,
            ]);

            if (random_int(1, 200) === 1) {
                ApiErrorLog::where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
            }
        } catch (\Throwable) {
            // If the database itself is the problem there's nowhere to record it;
            // the Laravel log still has it.
        }
    }

    /**
     * Which kind of account hit the error. Shops and staff are named so an
     * admin can follow up; customers stay unnamed here (their details are masked).
     */
    private function account($user): array
    {
        if ($user instanceof Employee) {
            return ['staff', $user->name, null, $user->id];
        }
        if (! $user instanceof User) {
            return ['guest', null, null, null];
        }

        return match ($user->role) {
            User::ROLE_VENDOR => ['vendor', $user->vendorApplication?->store_name ?: $user->name, $user->id, null],
            User::ROLE_ADMIN => ['admin', $user->name, $user->id, null],
            default => ['customer', null, $user->id, null],
        };
    }
}
