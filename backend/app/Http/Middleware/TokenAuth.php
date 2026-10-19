<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\User;
use App\Services\AccountSuspensionService;
use App\Support\SingleSession;
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
            // Signed out because the account logged in on another browser: say so
            if ($type !== 'employee' && SingleSession::replacedBy($hashedToken)) {
                return response()->json([
                    'success' => false,
                    'code' => 'session_replaced',
                    'message' => SingleSession::REPLACED_MESSAGE,
                ], 401);
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        if (AccountSuspensionService::isBlocked($authenticatable)) {
            return response()->json([
                'success' => false,
                'code' => 'account_suspended',
                'message' => AccountSuspensionService::messageFor($authenticatable),
            ], 403);
        }

        if ($authenticatable instanceof User) {
            SingleSession::touch($authenticatable);
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
