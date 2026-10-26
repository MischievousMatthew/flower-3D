<?php

namespace App\Support;

/**
 * Every contact number is a Philippine mobile number, kept as "09XX XXX XXXX".
 * People may type 09171234567, +63 917 123 4567, 639171234567 or 9171234567.
 */
class Phone
{
    public const PATTERN = '/^09\d{2} \d{3} \d{4}$/';

    public const MESSAGE = 'Enter a mobile number like 0917 123 4567.';

    /** "09XX XXX XXXX", or null when it isn't a mobile number */
    public static function format(?string $value): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $value);
        if (str_starts_with($digits, '63') && strlen($digits) === 12) {
            $digits = '0' . substr($digits, 2);
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $digits = '0' . $digits;
        }

        if (! preg_match('/^09\d{9}$/', $digits)) {
            return null;
        }

        return substr($digits, 0, 4) . ' ' . substr($digits, 4, 3) . ' ' . substr($digits, 7);
    }
}
