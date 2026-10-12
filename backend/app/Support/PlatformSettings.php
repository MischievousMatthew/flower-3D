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
        'stale_pending_days' => 3,           // when a pending order counts as stuck
        'login_max_attempts' => 5,           // failed sign-ins before a temporary lock
        'login_lockout_minutes' => 15,
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
