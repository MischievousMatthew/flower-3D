<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The ERP endpoints (HR, payroll, procurement, warehouses, deliveries, CRM,
 * finance) belong to a shop: only its owner (a vendor account) or one of its
 * employees may call them.
 *
 * employee.module alone isn't enough: it checks employees' permissions and
 * lets every other account through, and the per-shop filter on the models
 * (BelongsToOwner) doesn't apply to customers or admins, so a customer could
 * otherwise read or change any shop's records.
 */
class EnsureErpActor
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();

        $allowed = ($actor instanceof Employee && $actor->owner_id)
            || ($actor instanceof User && $actor->role === User::ROLE_VENDOR);

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'code' => 'erp_only',
                'message' => 'This is only for shop owners and their staff.',
            ], 403);
        }

        return $next($request);
    }
}
