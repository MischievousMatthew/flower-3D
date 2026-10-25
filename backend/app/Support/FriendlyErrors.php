<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * Vendors, customers and staff never see programmer errors ("SQLSTATE…",
 * "Call to undefined method…"). They get a plain message and a short
 * reference code; the real error is kept for admins under that code
 * (Admin → System health → Errors).
 */
class FriendlyErrors
{
    /** Signs that a message was written for programmers, not people */
    private const TECHNICAL = [
        '/SQLSTATE\[/',
        '/\bSQL:/',
        '/\(Connection: /',
        '/\b[A-Z]\w*(Exception|Error)\b/',
        '/\.php\b/',
        '/ on line \d+/',
        '/Call to (undefined|a member function|private|protected)/',
        '/Undefined (variable|array key|offset|index|property|method|constant)/',
        '/Attempt to (read|assign) property/',
        '/must be of type /',
        '/Too few arguments/',
        '/Class "?[\w\\\\]+"? not found/',
        '/Target class \[/',
        '/Route \[[^\]]+\] not defined/',
        '/No query results for model/',
        '/Allowed memory size/',
        '/Maximum execution time/',
        '/Array to string conversion/',
        '/Division by zero/',
        '/(column|relation|table) "?[\w.]+"? (does not exist|doesn\'t exist)/i',
        '/Unknown column/',
        '/Integrity constraint/',
        '/Base table or view/',
        '/violates (foreign key|unique|not-null|check) constraint/',
        '/syntax error/i',
        '/cURL error \d+/',
        '/Connection (refused|timed out|reset)/i',
        '/could not (connect|translate host)/i',
        '/failed to open stream/i',
        '/stack trace/i',
        '/\b(App|Illuminate|Symfony|GuzzleHttp|Carbon)\\\\/',
    ];

    /** Laravel's own wording for ordinary refusals; reworded by status, not logged */
    private const FRAMEWORK_DEFAULTS = [
        'Unauthenticated.',
        'Unauthorized.',
        'This action is unauthorized.',
        'Too Many Attempts.',
        'Server Error',
        'Not Found',
        'Forbidden',
        'Method Not Allowed',
        'Service Unavailable',
        'Page Expired',
        'CSRF token mismatch.',
        'Content Too Large',
    ];

    public static function isTechnical(?string $message): bool
    {
        if (! is_string($message) || trim($message) === '') {
            return false;
        }
        foreach (self::TECHNICAL as $pattern) {
            if (preg_match($pattern, $message)) {
                return true;
            }
        }

        return false;
    }

    /** "Unauthenticated.", "The route api/x could not be found." and similar */
    public static function isFrameworkDefault(?string $message): bool
    {
        return is_string($message)
            && (in_array(trim($message), self::FRAMEWORK_DEFAULTS, true) || preg_match('/^The route .+ could not be found\.$/', trim($message)));
    }

    /** What people see for an ordinary refusal (signed out, not allowed, too many tries…) */
    public static function forStatus(int $status): ?string
    {
        return match ($status) {
            401 => 'Please sign in again to continue.',
            403 => "You don't have permission to do that.",
            404 => "We couldn't find that. It may have been moved or removed.",
            405 => "That action isn't available.",
            413 => 'That file is too large. Please choose a smaller one.',
            419 => 'This page has expired. Refresh the page and try again.',
            429 => 'Too many tries. Please wait a moment and try again.',
            503 => 'BloomCraft is being updated. Please try again in a few minutes.',
            default => null,
        };
    }

    /** The short code people can quote to support, the same for the whole request */
    public static function reference(Request $request): string
    {
        if (! $request->attributes->has('error_reference')) {
            $request->attributes->set('error_reference', 'BC-' . Str::upper(Str::random(6)));
        }

        return $request->attributes->get('error_reference');
    }

    /** The plain message for something that broke on our side, with its reference code */
    public static function message(Request $request, ?Throwable $e = null): string
    {
        $reference = self::reference($request);
        $what = match (true) {
            self::isConnectionProblem($e) => "We're having trouble connecting right now. Please try again in a minute.",
            $request->isMethod('GET') => "We couldn't load this right now. Please try again.",
            $request->isMethod('DELETE') => "We couldn't remove that. Please try again.",
            default => "We couldn't finish that. Please try again.",
        };

        return "{$what} If it keeps happening, contact support and mention code {$reference}.";
    }

    /** Keeps the original error for the admin log (the first one wins) */
    public static function remember(Request $request, ?string $original, ?Throwable $e = null): void
    {
        self::reference($request);
        if (! $request->attributes->has('error_original')) {
            $request->attributes->set('error_original', $original ?? $e?->getMessage());
        }
        if ($e && ! $request->attributes->has('error_exception')) {
            $request->attributes->set('error_exception', $e);
        }
    }

    private static function isConnectionProblem(?Throwable $e): bool
    {
        if (! $e) {
            return false;
        }
        $message = $e->getMessage();

        return ($e instanceof QueryException && str_starts_with((string) $e->getCode(), '08'))
            || (bool) preg_match('/Connection (refused|timed out|reset)|could not (connect|translate host)|server has gone away|cURL error (6|7|28)\b/i', $message);
    }
}
