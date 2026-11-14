<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Only PayMongo can call the webhook. Its Paymongo-Signature header looks like
 *   t=<unix time>,te=<test-mode signature>,li=<live-mode signature>
 * where each signature is HMAC-SHA256 of "<t>.<raw body>" with the webhook
 * secret. Test keys fill in te, live keys fill in li; either must match.
 */
class VerifyPayMongoWebhook
{
    /** A signed webhook older (or newer) than this is refused, so it can't be replayed later */
    private const TOLERANCE_SECONDS = 300;

    public function handle(Request $request, Closure $next)
    {
        // Skip signature verification in local development
        if (app()->environment('local')) {
            return $next($request);
        }

        $secret    = config('services.paymongo.webhook_secret');
        $signature = (string) $request->header('Paymongo-Signature', '');

        $parts = [];
        foreach (explode(',', $signature) as $part) {
            if (str_contains($part, '=')) {
                [$key, $value] = explode('=', trim($part), 2);
                $parts[$key] = $value;
            }
        }

        $timestamp = $parts['t'] ?? '';
        $sent      = array_filter([$parts['li'] ?? '', $parts['te'] ?? '']);

        if (! $secret || ! ctype_digit($timestamp) || ! $sent) {
            return $this->refuse($request, 'missing signature');
        }
        if (abs(time() - (int) $timestamp) > self::TOLERANCE_SECONDS) {
            return $this->refuse($request, 'signature too old');
        }

        $computed = hash_hmac('sha256', $timestamp . '.' . $request->getContent(), $secret);
        foreach ($sent as $candidate) {
            if (hash_equals($computed, $candidate)) {
                return $next($request);
            }
        }

        return $this->refuse($request, 'signature mismatch');
    }

    private function refuse(Request $request, string $why)
    {
        Log::warning('Invalid PayMongo webhook signature', ['ip' => $request->ip(), 'why' => $why]);

        return response()->json(['error' => 'Unauthorized'], 401);
    }
}
