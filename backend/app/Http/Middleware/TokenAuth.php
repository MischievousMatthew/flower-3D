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
            $authenticatable = $this->resolveByToken(Employee::query(), $hashedToken);
        } elseif ($type === 'user') {
            $authenticatable = $this->resolveByToken(User::query(), $hashedToken);
        } else {
            $authenticatable = $this->resolveByToken(User::query(), $hashedToken)
                ?? $this->resolveByToken(Employee::query(), $hashedToken);
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
            // A token left unused too long stops working here too, not only in the browser
            if (SingleSession::isExpired($authenticatable)) {
                SingleSession::end($authenticatable);

                return response()->json([
                    'success' => false,
                    'code' => 'session_expired',
                    'message' => SingleSession::EXPIRED_MESSAGE,
                ], 401);
            }
            SingleSession::touch($authenticatable);
        }

        Auth::setUser($authenticatable);
        $request->setUserResolver(fn () => $authenticatable);

        return $next($request);
    }

    private function resolveByToken($query, string $hashedToken)
    {
        // Only hashed tokens are stored; a token kept in plain text (an old
        // sign-in) would work for anyone who could read the database
        return $query->where('api_token', $hashedToken)->first();
    }
}
