<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Services\SubscriptionAccessService;
use App\Subscriptions\SubscriptionPlans;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authorizes leave review from the requested state without trusting the
 * controller or frontend to select the employee permission.
 */
class EnsureEmployeeLeaveReviewAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! app(SubscriptionAccessService::class)->canAccess($user, 'leave')) {
            return response()->json([
                'success' => false,
                'message' => 'Your company subscription does not include this module.',
                'code' => 'subscription_module_unavailable',
            ], 403);
        }

        if (! $user instanceof Employee) {
            return $next($request);
        }

        $permission = match ($request->input('status')) {
            'approved' => 'approve',
            'rejected' => 'reject',
            default => null,
        };

        if ($permission === null || ! $user->hasModulePermission('leave_management', $permission)) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        return $next($request);
    }
}
