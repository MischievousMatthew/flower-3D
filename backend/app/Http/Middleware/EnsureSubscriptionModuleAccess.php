<?php

namespace App\Http\Middleware;

use App\Services\SubscriptionAccessService;
use App\Subscriptions\SubscriptionPlans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscriptionModuleAccess
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $module = SubscriptionPlans::canonicalModule($module);

        if (! app(SubscriptionAccessService::class)->canAccess($request->user(), $module)) {
            return response()->json([
                'success' => false,
                'message' => 'Your company subscription does not include this module.',
                'code' => 'subscription_module_unavailable',
            ], 403);
        }

        return $next($request);
    }
}
