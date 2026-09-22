<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Services\SubscriptionAccessService;
use App\Subscriptions\SubscriptionPlans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeModuleAccess
{
    public function handle(Request $request, Closure $next, string $module, string $permission = 'view'): Response
    {
        $user = $request->user();

        // A subscription is company-level; employee RBAC below remains the
        // second, more granular gate and is not changed by this stage.
        $subscriptionModule = SubscriptionPlans::canonicalModule($module);
        if (! app(SubscriptionAccessService::class)->canAccess($user, $subscriptionModule)) {
            return response()->json([
                'success' => false,
                'message' => 'Your company subscription does not include this module.',
                'code' => 'subscription_module_unavailable',
            ], 403);
        }

        if (! $user instanceof Employee) {
            return $next($request);
        }

        $allowed = $user->hasModulePermission($module, $permission);

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        return $next($request);
    }
}
