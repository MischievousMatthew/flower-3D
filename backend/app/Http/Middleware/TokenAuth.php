<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TokenAuth
{
    public function handle(Request $request, Closure $next, ?string $type = null): Response
    {
        $plainToken = $request->bearerToken();

        if (!$plainToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $hashedToken = hash('sha256', $plainToken);
        $authenticatable = null;

        if ($type === 'employee') {
            $authenticatable = Employee::where('api_token', $hashedToken)->first();
        } elseif ($type === 'user') {
            $authenticatable = User::where('api_token', $hashedToken)->first();
        } else {
            $authenticatable = User::where('api_token', $hashedToken)->first()
                ?? Employee::where('api_token', $hashedToken)->first();
        }

        if (!$authenticatable) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        Auth::setUser($authenticatable);
        $request->setUserResolver(fn () => $authenticatable);

        return $next($request);
    }
}
