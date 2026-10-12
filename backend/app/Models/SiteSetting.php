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
}
