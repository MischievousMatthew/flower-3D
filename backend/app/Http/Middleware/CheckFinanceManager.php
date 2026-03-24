<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckFinanceManager
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if (!$user) {
            return response()->json([
                'message' => 'Unauthenticated.'
            ], 401);
        }

        $isProcurement = strtolower(trim($user->department)) === 'procurement';
        $isFinanceManager = strtolower(trim($user->role)) === 'finance manager';

        if (!($isProcurement && $isFinanceManager)) {
            return response()->json([
                'message' => 'Forbidden. This module is restricted to Finance Managers only.',
                'required_department' => 'Procurement',
                'required_role' => 'Finance Manager',
                'your_department' => $user->department,
                'your_role' => $user->role
            ], 403);
        }

        return $next($request);
    }
}