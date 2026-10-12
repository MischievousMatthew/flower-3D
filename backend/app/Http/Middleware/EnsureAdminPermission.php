<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * admin.can:orders.view            — needs that permission
 * admin.can:orders.view|finance.view — needs any one of them
 */
class EnsureAdminPermission
{
    public function handle(Request $request, Closure $next, string $permissions): Response
    {
        $user = $request->user();

        if (!$user instanceof User || $user->role !== User::ROLE_ADMIN) {
            return response()->json(['success' => false, 'message' => 'Unauthorized role'], 403);
        }

        foreach (explode('|', $permissions) as $permission) {
            if ($user->adminCan($permission)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'code' => 'forbidden_permission',
            'message' => "Your admin role doesn't allow this. Ask a super admin if you need it.",
        ], 403);
    }
}
