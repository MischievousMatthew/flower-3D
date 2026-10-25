<?php

namespace App\Support;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Schema;

/**
 * Platform switches admins control in Security → Platform settings.
 * Stored in site_settings under "platform.*" keys; anything missing falls
 * back to the default below, so the app works before they're ever saved.
 */
final class PlatformSettings
{
    public const DEFAULTS = [
        'registrations_open' => true,        // customers can sign up
        'vendor_applications_open' => true,  // stores can apply
        'checkout_paused' => false,          // stop all new orders
        'checkout_pause_message' => 'Ordering is paused for a moment. Please try again later.',
        // Scheduled maintenance: checkout pauses by itself from start to end (ISO times; end optional)
        'checkout_pause_starts_at' => null,
        'checkout_pause_ends_at' => null,
        'stale_pending_days' => 3,           // when a pending order counts as stuck
        'login_max_attempts' => 5,           // failed sign-ins before a temporary lock
        'login_lockout_minutes' => 15,
        'login_show_attempts' => false,   // tell people how many tries are left before the lock
        'login_ip_per_minute' => 20,      // sign-in tries a minute from one internet connection
    ];

    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $stored = [];
        try {
            if (Schema::hasTable('site_settings')) {
                $stored = SiteSetting::query()->where('key', 'like', 'platform.%')->pluck('value', 'key')
                    ->mapWithKeys(fn ($v, $k) => [substr($k, 9) => $v])->all();
            }
        } catch (\Throwable) {
            // fall back to the defaults
        }

        return self::$cache = array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    /**
     * Whether checkout is paused right now: the switch is on, or a scheduled
     * pause has started and not ended. Checked on each request, so nothing
     * needs to run in the background at the start or end time.
     */
    public static function checkoutPausedNow(): bool
    {
        $all = self::all();
        if ($all['checkout_paused']) {
            return true;
        }
        [$start, $end] = self::pauseWindow();

        return $start !== null && now()->gte($start) && ($end === null || now()->lt($end));
    }

    /** @return array{0: ?\Carbon\CarbonImmutable, 1: ?\Carbon\CarbonImmutable} the scheduled pause, if any */
    public static function pauseWindow(): array
    {
        $all = self::all();
        $parse = function ($value) {
            try {
                return $value ? \Carbon\CarbonImmutable::parse($value) : null;
            } catch (\Throwable) {
                return null;
            }
        };

        return [$parse($all['checkout_pause_starts_at']), $parse($all['checkout_pause_ends_at'])];
    }

    /** What customers and stores may know: whether sign-ups and checkout are open, and any planned pause */
    public static function publicStatus(): array
    {
        $all = self::all();
        [$start, $end] = self::pauseWindow();
        $upcoming = $start && ($end === null || now()->lt($end));

        return [
            'registrations_open' => (bool) $all['registrations_open'],
            'vendor_applications_open' => (bool) $all['vendor_applications_open'],
            'checkout_paused' => self::checkoutPausedNow(),
            'checkout_pause_message' => (string) $all['checkout_pause_message'],
            'checkout_pause_starts_at' => $upcoming ? $start->toIso8601String() : null,
            'checkout_pause_ends_at' => $upcoming && $end ? $end->toIso8601String() : null,
        ];
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? self::DEFAULTS[$key] ?? null;
    }

    public static function save(array $values): array
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            SiteSetting::updateOrCreate(['key' => "platform.{$key}"], ['value' => $value]);
        }
        self::$cache = null;

        return self::all();
    }
}
