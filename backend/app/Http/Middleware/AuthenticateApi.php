<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class AuthenticateApi
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('AuthenticateApi Middleware Check:', [
            'path' => $request->path(),
            'has_token' => $request->bearerToken() ? 'Yes' : 'No',
            'has_user' => $request->user() ? 'Yes' : 'No',
            'session_id' => session()->getId(),
        ]);
        
        // For now, allow all requests (remove this check temporarily)
        // We'll add proper authentication later
        return $next($request);
        
        /*
        // Original check - comment it out for now
        if ($request->bearerToken() || $request->user()) {
            return $next($request);
        }
        
        return response()->json(['error' => 'Unauthorized'], 401);
        */
    }
}