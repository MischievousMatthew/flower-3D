<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Website information: name, about text, contact details, social links */
class SiteSetting extends Model
{
    public const KEYS = [
        'site_name', 'tagline', 'about', 'contact_email', 'contact_phone',
        'address', 'business_hours', 'socials', 'footer_note',
    ];

    protected $fillable = ['key', 'value'];

    protected $casts = ['value' => 'json'];

    /** @return array<string, mixed> */
    public static function snapshot(): array
    {
        $stored = static::query()->whereIn('key', self::KEYS)->pluck('value', 'key')->all();

        return collect(self::KEYS)->mapWithKeys(fn ($key) => [$key => $stored[$key] ?? null])->all();
    }

    /** How the shop's banner slideshow plays. Kept out of KEYS so the website-info form never resets it. */
    public const SLIDESHOW_DEFAULTS = [
        'seconds' => 6,           // time each slide stays up
        'autoplay' => true,       // move on by itself
        'pause_on_hover' => true, // hold while the pointer is over it
        'show_arrows' => true,    // previous / next buttons
        'show_dots' => true,      // slide dots
    ];

    /** How the announcement bar at the top of the shop behaves. Also kept out of KEYS. */
    public const ANNOUNCEMENT_BAR_DEFAULTS = [
        'seconds' => 7,              // time each announcement stays up
        'autoplay' => true,          // move on by itself
        'pause_on_hover' => true,    // hold while the pointer is over it
        'show_arrows' => true,       // ‹ › buttons
        'dismissible' => true,       // customers may close announcements
        'dismiss_for' => 'forever',  // forever | 1 | 7 (days a closed announcement stays hidden)
    ];

    public const DISMISS_FOR = ['forever', '1', '7'];

    /** @return array<string, mixed> */
    public static function slideshow(): array
    {
        return static::group('slideshow', self::SLIDESHOW_DEFAULTS);
    }

    /** @return array<string, mixed> */
    public static function announcementBar(): array
    {
        return static::group('announcement_bar', self::ANNOUNCEMENT_BAR_DEFAULTS);
    }

    /** A stored settings row merged over its defaults; unknown keys are dropped. */
    private static function group(string $key, array $defaults): array
    {
        $stored = static::query()->where('key', $key)->value('value');
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }

        return array_merge($defaults, array_intersect_key(is_array($stored) ? $stored : [], $defaults));
    }
}
