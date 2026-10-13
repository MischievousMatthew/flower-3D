<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A vendor's seasonal offer: a percentage or a fixed amount off a set of
 * products between two dates. Nothing is written onto the products; the
 * price is worked out when it is read (see Product::getDiscountPriceAttribute),
 * so an offer starts and ends on time without a scheduler.
 */
class VendorPromotion extends Model
{
    public const PERCENT = 'percent';
    public const FIXED = 'fixed';
    public const APPLIES_TO = ['all', 'category', 'occasion', 'products'];

    protected $fillable = [
        'owner_id', 'name', 'discount_type', 'discount_value', 'applies_to', 'targets', 'starts_at', 'ends_at', 'is_active',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'targets' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /** Active promotions per owner, kept briefly so a product list costs one query per shop. */
    private static array $runningCache = [];

    public static function runningFor(int $ownerId): Collection
    {
        $cached = self::$runningCache[$ownerId] ?? null;
        if ($cached && $cached['at'] > microtime(true) - 30) {
            return $cached['items'];
        }

        $now = now();
        $items = static::query()
            ->where('owner_id', $ownerId)
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->get();

        self::$runningCache[$ownerId] = ['at' => microtime(true), 'items' => $items];

        return $items;
    }

    public static function forgetCache(?int $ownerId = null): void
    {
        if ($ownerId === null) {
            self::$runningCache = [];
        } else {
            unset(self::$runningCache[$ownerId]);
        }
    }

    public function state(): string
    {
        if (! $this->is_active) {
            return 'paused';
        }
        if ($this->starts_at->isFuture()) {
            return 'scheduled';
        }

        return $this->ends_at->isPast() ? 'ended' : 'running';
    }

    public function covers(Product $product): bool
    {
        $targets = $this->targets ?? [];

        return match ($this->applies_to) {
            'all' => true,
            'category' => in_array($product->category, $targets, true),
            'occasion' => (bool) array_intersect($targets, (array) ($product->occasion_tags ?? [])),
            'products' => in_array($product->id, array_map('intval', $targets), true),
            default => false,
        };
    }

    /** The price after this offer, or null when it would not lower the price. */
    public function priceFor(float $price): ?float
    {
        $value = (float) $this->discount_value;
        $after = $this->discount_type === self::PERCENT
            ? $price * (1 - min($value, 100) / 100)
            : $price - $value;
        $after = round($after, 2);

        return $after > 0 && $after < $price ? $after : null;
    }
}
