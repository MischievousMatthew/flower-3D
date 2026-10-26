<?php

namespace App\Http\Middleware;

use App\Support\Phone;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Saves every contact number the same way ("0917 123 4567") for customers,
 * vendors, the ERP and admin, before any controller validates it. A number
 * that isn't a mobile number gets a plain message next to its field.
 */
class FormatPhoneNumbers
{
    /** Request fields that hold a contact number ("*" = each item of a list) */
    private const FIELDS = [
        'phone', 'contact_number', 'contactNumber', 'mobile_number', 'emergency_contact_number', 'contact_phone',
        'contacts.*.phone',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            return $next($request);
        }

        $input = $request->input();
        $errors = [];
        foreach (self::FIELDS as $field) {
            foreach ($this->paths($input, $field) as $path) {
                $value = Arr::get($input, $path);
                if (! is_string($value) || trim($value) === '') {
                    continue;
                }
                $formatted = Phone::format($value);
                if ($formatted === null) {
                    $errors[$path] = Phone::MESSAGE;
                } else {
                    Arr::set($input, $path, $formatted);
                }
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
        $request->merge($input);

        return $next($request);
    }

    /** "contacts.*.phone" → ["contacts.0.phone", "contacts.1.phone"] for what was sent */
    private function paths(array $input, string $field): array
    {
        if (! str_contains($field, '*')) {
            return Arr::has($input, $field) ? [$field] : [];
        }
        [$list, $key] = explode('.*.', $field, 2);
        $items = Arr::get($input, $list);

        return is_array($items)
            ? array_values(array_filter(array_map(fn ($i) => "{$list}.{$i}.{$key}", array_keys($items)), fn ($p) => Arr::has($input, $p)))
            : [];
    }
}
