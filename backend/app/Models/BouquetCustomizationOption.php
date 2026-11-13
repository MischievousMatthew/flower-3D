<?php

namespace App\Models;

use App\Support\BouquetPresets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A vendor's wrapper, filler, ribbon, message card or own 3D wrapper model
 * (a bouquet style for occasions) for the 3D flower customizer. Flowers stay regular products (selling_type =
 * per_piece_customizable).
 */
class BouquetCustomizationOption extends Model
{
    public const TYPE_WRAPPER = 'wrapper';
    public const TYPE_FILLER = 'filler';
    public const TYPE_RIBBON = 'ribbon';
    public const TYPE_CARD = 'card';
    // The shop's own wrapper as a 3D model, picked as an occasion's bouquet style
    public const TYPE_WRAP_MODEL = 'wrap_model';
    public const TYPES = [self::TYPE_WRAPPER, self::TYPE_FILLER, self::TYPE_RIBBON, self::TYPE_CARD, self::TYPE_WRAP_MODEL];
    /** Types that are a 3D model file */
    public const MODEL_TYPES = [self::TYPE_FILLER, self::TYPE_WRAP_MODEL];

    public const MAX_FILLERS = 5;
    // Copies of the same filler don't count toward the limit; this only stops a runaway save
    public const MAX_FILLER_COPIES = 60;

    // A card's message is capped by words or by letters (spaces don't count)
    public const CARD_LIMIT_WORDS = 'words';
    public const CARD_LIMIT_LETTERS = 'letters';
    public const CARD_SIZES = ['Small', 'Medium', 'Large'];

    protected $fillable = [
        'owner_id',
        'type',
        'name',
        'description',
        'color_hex',
        'pattern',
        'pattern_text',
        'pattern_size',
        'price',
        'is_active',
        'model_url',
        'model_path',
        'image_url',
        'image_path',
        'card_limit_type',
        'card_limit',
        'card_sizes',
        'sort_order',
        // Wrappers and fillers: their stock item (wrapping in sheets, a filler in
        // portions), the same stock ERP Stock & replenishment and Inventory show
        'stock_product_id',
    ];

    protected $casts = [
        'price' => 'float',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'card_limit' => 'integer',
        'pattern_size' => 'integer',
        'card_sizes' => 'array',
        'stock_product_id' => 'integer',
    ];

    protected $hidden = ['model_path', 'image_path'];

    // One custom bouquet uses one sheet of each wrapper layer (outer and inner)
    public const SHEETS_PER_LAYER = 1;
    // ...and one portion ("one bouquet's worth") of each different filler in it,
    // however many copies are placed: the shop only guesses how many bouquets a
    // sack makes, so nobody has to count pieces
    public const PORTIONS_PER_FILLER = 1;

    /**
     * Types whose stock can be counted, and how: the kind of stock item, what
     * one unit is and what it's bought in. With no stock item the count isn't
     * tracked: the shop just switches the option off when it runs out.
     */
    public const STOCK = [
        self::TYPE_WRAPPER => ['kind' => 'wrapping', 'unit' => 'sheet', 'pack' => 'roll'],
        self::TYPE_FILLER  => ['kind' => 'greenery', 'unit' => 'portion', 'pack' => 'sack'],
        // A ribbon by the roll: the shop guesses how many bouquets a roll ties
        self::TYPE_RIBBON  => ['kind' => 'ribbons', 'unit' => 'portion', 'pack' => 'roll'],
    ];

    /** A wrapper's, filler's or ribbon's stock item (wrapping in sheets, a filler or ribbon in portions) */
    public function stockItem(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class, 'stock_product_id');
    }

    /**
     * What custom bouquets take from stock: for each order item, a sheet of its
     * outer and inner wrapper, a portion of each different filler and a portion
     * of its ribbon (those with a stock item), as [stock item id, null, amount × quantity].
     */
    public static function stockLinesFor(iterable $items): array
    {
        $uses = []; // option id => amount used
        foreach ($items as $item) {
            $c = $item->customizations ?? null;
            if (is_string($c)) {
                $c = json_decode($c, true);
            }
            if (! is_array($c)) {
                continue;
            }
            $qty = max(1, (int) ($item->quantity ?? 1));
            // A sheet per wrapper layer, and one portion of the ribbon
            foreach (['wrapper.id', 'inner_wrapper.id', 'ribbon.id'] as $path) {
                $id = (int) data_get($c, $path, 0);
                if ($id > 0) {
                    $uses[$id] = ($uses[$id] ?? 0) + self::SHEETS_PER_LAYER * $qty;
                }
            }
            $fillers = data_get($c, 'fillers', []);
            $fillerIds = is_array($fillers)
                ? array_unique(array_filter(array_map(fn ($f) => (int) data_get($f, 'id', 0), $fillers)))
                : [];
            foreach ($fillerIds as $id) {
                $uses[$id] = ($uses[$id] ?? 0) + self::PORTIONS_PER_FILLER * $qty;
            }
        }
        if (! $uses) {
            return [];
        }

        return static::query()
            ->whereIn('id', array_keys($uses))
            ->whereIn('type', array_keys(self::STOCK))
            ->whereNotNull('stock_product_id')
            ->orderBy('id')
            ->get()
            ->map(fn (self $o) => [(int) $o->stock_product_id, null, $uses[$o->id]])
            ->values()
            ->all();
    }

    /** How much of the card's allowance a message uses */
    public static function countCardMessage(string $message, ?string $limitType): int
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return 0;
        }

        return $limitType === self::CARD_LIMIT_LETTERS
            ? mb_strlen(preg_replace('/\s+/u', '', $trimmed))
            : count(preg_split('/\s+/u', $trimmed));
    }

    public function scopeForOwner(Builder $query, int $ownerId): Builder
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Options a customer can pick: wrappers and fillers only when switched on
     * and, when their stock is counted, not run out; fillers only with a 3D model.
     */
    public function scopeAvailable(Builder $query): Builder
    {
        $stocked = array_keys(self::STOCK);

        return $query
            ->where(fn ($q) => $q->whereNotIn('type', $stocked)->orWhere('is_active', true))
            ->where(fn ($q) => $q->whereNotIn('type', $stocked)
                ->orWhereNull('stock_product_id')
                ->orWhereHas('stockItem', fn ($p) => $p->where('quantity_in_stock', '>=', 1)))
            ->where(fn ($q) => $q->whereNotIn('type', self::MODEL_TYPES)->orWhereNotNull('model_url'));
    }

    /**
     * Price a custom bouquet's extras from the vendor's saved settings, never
     * from client-sent prices: the occasion's base price plus the message card.
     * Wrappers, ribbons and fillers are included in the base price. Returns the
     * extra total and the customizations with the server's names and colours.
     *
     * @return array{ok: bool, message?: string, total: float, customizations: array}
     */
    public static function resolveSelections(int $ownerId, array $customizations): array
    {
        // The occasion preset first: its size caps the flowers and fillers, and
        // parts the occasion doesn't have (a wedding bouquet's wrapper) are dropped
        $preset = BouquetPresets::check(BouquetPresets::forOwner($ownerId), $customizations);
        if (! $preset['ok']) {
            return self::failure($preset['message'], $customizations);
        }
        $customizations = $preset['customizations'];
        $maxFillers = $preset['filler_limit'];
        $base = (float) ($preset['base_price'] ?? 0);

        // 'wrapper' is the outer paper; 'inner_wrapper' the inner layer.
        $wrapperId = (int) data_get($customizations, 'wrapper.id', 0);
        $innerWrapperId = (int) data_get($customizations, 'inner_wrapper.id', 0);
        $ribbonId = (int) data_get($customizations, 'ribbon.id', 0);
        $cardId = (int) data_get($customizations, 'card.id', 0);
        $fillers = data_get($customizations, 'fillers', []);
        $fillers = is_array($fillers) ? array_values($fillers) : [];

        // The limit is on different fillers: the same filler again counts once
        $kinds = collect($fillers)->map(fn ($filler) => (int) data_get($filler, 'id', 0))->unique()->count();
        if ($kinds > $maxFillers) {
            return self::failure('This bouquet can have up to ' . $maxFillers . ' different fillers.', $customizations);
        }
        if (count($fillers) > self::MAX_FILLER_COPIES) {
            return self::failure('This bouquet has too many fillers. Remove some and try again.', $customizations);
        }

        $ids = array_filter(array_merge(
            [$wrapperId, $innerWrapperId, $ribbonId, $cardId],
            array_map(fn ($filler) => (int) data_get($filler, 'id', 0), $fillers)
        ));

        if (!$ids) {
            unset($customizations['wrapper'], $customizations['inner_wrapper'], $customizations['ribbon'], $customizations['card']);
            $customizations['fillers'] = [];
            $customizations['extras_total'] = round($base, 2);

            return ['ok' => true, 'total' => round($base, 2), 'customizations' => $customizations];
        }

        $options = self::forOwner($ownerId)->available()->whereIn('id', array_unique($ids))->get()->keyBy('id');
        $total = $base;

        $selections = [
            'wrapper' => [$wrapperId, self::TYPE_WRAPPER, 'paper_color'],
            'inner_wrapper' => [$innerWrapperId, self::TYPE_WRAPPER, 'inner_paper_color'],
            'ribbon' => [$ribbonId, self::TYPE_RIBBON, 'ribbon_color'],
        ];

        foreach ($selections as $key => [$id, $type, $colorKey]) {
            if (!$id) {
                unset($customizations[$key]);
                continue;
            }

            $option = $options->get($id);
            if (!$option || $option->type !== $type) {
                return self::failure("The selected {$type} is no longer available.", $customizations);
            }

            $customizations[$key] = [
                'id' => $option->id,
                'name' => $option->name,
                'color_hex' => $option->color_hex,
                'pattern' => $option->patternData(),
                'price' => 0.0, // part of the occasion's base price
            ];
            $customizations[$colorKey] = $option->color_hex;
        }

        // Each layer's print (newspaper, words), from the shop's wrapper, never the browser
        $design = $customizations['design'] ?? 'classic';
        foreach (['wrapper_pattern' => $wrapperId, 'inner_wrapper_pattern' => $innerWrapperId] as $patternKey => $id) {
            unset($customizations[$patternKey]);
            $wrapper = $id ? $options->get($id) : null;
            if ($wrapper && $wrapper->patternData() && in_array($design, BouquetPresets::PATTERN_DESIGNS, true)) {
                $customizations[$patternKey] = $wrapper->patternData();
            }
        }

        $resolvedFillers = [];
        foreach ($fillers as $index => $filler) {
            $option = $options->get((int) data_get($filler, 'id', 0));
            if (!$option || $option->type !== self::TYPE_FILLER) {
                return self::failure('A selected filler is no longer available.', $customizations);
            }

            $resolvedFillers[] = [
                'scene_id' => (string) data_get($filler, 'scene_id', "filler-{$index}"),
                'id' => $option->id,
                'name' => $option->name,
                'model_3d_url' => $option->model_url,
                'price' => 0.0, // part of the occasion's base price
                'placement_order' => $index,
                'position' => (array) data_get($filler, 'position', []),
                'rotation' => (array) data_get($filler, 'rotation', []),
                'scale' => (float) data_get($filler, 'scale', 1),
                'locked' => (bool) data_get($filler, 'locked', false),
                'group' => self::cleanGroup(data_get($filler, 'group')),
                'sprigs' => self::cleanSprigs(data_get($filler, 'sprigs')),
                // Its pieces were arranged by hand, so adding fillers doesn't re-lay them
                'arranged' => (bool) data_get($filler, 'arranged', false),
            ];
        }

        $customizations['fillers'] = $resolvedFillers;

        // Message card: price from the chosen size, message within the cap
        if ($cardId) {
            $option = $options->get($cardId);
            if (!$option || $option->type !== self::TYPE_CARD) {
                return self::failure('The selected card is no longer available.', $customizations);
            }

            $sizeLabel = (string) data_get($customizations, 'card.size', '');
            $size = collect($option->card_sizes ?? [])->first(fn ($s) => ($s['label'] ?? null) === $sizeLabel);
            if (!$size) {
                return self::failure("{$option->name} isn't available in that size any more. Choose another size.", $customizations);
            }

            $message = trim((string) data_get($customizations, 'card.message', ''));
            $used = self::countCardMessage($message, $option->card_limit_type);
            if ($option->card_limit && $used > $option->card_limit) {
                return self::failure(
                    "Your card message is {$used} {$option->card_limit_type}; this card allows up to {$option->card_limit}.",
                    $customizations
                );
            }

            $customizations['card'] = [
                'scene_id' => (string) data_get($customizations, 'card.scene_id', 'card'),
                'id' => $option->id,
                'name' => $option->name,
                'image_url' => $option->image_url,
                'size' => $size['label'],
                'price' => (float) $size['price'],
                'limit_type' => $option->card_limit_type,
                'limit' => $option->card_limit,
                'message' => $message,
                'open' => (bool) data_get($customizations, 'card.open', false),
                'position' => (array) data_get($customizations, 'card.position', []),
                'rotation' => (array) data_get($customizations, 'card.rotation', []),
                'scale' => (float) data_get($customizations, 'card.scale', 1),
                'locked' => (bool) data_get($customizations, 'card.locked', false),
                'group' => self::cleanGroup(data_get($customizations, 'card.group')),
            ];
            $total += (float) $size['price'];
        } else {
            unset($customizations['card']);
        }

        $customizations['extras_total'] = round($total, 2);

        return ['ok' => true, 'total' => round($total, 2), 'customizations' => $customizations];
    }

    /** The wrapper's print as the customizer and viewer read it, or null for plain paper */
    public function patternData(): ?array
    {
        return $this->type === self::TYPE_WRAPPER
            ? BouquetPresets::cleanPattern(['type' => $this->pattern, 'text' => $this->pattern_text, 'size' => $this->pattern_size])
            : null;
    }

    /** A group id from the customizer (items in a group move together), or null */
    public static function cleanGroup($group): ?string
    {
        return is_string($group) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $group) ? $group : null;
    }

    private static function failure(string $message, array $customizations): array
    {
        return ['ok' => false, 'message' => $message, 'total' => 0.0, 'customizations' => $customizations];
    }

    /**
     * A filler's pieces of greenery on the original bouquet's spots, each
     * { i: spot, a: turn, r: spin, k: size, p: how far out } (see frontend
     * utils/fillerLayout.js). Anything else is dropped, so a filler without
     * valid pieces just shows the older way.
     */
    private static function cleanSprigs($sprigs): ?array
    {
        if (!is_array($sprigs)) {
            return null;
        }

        $clean = [];
        foreach (array_slice(array_values($sprigs), 0, 60) as $sprig) {
            $spot = data_get($sprig, 'i');
            if (!is_numeric($spot) || $spot < 0 || $spot > 200) {
                continue;
            }
            $number = fn ($key, $default, $min, $max) => is_numeric(data_get($sprig, $key))
                ? max($min, min($max, round((float) data_get($sprig, $key), 4)))
                : $default;
            $piece = [
                'i' => (int) $spot,
                'a' => $number('a', 0.0, -10.0, 10.0),
                'r' => $number('r', 0.0, -10.0, 10.0),
                'k' => $number('k', 1.0, 0.2, 2.0),
                'p' => $number('p', 1.0, 0.0, 1.5),
            ];
            // Moved on its own: [x, y, z, qx, qy, qz, qw, size] in the filler's frame
            $pose = is_array(data_get($sprig, 't')) ? array_values(data_get($sprig, 't')) : [];
            if (count($pose) === 8 && collect($pose)->every(fn ($v) => is_numeric($v) && abs((float) $v) <= 50) && (float) $pose[7] > 0) {
                $piece['t'] = array_map(fn ($v) => round((float) $v, 5), $pose);
            }
            $clean[] = $piece;
        }

        return $clean ?: null;
    }
}
