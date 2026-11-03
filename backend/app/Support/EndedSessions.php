<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Remembers why a sign-in ended, for every kind of account (customers,
 * vendors, admins, ERP staff). When a browser still uses the old token,
 * TokenAuth answers with the reason and the site shows it in a modal.
 */
final class EndedSessions
{
    public const KEEP_DAYS = 7;

    public const REPLACED = 'session_replaced';
    public const EXPIRED = 'session_expired';
    public const ENDED = 'session_ended';

    public const MSG_GENERIC = 'You were signed out. Please sign in again.';
    public const MSG_PASSWORD = 'Your password was changed, so you were signed out. Sign in with the new password.';
    public const MSG_STAFF_PASSWORD = 'The shop owner changed your password, so you were signed out. Sign in with the new password.';
    public const MSG_STAFF_CLOSED = 'The shop owner closed your staff account, so you were signed out.';
    public const MSG_ACCESS_OFF = 'Your access was turned off, so you were signed out.';
    public const MSG_SUSPENDED_STORE = 'The shop was suspended by BloomCraft, so you were signed out. Contact the shop owner or support.';

    /** Record why this account's current token stopped working (call before clearing it) */
    public static function endFor(User|Employee|null $account, string $code, string $message): void
    {
        if ($account && $account->api_token) {
            self::record($account->api_token, $code, $message);
        }
    }

    public static function record(?string $tokenHash, string $code, string $message): void
    {
        if (! $tokenHash) {
            return;
        }
        try {
            DB::table('ended_sessions')->updateOrInsert(
                ['token_hash' => $tokenHash],
                ['code' => $code, 'message' => mb_substr($message, 0, 300), 'created_at' => now()],
            );
            // Old entries are no use to anyone; tidy up now and then
            if (random_int(1, 50) === 1) {
                DB::table('ended_sessions')->where('created_at', '<', now()->subDays(self::KEEP_DAYS))->delete();
            }
        } catch (\Throwable $e) {
            // Bookkeeping must never stop a sign-out
            Log::warning('EndedSessions record failed: ' . $e->getMessage());
        }
    }

    /** ['code' => …, 'message' => …] for a token that has ended, or null if none was recorded */
    public static function lookup(string $tokenHash): ?array
    {
        try {
            $row = DB::table('ended_sessions')->where('token_hash', $tokenHash)->first();
        } catch (\Throwable $e) {
            Log::warning('EndedSessions lookup failed: ' . $e->getMessage());
            return null;
        }

        return $row ? ['code' => $row->code, 'message' => $row->message] : null;
    }
}
