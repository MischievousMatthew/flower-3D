<?php

namespace App\Http\Middleware;

use App\Models\Employee;
use App\Models\User;
use App\Services\AccountSuspensionService;
use App\Support\EndedSessions;
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
            // A working sign-in of the other kind (staff token on a customer route,
            // or the reverse): a wrong-route call, not a sign-out
            $otherKind = match ($type) {
                'employee' => $this->resolveByToken(User::query(), $hashedToken),
                'user' => $this->resolveByToken(Employee::query(), $hashedToken),
                default => null,
            };
            if ($otherKind) {
                return response()->json([
                    'success' => false,
                    'code' => 'wrong_account_type',
                    'message' => 'This page is for a different kind of account.',
                ], 401);
            }

            // This sign-in has ended: say why, so the browser can tell the person
            $ended = EndedSessions::lookup($hashedToken)
                ?? (SingleSession::replacedBy($hashedToken)
                    ? ['code' => EndedSessions::REPLACED, 'message' => SingleSession::REPLACED_MESSAGE]
                    : ['code' => EndedSessions::ENDED, 'message' => EndedSessions::MSG_GENERIC]);

            return response()->json(['success' => false] + $ended, 401);
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
                SingleSession::end($authenticatable, EndedSessions::EXPIRED, SingleSession::EXPIRED_MESSAGE);

                return response()->json([
                    'success' => false,
                    'code' => 'session_expired',
                    'message' => SingleSession::EXPIRED_MESSAGE,
                ], 401);
            }
            SingleSession::touch($authenticatable);
        } elseif ($authenticatable instanceof Employee) {
            // So a sign-in on another browser can tell this one is in use
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
