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
        // How many people can use it (null = no limit); uses_count is only changed by claim / release
        'max_uses',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'targets' => 'array',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'boolean',
        'max_uses' => 'integer',
        'uses_count' => 'integer',
    ];

    /** Every spot is taken */
    public function isFull(): bool
    {
        return $this->max_uses !== null && (int) $this->uses_count >= $this->max_uses;
    }

    /** Spots left, or null with no limit */
    public function spotsLeft(): ?int
    {
        return $this->max_uses === null ? null : max(0, $this->max_uses - (int) $this->uses_count);
    }

    /** Active promotions per owner, kept briefly so a product list costs one query per shop. */
    private static array $runningCache = [];

    /** Promotions the signed-in customer already used, per user (once each) */
    private static array $usedBy = [];

    /**
     * The shop's running promotions this customer can still get: not full, and
     * not one they already used. (Without a signed-in customer, only "not full".)
     */
    public static function runningFor(int $ownerId): Collection
    {
        $userId = \Illuminate\Support\Facades\Auth::id();
        $used = $userId ? self::usedBy((int) $userId) : [];

        return self::allRunningFor($ownerId)
            ->reject(fn (self $p) => $p->isFull() || in_array($p->id, $used, true))
            ->values();
    }

    /** Ids of the promotions a customer has used (a pending order counts until it's cancelled) */
    public static function usedBy(int $userId): array
    {
        return self::$usedBy[$userId] ??= \Illuminate\Support\Facades\DB::table('promotion_redemptions')
            ->where('user_id', $userId)->pluck('promotion_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * Takes one spot of each promotion for the order, inside the order's
     * transaction. The count only goes up while it's below max_uses, in one
     * UPDATE, so two customers can't both take the last spot (the loser gets
     * PromotionUnavailableException and the order is rolled back). A customer
     * who already used a promotion can't use it again.
     *
     * @param int[] $promotionIds
     */
    public static function claim(array $promotionIds, int $userId, int $orderId): void
    {
        foreach (array_unique(array_map('intval', $promotionIds)) as $id) {
            $promotion = static::query()->find($id);
            if (! $promotion) {
                continue;
            }

            $taken = static::query()
                ->whereKey($id)
                ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('uses_count', '<', 'max_uses'))
                ->increment('uses_count');
            if ($taken !== 1) {
                throw new \App\Exceptions\PromotionUnavailableException($promotion->name);
            }

            // One per customer (unique promotion + user). Its own savepoint, so a
            // second order at the same moment fails cleanly instead of breaking
            // the order's transaction.
            try {
                \Illuminate\Support\Facades\DB::transaction(fn () => \Illuminate\Support\Facades\DB::table('promotion_redemptions')->insert([
                    'promotion_id' => $id, 'user_id' => $userId, 'order_id' => $orderId,
                    'created_at' => now(), 'updated_at' => now(),
                ]));
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                throw new \App\Exceptions\PromotionUnavailableException($promotion->name, true);
            }
        }
        unset(self::$usedBy[$userId]);
        self::forgetCache();
    }

    /**
     * Gives back the spots an order held (cancelled, or its payment failed).
     * Safe to call more than once: a spot is only given back when its
     * redemption row was still there, and the count never goes below 0.
     */
    public static function release(int $orderId): void
    {
        $rows = \Illuminate\Support\Facades\DB::table('promotion_redemptions')->where('order_id', $orderId)->get(['id', 'promotion_id', 'user_id']);
        foreach ($rows as $row) {
            $deleted = \Illuminate\Support\Facades\DB::table('promotion_redemptions')->where('id', $row->id)->delete();
            if ($deleted === 1) {
                static::query()->whereKey($row->promotion_id)->where('uses_count', '>', 0)->decrement('uses_count');
            }
            unset(self::$usedBy[(int) $row->user_id]);
        }
        if ($rows->isNotEmpty()) {
            self::forgetCache();
        }
    }

    /** Every running promotion of the shop, before the per-customer and "full" checks */
    private static function allRunningFor(int $ownerId): Collection
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
        if ($this->ends_at->isPast()) {
            return 'ended';
        }

        // Every spot taken: customers see the regular price (until a cancelled order frees one)
        return $this->isFull() ? 'full' : 'running';
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
