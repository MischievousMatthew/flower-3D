<?php

namespace App\Http\Middleware;

use App\Services\OrderAutoCompleteService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * There's no cron on the free server, so orders out for delivery for 3 days
 * are completed while the app is used: after a response has gone out, at
 * most once every 10 minutes (OrderAutoCompleteService::sweepIfDue).
 */
class AutoCompleteDeliveredOrders
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            app(OrderAutoCompleteService::class)->sweepIfDue();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Order auto-complete sweep failed', ['error' => get_class($e)]);
        }
    }
}
