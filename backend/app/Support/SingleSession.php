<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * One sign-in per account (customers, vendors, admins).
 *
 * A session counts as active when its token was used within the last
 * ACTIVE_MINUTES, the same window as the site's auto sign-out. Signing in from
 * another browser while one is active needs the customer's go-ahead; the
 * replaced browser is then told why it was signed out.
 */
final class SingleSession
{
    public const ACTIVE_MINUTES = 15;

    /** Don't write "last seen" more than once a minute per account */
    private const TOUCH_EVERY_SECONDS = 60;

    public const ACTIVE_MESSAGE = 'This account is already logged in on another device or browser.';
    public const REPLACED_MESSAGE = 'Your account was logged in on another device or browser.';

    public static function deviceFrom(?string $device): ?string
    {
        $device = trim((string) $device);

        return $device === '' ? null : substr($device, 0, 64);
    }

    /** Signed in and recently used somewhere other than this browser */
    public static function isActiveElsewhere(User $user, ?string $device): bool
    {
        if (!$user->api_token || !$user->last_seen_at) {
            return false;
        }
        if ($user->last_seen_at->lt(now()->subMinutes(self::ACTIVE_MINUTES))) {
            return false;
        }

        $device = self::deviceFrom($device);

        return !$device || $device !== $user->session_device;
    }

    /** Starts the account's only session; a different one still in place is marked as replaced */
    public static function start(User $user, string $tokenHash, ?string $device): void
    {
        $previous = $user->api_token;

        $user->forceFill([
            'api_token' => $tokenHash,
            'session_device' => self::deviceFrom($device),
            'last_seen_at' => now(),
            'replaced_token_hash' => $previous && $previous !== $tokenHash ? $previous : $user->replaced_token_hash,
        ])->save();
    }

    public static function end(User $user): void
    {
        $user->forceFill(['api_token' => null, 'last_seen_at' => null, 'session_device' => null])->save();
    }

    /** Keeps "last seen" fresh while the account is being used */
    public static function touch(User $user): void
    {
        if ($user->last_seen_at && $user->last_seen_at->gt(now()->subSeconds(self::TOUCH_EVERY_SECONDS))) {
            return;
        }

        try {
            User::query()->whereKey($user->id)->update(['last_seen_at' => now()]);
            $user->last_seen_at = now();
        } catch (\Throwable $e) {
            // Bookkeeping must never fail the request it rides on
            Log::warning('SingleSession touch failed: ' . $e->getMessage());
        }
    }

    /** Whether this (no longer valid) token belonged to a session another sign-in replaced */
    public static function replacedBy(string $tokenHash): bool
    {
        try {
            return User::query()->where('replaced_token_hash', $tokenHash)->exists();
        } catch (\Throwable $e) {
            Log::warning('SingleSession replaced check failed: ' . $e->getMessage());
            return false;
        }
    }
}
