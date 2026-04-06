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
        $plainToken = trim((string) $request->bearerToken());

        if (!$plainToken) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        $hashedToken = hash('sha256', $plainToken);
        $authenticatable = null;

        if ($type === 'employee') {
            $authenticatable = $this->resolveByToken(Employee::query(), $plainToken, $hashedToken);
        } elseif ($type === 'user') {
            $authenticatable = $this->resolveByToken(User::query(), $plainToken, $hashedToken);
        } else {
            $authenticatable = $this->resolveByToken(User::query(), $plainToken, $hashedToken)
                ?? $this->resolveByToken(Employee::query(), $plainToken, $hashedToken);
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

    private function resolveByToken($query, string $plainToken, string $hashedToken)
    {
        return $query
            ->where(function ($tokenQuery) use ($plainToken, $hashedToken) {
                $tokenQuery->where('api_token', $hashedToken)
                    ->orWhere('api_token', $plainToken);
            })
            ->first();
    }
}
