<?php
// app/Http/Middleware/EnsureUserIsVendor.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsVendor
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!Auth::check()) {
            return response()->json([
                'message' => 'Unauthenticated.',
                'success' => false
            ], 401);
        }

        $user = Auth::user();
        
        // Check if user has role 'vendor'
        if ($user->role !== 'vendor') {
            return response()->json([
                'message' => 'Access denied. Vendor only.',
                'success' => false
            ], 403);
        }

        return $next($request);
    }
}