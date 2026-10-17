<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Occasion presets for the customer's 3D bouquet customizer.
 *
 * The occasions and how each is built (wrapper, ribbon) are fixed here. Each
 * vendor can turn occasions on or off and choose the sizes (number of flower
 * stems); that's stored in vendor_applications.bouquet_presets as
 * { key: { enabled, sizes[] } }, and anything missing falls back to DEFAULTS.
 */
class BouquetPresets
{
    public const KEYS = ['wedding', 'romance', 'birthday', 'graduation', 'sympathy', 'just_because'];

    public const MAX_SIZE = 36;
    public const MAX_SIZES_PER_OCCASION = 4;
    public const MIN_FILLERS = 5;

    /** Bouquets saved before presets existed allowed this many flowers. */
    public const LEGACY_MAX_FLOWERS = 3;

    public const DEFAULTS = [
        'wedding' => ['label' => 'Wedding', 'wrapper' => false, 'ribbon' => true, 'sizes' => [6, 12, 24]],
        'romance' => ['label' => "Anniversary / Valentine's", 'wrapper' => true, 'ribbon' => true, 'sizes' => [6, 12, 24]],
        'birthday' => ['label' => 'Birthday', 'wrapper' => true, 'ribbon' => true, 'sizes' => [4, 8, 12]],
        'graduation' => ['label' => 'Graduation', 'wrapper' => true, 'ribbon' => true, 'sizes' => [3, 6, 9]],
        'sympathy' => ['label' => 'Sympathy', 'wrapper' => true, 'ribbon' => false, 'sizes' => [6, 12]],
        'just_because' => ['label' => 'Just because', 'wrapper' => true, 'ribbon' => true, 'sizes' => [3, 6, 12]],
    ];

    /**
     * Every occasion, in display order, with the vendor's saved choices applied.
     *
     * @return array<int, array{key: string, label: string, wrapper: bool, ribbon: bool, enabled: bool, sizes: int[]}>
     */
    public static function merge(?array $saved): array
    {
        $saved = $saved ?? [];

        return array_map(function (string $key) use ($saved) {
            $default = self::DEFAULTS[$key];
            $own = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            $sizes = self::cleanSizes($own['sizes'] ?? null);

            return [
                'key' => $key,
                'label' => $default['label'],
                'wrapper' => $default['wrapper'],
                'ribbon' => $default['ribbon'],
                'enabled' => array_key_exists('enabled', $own) ? (bool) $own['enabled'] : true,
                'sizes' => $sizes ?: $default['sizes'],
            ];
        }, self::KEYS);
    }

    /** The vendor's presets (defaults if they never saved any). */
    public static function forOwner(int $ownerId): array
    {
        // Vendor applications are linked to their user by email (User::vendor())
        $email = \App\Models\User::whereKey($ownerId)->value('email');
        $application = $email
            ? \App\Models\VendorApplication::query()->where('email', $email)->latest('id')->first(['id', 'bouquet_presets'])
            : null;

        return self::merge($application?->bouquet_presets);
    }

    /**
     * Validates a vendor's settings form and returns what to store.
     *
     * @throws ValidationException
     */
    public static function normalize(array $input): array
    {
        $errors = [];
        $out = [];

        foreach (self::KEYS as $key) {
            if (! array_key_exists($key, $input)) {
                continue;
            }
            $row = is_array($input[$key]) ? $input[$key] : [];
            $raw = is_array($row['sizes'] ?? null) ? array_values($row['sizes']) : [];
            $label = self::DEFAULTS[$key]['label'];

            $sizes = [];
            foreach ($raw as $size) {
                if (! is_numeric($size) || (int) $size != $size || $size < 1 || $size > self::MAX_SIZE) {
                    $errors["$key.sizes"][] = "$label: each size must be a whole number from 1 to " . self::MAX_SIZE . '.';
                    continue 2;
                }
                $sizes[] = (int) $size;
            }
            if (! $sizes) {
                $errors["$key.sizes"][] = "$label: add at least one size.";
                continue;
            }
            if (count($sizes) !== count(array_unique($sizes))) {
                $errors["$key.sizes"][] = "$label: each size can only be listed once.";
                continue;
            }
            if (count($sizes) > self::MAX_SIZES_PER_OCCASION) {
                $errors["$key.sizes"][] = "$label: up to " . self::MAX_SIZES_PER_OCCASION . ' sizes.';
                continue;
            }
            sort($sizes);
            $out[$key] = ['enabled' => (bool) ($row['enabled'] ?? true), 'sizes' => $sizes];
        }

        if (! $errors) {
            $enabled = array_filter(self::merge($out), fn ($p) => $p['enabled']);
            if (! $enabled) {
                $errors['presets'][] = 'Keep at least one occasion on, so customers can build a bouquet.';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $out;
    }

    /** Largest size any enabled occasion offers: the most flowers one bouquet can have. */
    public static function largestSize(array $presets): int
    {
        $sizes = [];
        foreach ($presets as $p) {
            if ($p['enabled']) {
                $sizes = array_merge($sizes, $p['sizes']);
            }
        }

        return $sizes ? max($sizes) : self::LEGACY_MAX_FLOWERS;
    }

    public static function fillerLimit(int $size): int
    {
        return max(self::MIN_FILLERS, (int) ceil($size / 2));
    }

    /**
     * Checks a custom bouquet against the vendor's presets and drops the parts
     * its occasion doesn't have (e.g. a wedding bouquet's wrapper).
     *
     * @return array{ok: bool, message?: string, customizations: array, filler_limit: int}
     */
    public static function check(array $presets, array $customizations): array
    {
        $flowers = is_array($customizations['flowers'] ?? null) ? count($customizations['flowers']) : 0;
        $preset = $customizations['preset'] ?? null;

        // Made before presets existed: the old fixed limit still applies
        if (! is_array($preset) || empty($preset['key'])) {
            if ($flowers > self::LEGACY_MAX_FLOWERS) {
                return self::fail('A custom bouquet can have up to ' . self::LEGACY_MAX_FLOWERS . ' flowers.', $customizations, self::MIN_FILLERS);
            }

            return ['ok' => true, 'customizations' => $customizations, 'filler_limit' => self::MIN_FILLERS];
        }

        $byKey = [];
        foreach ($presets as $p) {
            $byKey[$p['key']] = $p;
        }
        $occasion = $byKey[$preset['key']] ?? null;
        $size = (int) ($preset['size'] ?? 0);
        $fillerLimit = self::fillerLimit(max($size, 1));

        if (! $occasion) {
            return self::fail("This bouquet's occasion isn't available. Please rebuild it.", $customizations, $fillerLimit);
        }
        if (! $occasion['enabled']) {
            return self::fail("This shop no longer offers {$occasion['label']} bouquets. Please choose another occasion.", $customizations, $fillerLimit);
        }
        if (! in_array($size, $occasion['sizes'], true)) {
            return self::fail("This shop doesn't offer a {$size}-flower {$occasion['label']} bouquet. Please choose one of its sizes.", $customizations, $fillerLimit);
        }
        if ($flowers > $size) {
            return self::fail("This {$size}-flower bouquet has {$flowers} flowers. Remove some or choose a bigger size.", $customizations, $fillerLimit);
        }

        $customizations['preset'] = ['key' => $occasion['key'], 'size' => $size, 'label' => $occasion['label']];
        if (! $occasion['wrapper']) {
            unset($customizations['wrapper'], $customizations['inner_wrapper']);
        }
        if (! $occasion['ribbon']) {
            unset($customizations['ribbon']);
        }

        return ['ok' => true, 'customizations' => $customizations, 'filler_limit' => $fillerLimit];
    }

    private static function cleanSizes($sizes): array
    {
        if (! is_array($sizes)) {
            return [];
        }
        $clean = array_values(array_unique(array_filter(
            array_map('intval', $sizes),
            fn ($s) => $s >= 1 && $s <= self::MAX_SIZE
        )));
        sort($clean);

        return array_slice($clean, 0, self::MAX_SIZES_PER_OCCASION);
    }

    private static function fail(string $message, array $customizations, int $fillerLimit): array
    {
        return ['ok' => false, 'message' => $message, 'customizations' => $customizations, 'filler_limit' => $fillerLimit];
    }
}
