<?php

namespace App\Services;

use App\Models\LoginAttempt;
use App\Support\PlatformSettings;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Records failed sign-ins and temporarily locks an account after too many
 * in a row (Security → Platform settings sets how many and for how long).
 * A successful sign-in starts the count again.
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
        if ($identifier === '') {
            return 0;
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
                ->where('reason', '!=', 'locked')->where('created_at', '>', $since)
                ->orderByDesc('created_at')->limit($max)->pluck('created_at');

            if ($failures->count() < $max) {
                return 0;
            }

            // Locked until the oldest of the last $max failures ages out
            $unlockAt = CarbonImmutable::parse($failures->last())->addMinutes($window);
            return max(1, (int) ceil(now()->diffInSeconds($unlockAt, false) / 60));
        } catch (\Throwable $e) {
            Log::warning('LoginGuard check failed: ' . $e->getMessage());
            return 0; // never block sign-in because the check itself broke
        }
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

    public static function lockedMessage(int $minutes): string
    {
        return "Too many failed sign-ins. Try again in {$minutes} " . ($minutes === 1 ? 'minute' : 'minutes') . '.';
    }
}
