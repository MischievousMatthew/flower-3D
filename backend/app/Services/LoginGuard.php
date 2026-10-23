<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Support\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records failed sign-ins and temporarily locks an account after too many
 * wrong passwords in a row (Security → Platform settings sets how many and
 * for how long). A successful sign-in or a password reset starts the count again.
 *
 * Only wrong passwords count toward the lock: a name that matches no account
 * has nothing to protect, and counting it too made one typo count twice when
 * the login form tries the staff and the customer sign-in in turn.
 */
class LoginGuard
{
    public static function identifier(?string $typed): string
    {
        return Str::limit(mb_strtolower(trim((string) $typed)), 185, '');
    }

    /** Minutes left on a lock, or 0 when sign-in is allowed */
    public function lockedFor(string $identifier): int
    {
        $seconds = $this->lockedSeconds($identifier);

        return $seconds ? max(1, (int) ceil($seconds / 60)) : 0;
    }

    /** Seconds left on a lock, or 0 when sign-in is allowed */
    public function lockedSeconds(string $identifier): int
    {
        [$failures, $max, $window] = $this->recentFailures($identifier);
        if ($failures->count() < $max) {
            return 0;
        }
        // Locked until the oldest of the last $max failures ages out
        $unlockAt = CarbonImmutable::parse($failures->last())->addMinutes($window);

        return max(1, (int) ceil(now()->diffInSeconds($unlockAt, false)));
    }

    /** Wrong passwords still allowed before the account is locked */
    public function attemptsLeft(string $identifier): int
    {
        [$failures, $max] = $this->recentFailures($identifier);

        return max(0, $max - $failures->count());
    }

    public function recordFailure(Request $request, string $identifier, string $reason, ?int $userId = null, string $actorType = 'user'): void
    {
        try {
            LoginAttempt::create([
                'identifier' => $identifier,
                'user_id' => $userId,
                'actor_type' => $actorType,
                'reason' => $reason,
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed sign-in could not be recorded: ' . $e->getMessage());
        }
    }

    /** Starts the count again, e.g. after the password was reset */
    public function clear(array $identifiers): void
    {
        $identifiers = array_values(array_filter(array_map(fn ($i) => self::identifier($i), $identifiers)));
        if (! $identifiers) {
            return;
        }
        try {
            LoginAttempt::query()->whereIn('identifier', $identifiers)->where('reason', 'wrong_password')->delete();
        } catch (\Throwable $e) {
            Log::warning('Failed sign-ins could not be cleared: ' . $e->getMessage());
        }
    }

    public static function lockedMessage(int $minutes): string
    {
        return "Too many wrong passwords. For your safety, sign-in is paused for {$minutes} " . ($minutes === 1 ? 'minute' : 'minutes') . '.';
    }

    /** @return array{0: Collection, 1: int, 2: int} the latest wrong passwords (newest first), the limit and the window in minutes */
    private function recentFailures(string $identifier): array
    {
        $max = 5;
        $window = 15;
        if ($identifier === '') {
            return [collect(), $max, $window];
        }

        try {
            $max = max(1, (int) PlatformSettings::get('login_max_attempts'));
            $window = max(1, (int) PlatformSettings::get('login_lockout_minutes'));
            $since = CarbonImmutable::now()->subMinutes($window);

            $lastSuccess = DB::table('login_audit_logs')
                ->where(fn ($q) => $q->whereRaw('lower(username) = ?', [$identifier])->orWhereRaw('lower(email) = ?', [$identifier]))
                ->max('logged_in_at');
            if ($lastSuccess && CarbonImmutable::parse($lastSuccess)->gt($since)) {
                $since = CarbonImmutable::parse($lastSuccess);
            }

            $failures = LoginAttempt::query()->where('identifier', $identifier)
                ->where('reason', 'wrong_password')->where('created_at', '>', $since)
                ->orderByDesc('created_at')->limit($max)->pluck('created_at');

            return [$failures, $max, $window];
        } catch (\Throwable $e) {
            Log::warning('LoginGuard check failed: ' . $e->getMessage());

            return [collect(), $max, $window]; // never block sign-in because the check itself broke
        }
    }
}
