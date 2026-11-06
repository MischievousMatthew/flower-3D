<?php

namespace App\Support;

/**
 * Admins sign in on a page nothing links to: /console/<key> on the website,
 * where <key> is ADMIN_LOGIN_KEY, known only to the server. Once the key is
 * set, admin accounts can sign in there and nowhere else.
 *
 * Until it is set (or if it is too short to be safe), the hidden page stays
 * shut and admins keep using the normal sign-in page, so a deploy without
 * the key never locks every admin out.
 */
class AdminLoginGate
{
    public const MIN_LENGTH = 24;

    public static function key(): ?string
    {
        $key = trim((string) config('app.admin_login_key'));

        return strlen($key) >= self::MIN_LENGTH ? $key : null;
    }

    public static function enabled(): bool
    {
        return self::key() !== null;
    }

    /** The key in the URL is the right one */
    public static function opens(mixed $given): bool
    {
        $key = self::key();

        return $key !== null && is_string($given) && hash_equals($key, $given);
    }
}
