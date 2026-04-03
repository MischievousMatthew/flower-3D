<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeModuleAccess
{
    public function handle(Request $request, Closure $next, string $module, string $level = 'view'): Response
    {
        $user = $request->user();

        if (! $user instanceof Employee) {
            return $next($request);
        }

        $allowed = match ($level) {
            'edit' => $user->canEditModule($module),
            'view' => $user->canViewModule($module),
            default => false,
        };

        if (! $allowed) {
            return response()->json([
                'success' => false,
                'message' => 'Forbidden',
            ], 403);
        }

        return $next($request);
    }
}
