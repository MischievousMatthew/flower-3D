<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyPayMongoWebhook
{
    public function handle(Request $request, Closure $next)
    {
        // Skip signature verification in local development
        if (app()->environment('local')) {
            return $next($request);
        }

        $secret    = config('services.paymongo.webhook_secret');
        $signature = $request->header('Paymongo-Signature');

        if (!$secret || !$signature) {
            Log::warning('Invalid PayMongo webhook signature', [
                'ip'        => $request->ip(),
                'signature' => $signature,
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Parse signature header
        $parts = [];
        foreach (explode(',', $signature) as $part) {
            [$key, $value] = explode('=', $part, 2);
            $parts[$key] = $value;
        }

        $timestamp        = $parts['t']  ?? '';
        $expectedSig      = $parts['te'] ?? '';
        $payload          = $timestamp . '.' . $request->getContent();
        $computedSig      = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($expectedSig, $computedSig)) {
            Log::warning('Invalid PayMongo webhook signature', [
                'ip'        => $request->ip(),
                'signature' => $signature,
            ]);
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}