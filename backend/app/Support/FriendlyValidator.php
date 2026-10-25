<?php

namespace App\Support;

use Illuminate\Support\Str;
use Illuminate\Validation\Validator;

/**
 * Error messages name fields the way people say them. Without this, a shop
 * owner reads "The selected employee id is invalid." or "The is active field
 * must be true or false." Fields listed in lang/en/validation.php
 * ('attributes') keep that wording; the rest drop code-style parts such as a
 * trailing "id" or a leading "is".
 */
class FriendlyValidator extends Validator
{
    public function getDisplayableAttribute($attribute)
    {
        $shown = parent::getDisplayableAttribute($attribute);

        // Only reword the automatic "employee_id" → "employee id" form, never a chosen name
        $plain = str_replace('_', ' ', Str::snake(Str::afterLast($attribute, '.')));
        if ($shown !== $plain) {
            return $shown;
        }

        return self::humanize($plain);
    }

    public static function humanize(string $words): string
    {
        $words = preg_replace('/\s+ids$/', 's', $words);
        $words = preg_replace('/\s+(id|uuid|key|token)$/', '', $words);
        $words = preg_replace('/^(is|has)\s+/', '', $words);

        return trim($words) === '' ? 'this field' : trim($words);
    }
}
