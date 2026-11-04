<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

/**
 * Occasion presets for the customer's 3D bouquet customizer.
 *
 * BloomCraft provides the built-in occasions (KEYS) with a 3D design each. A
 * vendor can turn any occasion on or off, set its sizes (number of flower
 * stems) and base price, change its design, and choose whether customers pick
 * a wrapper colour and a ribbon. Vendors can also add their own occasions
 * (keys "custom_…") built on one of the designs. All of it is stored in
 * vendor_applications.bouquet_presets as { key: {...} }; anything missing
 * falls back to DEFAULTS.
 */
class BouquetPresets
{
    // Birthday is left out until the shop has a model for it
    public const KEYS = ['wedding', 'romance', 'graduation', 'sympathy', 'just_because'];

    public const MAX_SIZE = 36;
    public const MAX_SIZES_PER_OCCASION = 4;
    public const MIN_FILLERS = 5;
    public const MAX_CUSTOM = 10;
    public const MAX_BASE_PRICE = 100000;

    /** Bouquets saved before presets existed allowed this many flowers. */
    public const LEGACY_MAX_FLOWERS = 3;

    /**
     * The 3D designs and what each model has: a wrapper customers can recolour
     * (the basket's wicker counts) and a ribbon.
     */
    public const DESIGNS = [
        'classic' => ['label' => 'Classic cone', 'wrapper' => true, 'ribbon' => true],
        'korean' => ['label' => 'Korean layered wrap', 'wrapper' => true, 'ribbon' => true],
        'hand_tied' => ['label' => 'Round hand-tied', 'wrapper' => false, 'ribbon' => true],
        'basket' => ['label' => 'Basket', 'wrapper' => true, 'ribbon' => false],
    ];

    /** A shop's own 3D wrapper as a style: "model_<option id>" */
    public const MODEL_DESIGN = '/^model_(\d{1,10})$/';

    /** Designs whose outer paper can carry a print (newspaper, words) */
    public const PATTERN_DESIGNS = ['classic', 'korean'];
    public const MAX_PATTERN_WORD = 16;
    // The printed word's size, as a percent of the normal size
    public const MIN_PATTERN_SIZE = 50;
    public const MAX_PATTERN_SIZE = 200;

    /** Icons a vendor can give their own occasions (frontend constants/occasionIcons.js) */
    public const ICONS = ['rings', 'heart', 'gift', 'cap', 'dove', 'flower'];

    public const DEFAULTS = [
        'wedding' => ['label' => 'Wedding', 'design' => 'hand_tied', 'wrapper' => false, 'ribbon' => true, 'sizes' => [6, 12, 24], 'icon' => 'rings', 'description' => 'A hand-tied bridal bouquet finished with a satin ribbon.'],
        'romance' => ['label' => "Anniversary / Valentine's", 'design' => 'korean', 'wrapper' => true, 'ribbon' => true, 'sizes' => [6, 12, 24], 'icon' => 'heart', 'description' => "A Korean-style layered wrap, open and airy, for anniversaries and Valentine's Day."],
        'graduation' => ['label' => 'Graduation', 'design' => 'classic', 'wrapper' => true, 'ribbon' => true, 'sizes' => [3, 6, 9], 'icon' => 'cap', 'description' => 'The classic paper cone, for the big day.'],
        'sympathy' => ['label' => 'Sympathy', 'design' => 'basket', 'wrapper' => true, 'ribbon' => false, 'sizes' => [6, 12], 'icon' => 'dove', 'description' => 'A woven basket of flowers to share your condolences.'],
        'just_because' => ['label' => 'Just because', 'design' => 'classic', 'wrapper' => true, 'ribbon' => true, 'sizes' => [3, 6, 12], 'icon' => 'flower', 'description' => 'The classic paper cone. No reason needed.'],
    ];

    public static function isCustomKey(string $key): bool
    {
        return (bool) preg_match('/^custom_[a-z0-9]{6,24}$/', $key);
    }

    /**
     * Every occasion, in display order (built-in first, then the shop's own),
     * with the vendor's saved choices applied.
     *
     * @return array<int, array{key: string, label: string, design: string, wrapper: bool, ribbon: bool, enabled: bool, sizes: int[], base_price: float, custom: bool, icon: string, description: string}>
     */
    public static function merge(?array $saved): array
    {
        $saved = $saved ?? [];
        $list = [];

        foreach (self::KEYS as $key) {
            $own = is_array($saved[$key] ?? null) ? $saved[$key] : [];
            $list[] = self::build($key, self::DEFAULTS[$key], $own, false);
        }
        $custom = 0;
        foreach ($saved as $key => $own) {
            if (! is_string($key) || ! self::isCustomKey($key) || ! is_array($own) || ++$custom > self::MAX_CUSTOM) {
                continue;
            }
            $label = trim((string) ($own['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $design = self::validDesign($own['design'] ?? null) ?? 'classic';
            $list[] = self::build($key, [
                'label' => $label,
                'design' => $design,
                'wrapper' => self::parts($design)['wrapper'],
                'ribbon' => self::parts($design)['ribbon'],
                'sizes' => [6, 12],
                'icon' => in_array($own['icon'] ?? null, self::ICONS, true) ? $own['icon'] : 'flower',
                'description' => (string) ($own['description'] ?? ''),
            ], $own, true);
        }

        return $list;
    }

    private static function build(string $key, array $default, array $own, bool $custom): array
    {
        $design = self::validDesign($own['design'] ?? null) ?? $default['design'];
        $can = self::parts($design);
        $sizes = self::cleanSizes($own['sizes'] ?? null);

        return [
            'key' => $key,
            'label' => $default['label'],
            'design' => $design,
            // Only what the design's model has can be switched on
            'wrapper' => $can['wrapper'] && (array_key_exists('wrapper', $own) ? (bool) $own['wrapper'] : $default['wrapper']),
            'ribbon' => $can['ribbon'] && (array_key_exists('ribbon', $own) ? (bool) $own['ribbon'] : $default['ribbon']),
            'enabled' => array_key_exists('enabled', $own) ? (bool) $own['enabled'] : true,
            'sizes' => $sizes ?: $default['sizes'],
            'base_price' => is_numeric($own['base_price'] ?? null) ? round(max(0, min(self::MAX_BASE_PRICE, (float) $own['base_price'])), 2) : 0.0,
            'custom' => $custom,
            'icon' => $default['icon'],
            'description' => $default['description'],
        ];
    }

    private static function validDesign($design): ?string
    {
        return is_string($design) && (isset(self::DESIGNS[$design]) || self::modelId($design)) ? $design : null;
    }

    /** The 3D wrapper option id of a "model_<id>" style, or null */
    public static function modelId($design): ?int
    {
        return is_string($design) && preg_match(self::MODEL_DESIGN, $design, $m) ? (int) $m[1] : null;
    }

    /** What a style's model has. A shop's own 3D wrapper can be recoloured and gets a ribbon. */
    public static function parts(string $design): array
    {
        return self::DESIGNS[$design] ?? ['label' => '3D wrapper', 'wrapper' => true, 'ribbon' => true];
    }

    /**
     * Fills in each occasion that uses one of the shop's 3D wrappers with the
     * model's link and name. If that wrapper was deleted (or never was the
     * shop's), the occasion goes back to its usual style.
     */
    public static function withModels(array $presets, int $ownerId): array
    {
        $ids = array_values(array_filter(array_map(fn ($p) => self::modelId($p['design']), $presets)));
        $models = $ids
            ? \App\Models\BouquetCustomizationOption::forOwner($ownerId)
                ->where('type', \App\Models\BouquetCustomizationOption::TYPE_WRAP_MODEL)
                ->whereNotNull('model_url')
                ->whereIn('id', $ids)
                ->get(['id', 'name', 'model_url'])
                ->keyBy('id')
            : collect();

        return array_map(function ($p) use ($models) {
            $id = self::modelId($p['design']);
            if (! $id) {
                return $p;
            }
            $model = $models->get($id);
            if ($model) {
                return $p + ['model_url' => $model->model_url, 'model_name' => $model->name];
            }
            $design = self::DEFAULTS[$p['key']]['design'] ?? 'classic';
            $can = self::DESIGNS[$design];

            return array_merge($p, ['design' => $design, 'wrapper' => $p['wrapper'] && $can['wrapper'], 'ribbon' => $p['ribbon'] && $can['ribbon']]);
        }, $presets);
    }

    /** The vendor's presets (defaults if they never saved any). */
    public static function forOwner(int $ownerId): array
    {
        // Vendor applications are linked to their user by email (User::vendor())
        $email = \App\Models\User::whereKey($ownerId)->value('email');
        $application = $email
            ? \App\Models\VendorApplication::query()->where('email', $email)->latest('id')->first(['id', 'bouquet_presets'])
            : null;

        return self::withModels(self::merge($application?->bouquet_presets), $ownerId);
    }

    /**
     * Validates a vendor's settings form and returns what to store. Custom
     * occasions left out of the form are removed.
     *
     * @throws ValidationException
     */
    public static function normalize(array $input): array
    {
        $errors = [];
        $out = [];
        $custom = 0;

        foreach ($input as $key => $row) {
            $key = (string) $key;
            $isCustom = self::isCustomKey($key);
            if (! $isCustom && ! in_array($key, self::KEYS, true)) {
                continue;
            }
            $row = is_array($row) ? $row : [];
            $stored = [];

            if ($isCustom) {
                if (++$custom > self::MAX_CUSTOM) {
                    $errors['presets'][] = 'You can add up to ' . self::MAX_CUSTOM . ' occasions of your own.';
                    break;
                }
                $label = trim((string) ($row['label'] ?? ''));
                if ($label === '' || mb_strlen($label) > 40) {
                    $errors["$key.label"][] = 'Give the occasion a name of up to 40 characters.';
                    continue;
                }
                $description = trim((string) ($row['description'] ?? ''));
                if (mb_strlen($description) > 160) {
                    $errors["$key.description"][] = "$label: keep the description under 160 characters.";
                    continue;
                }
                $stored += [
                    'label' => $label,
                    'icon' => in_array($row['icon'] ?? null, self::ICONS, true) ? $row['icon'] : 'flower',
                    'description' => $description,
                ];
            } else {
                $label = self::DEFAULTS[$key]['label'];
            }

            $design = $row['design'] ?? ($isCustom ? 'classic' : self::DEFAULTS[$key]['design']);
            if (! self::validDesign($design)) {
                $errors["$key.design"][] = "$label: choose one of the bouquet styles.";
                continue;
            }

            $price = $row['base_price'] ?? 0;
            if ($price === '' || $price === null) {
                $price = 0;
            }
            if (! is_numeric($price) || $price < 0 || $price > self::MAX_BASE_PRICE) {
                $errors["$key.base_price"][] = "$label: the base price must be from 0 to " . number_format(self::MAX_BASE_PRICE) . '.';
                continue;
            }

            $raw = is_array($row['sizes'] ?? null) ? array_values($row['sizes']) : [];
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

            $can = self::parts($design);
            $out[$key] = $stored + [
                'enabled' => (bool) ($row['enabled'] ?? true),
                'sizes' => $sizes,
                'base_price' => round((float) $price, 2),
                'design' => $design,
                'wrapper' => $can['wrapper'] && (bool) ($row['wrapper'] ?? ($isCustom ? true : self::DEFAULTS[$key]['wrapper'])),
                'ribbon' => $can['ribbon'] && (bool) ($row['ribbon'] ?? ($isCustom ? true : self::DEFAULTS[$key]['ribbon'])),
            ];
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
     * @return array{ok: bool, message?: string, customizations: array, filler_limit: int, base_price: float}
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

            return ['ok' => true, 'customizations' => $customizations, 'filler_limit' => self::MIN_FILLERS, 'base_price' => 0.0];
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

        $base = (float) ($occasion['base_price'] ?? 0);
        $customizations['preset'] = ['key' => $occasion['key'], 'size' => $size, 'label' => $occasion['label'], 'base_price' => $base];
        // The bouquet's design follows its occasion (the viewer rebuilds it from this)
        $customizations['design'] = $occasion['design'];
        // A shop's own 3D wrapper: the viewer loads it from here
        if (! empty($occasion['model_url'])) {
            $customizations['design_model_url'] = $occasion['model_url'];
        } else {
            unset($customizations['design_model_url']);
        }
        // Parts the customer can't choose keep the design's own colours (e.g. a natural basket)
        if (! $occasion['wrapper']) {
            unset($customizations['wrapper'], $customizations['inner_wrapper'], $customizations['paper_color'], $customizations['inner_paper_color']);
        }
        if (! $occasion['ribbon']) {
            unset($customizations['ribbon'], $customizations['ribbon_color']);
        }
        // The print comes from the shop's chosen wrapper (resolveSelections), never the browser
        unset($customizations['wrapper_pattern'], $customizations['inner_wrapper_pattern']);

        return ['ok' => true, 'customizations' => $customizations, 'filler_limit' => $fillerLimit, 'base_price' => $base];
    }

    /** A print on the paper: newspaper, or a short word written over and over (size in %); null for plain */
    public static function cleanPattern($pattern): ?array
    {
        if (! is_array($pattern)) {
            return null;
        }
        if (($pattern['type'] ?? null) === 'newspaper') {
            return ['type' => 'newspaper'];
        }
        if (($pattern['type'] ?? null) === 'words') {
            $text = preg_replace('/[^\p{L}\p{N} \'&!♥.-]/u', '', (string) ($pattern['text'] ?? ''));
            $text = mb_substr(trim(preg_replace('/\s+/u', ' ', (string) $text)), 0, self::MAX_PATTERN_WORD);

            if ($text === '') {
                return null;
            }
            $size = self::cleanPatternSize($pattern['size'] ?? null);

            return $size === 100 ? ['type' => 'words', 'text' => $text] : ['type' => 'words', 'text' => $text, 'size' => $size];
        }

        return null;
    }

    /** A word size between 50% and 200%, in steps of 5; 100 when missing */
    public static function cleanPatternSize($size): int
    {
        if (! is_numeric($size)) {
            return 100;
        }

        return (int) (round(max(self::MIN_PATTERN_SIZE, min(self::MAX_PATTERN_SIZE, (float) $size)) / 5) * 5);
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
        return ['ok' => false, 'message' => $message, 'customizations' => $customizations, 'filler_limit' => $fillerLimit, 'base_price' => 0.0];
    }
}
