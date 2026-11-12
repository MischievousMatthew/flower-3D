<?php

namespace App\Support;

use App\Models\User;
use App\Models\VendorApplication;
use Illuminate\Support\Str;

/**
 * Whether a delivery address is inside a shop's delivery areas
 * (Shop settings → Areas you deliver to).
 *
 * Addresses are free text, so the places on the list (VendorApplication::
 * SERVICE_AREAS) are looked for in it; when it names none, the customer's
 * profile city and region are used instead. A shop that hasn't picked any
 * areas delivers anywhere. An address outside the areas, or one we can't
 * place, needs the shop's approval before the order goes out.
 */
class DeliveryArea
{
    /** Region names the old registration form saved, read as the provinces we list */
    private const LEGACY = [
        'Region IV-A (CALABARZON)' => 'Cavite',
        'NCR (National Capital Region)' => 'Metro Manila',
    ];

    /** The shop's areas, as names on the list */
    public static function areasOf(?VendorApplication $shop): array
    {
        $raw = $shop?->service_areas;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : explode(',', $raw);
        }
        $known = self::known();

        return array_values(array_unique(array_filter(
            array_map(fn ($a) => self::LEGACY[trim((string) $a)] ?? trim((string) $a), (array) $raw),
            fn ($a) => isset($known[$a]),
        )));
    }

    /**
     * @return array{covered: bool, restricted: bool, areas: string[], found: string[]}
     *   restricted: the shop picked areas at all; found: the places read from the address
     */
    public static function check(?VendorApplication $shop, ?string $address, ?User $customer = null): array
    {
        $areas = self::areasOf($shop);
        $found = self::placesIn((string) $address);
        if (! $found && $customer) {
            $found = self::placesIn(trim(($customer->city ?? '') . ', ' . ($customer->region ?? '')));
        }

        if (! $areas) {
            return ['covered' => true, 'restricted' => false, 'areas' => [], 'found' => $found];
        }

        $known = self::known();
        $covered = false;
        foreach ($found as $place) {
            // The place itself, or its whole province
            if (in_array($place, $areas, true) || in_array($known[$place], $areas, true)) {
                $covered = true;
                break;
            }
        }

        return ['covered' => $covered, 'restricted' => true, 'areas' => $areas, 'found' => $found];
    }

    /** Places and provinces on the list that the text names, longest names first */
    public static function placesIn(string $text): array
    {
        $haystack = ' ' . self::plain($text) . ' ';
        if (trim($haystack) === '') {
            return [];
        }

        $names = array_keys(self::known());
        // "Cavite City" before "Cavite", "Metro Manila" before "Manila"
        usort($names, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        $found = [];
        foreach ($names as $name) {
            $needle = ' ' . self::plain($name) . ' ';
            if (str_contains($haystack, $needle)) {
                $found[] = $name;
                // So a shorter name inside it isn't read as a second place
                $haystack = str_replace($needle, '  ', $haystack);
            }
        }

        return $found;
    }

    /** name → its province (a province maps to itself) */
    private static function known(): array
    {
        static $map = null;
        if ($map === null) {
            $map = [];
            foreach (VendorApplication::SERVICE_AREAS as $province => $places) {
                $map[$province] = $province;
                foreach ($places as $place) {
                    $map[$place] = $province;
                }
            }
        }

        return $map;
    }

    /** Lowercase, no accents (Dasmariñas = Dasmarinas), words split by single spaces */
    private static function plain(string $text): string
    {
        $text = mb_strtolower(Str::ascii($text));

        return trim(preg_replace('/[^a-z0-9]+/', ' ', $text));
    }
}
