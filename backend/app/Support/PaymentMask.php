<?php

namespace App\Support;

/**
 * Admin screens show gateway references only in masked form, enough to
 * match a payment with PayMongo support, never the full id.
 */
final class PaymentMask
{
    /** "cs_594d6ada54dccdde384ae9eb" → "cs_••••9eb" */
    public static function reference(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $prefix = preg_match('/^[a-z]+_/i', $value, $m) ? $m[0] : '';
        $tail = substr($value, -4);

        return $prefix . '••••' . $tail;
    }
}
