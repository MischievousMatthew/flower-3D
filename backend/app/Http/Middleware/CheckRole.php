<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets the request through only when the signed-in account has one of the roles (read from the database). */
class CheckRole
{
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        if (!in_array($user->role, $roles)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized role'
            ], 403);
        }

        // Anything that goes wrong further on is handled (and hidden from people) by FriendlyApiErrors
        return $next($request);
    }
}
