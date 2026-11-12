<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    use BelongsToOwner, SoftDeletes;

    // BloomCraft's moderation of a listing (the vendor's own `status` is separate)
    public const MODERATION_PENDING = 'pending';         // new, live, not reviewed yet
    public const MODERATION_APPROVED = 'approved';       // reviewed, live
    public const MODERATION_HIDDEN = 'hidden';           // taken down until the vendor fixes it
    public const MODERATION_RESUBMITTED = 'resubmitted'; // fixed by the vendor, still down until reviewed
    public const MODERATION_REMOVED = 'removed';         // taken down for good
    public const MODERATION_LIVE = [self::MODERATION_PENDING, self::MODERATION_APPROVED];
    // A material the shop uses but doesn't sell (wrapping, ribbon, vases…):
    // counted in stock, never shown to customers or in the shop's product lists
    public const MODERATION_INTERNAL = 'internal';

    /**
     * Products customers may see: not taken down by BloomCraft and not from a
     * suspended vendor. Callers still check the vendor's own status.
     */
    public function scopeListedForCustomers($query)
    {
        return $query
            ->whereIn('moderation_status', self::MODERATION_LIVE)
            ->whereNotIn('owner_id', User::query()->where('is_suspended', true)->select('id'));
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductReport::class);
    }

    // ── Stock per flower colour ─────────────────────────────────────────────
    // A product sold in several flower colours keeps a count for each one in
    // color_options: [{name, value?, hex?, quantity}]. quantity_in_stock is then
    // always the sum of those counts.

    /** True when the shop keeps a separate count for each colour */
    public function tracksColorStock(): bool
    {
        return collect(is_array($this->color_options) ? $this->color_options : [])
            ->contains(fn ($c) => is_array($c) && isset($c['quantity']) && is_numeric($c['quantity']));
    }

    /** Index of a colour in color_options (case-insensitive name), or null */
    public function colorIndex(?string $color): ?int
    {
        $wanted = mb_strtolower(trim((string) $color));
        if ($wanted === '' || ! is_array($this->color_options)) {
            return null;
        }
        foreach (array_values($this->color_options) as $i => $c) {
            if (is_array($c) && mb_strtolower(trim((string) ($c['name'] ?? ''))) === $wanted) {
                return $i;
            }
        }
        return null;
    }

    /** How many a customer can buy in this colour (the whole stock when colours aren't counted separately) */
    public function stockFor(?string $color): int
    {
        if ($this->tracksColorStock()) {
            $i = $this->colorIndex($color);
            if ($i !== null) {
                return max(0, (int) (array_values($this->color_options)[$i]['quantity'] ?? 0));
            }
        }
        return max(0, (int) $this->quantity_in_stock);
    }

    /**
     * Takes $qty off the shelf, from $color's own count when colours are
     * counted. Locks the row, so call it inside a transaction. Returns the
     * product, or null when there isn't enough (nothing is changed then).
     */
    public static function takeStock(int $productId, int $qty, ?string $color = null): ?self
    {
        $product = static::query()->whereKey($productId)->lockForUpdate()->first();
        if (! $product || $qty <= 0) {
            return $product;
        }
        if ($product->stockFor($color) < $qty || (int) $product->quantity_in_stock < $qty) {
            return null;
        }
        $product->shiftStock(-$qty, $color);
        return $product;
    }

    /** Puts $qty back (a cancelled or unpaid order), on $color's own count when colours are counted */
    public static function putBackStock(int $productId, int $qty, ?string $color = null): void
    {
        $product = static::withTrashed()->whereKey($productId)->lockForUpdate()->first();
        if ($product && $qty > 0) {
            $product->shiftStock($qty, $color);
        }
    }

    /** Changes the stock by $delta, on one colour when it is a counted colour, and saves */
    private function shiftStock(int $delta, ?string $color): void
    {
        $i = $this->tracksColorStock() ? $this->colorIndex($color) : null;
        if ($i !== null) {
            $options = array_values($this->color_options);
            $options[$i]['quantity'] = max(0, (int) ($options[$i]['quantity'] ?? 0) + $delta);
            $this->color_options = $options; // the saving hook sets the total from the colours
        } else {
            $this->quantity_in_stock = max(0, (int) $this->quantity_in_stock + $delta);
        }
        $this->save();
    }

    /**
     * Keeps the colour counts and the total in step on every save:
     * - colours changed (the product form, an order for one colour): total = their sum
     * - only the total changed (inventory − / +, receiving, warehouse batches):
     *   a drop comes off the colours with the most first, a rise goes to the first colour
     */
    private function syncColorStock(): void
    {
        if (! $this->tracksColorStock()) {
            return;
        }
        $options = array_values(array_filter($this->color_options, 'is_array'));
        foreach ($options as &$c) {
            $c['quantity'] = max(0, (int) ($c['quantity'] ?? 0));
        }
        unset($c);
        $sum = array_sum(array_column($options, 'quantity'));

        if (! $this->isDirty('color_options') && $this->isDirty('quantity_in_stock')) {
            $diff = max(0, (int) $this->quantity_in_stock) - $sum;
            if ($diff > 0) {
                $options[0]['quantity'] += $diff;
            } elseif ($diff < 0) {
                $order = array_keys($options);
                usort($order, fn ($a, $b) => $options[$b]['quantity'] <=> $options[$a]['quantity']);
                $left = -$diff;
                foreach ($order as $i) {
                    $take = min($left, $options[$i]['quantity']);
                    $options[$i]['quantity'] -= $take;
                    $left -= $take;
                    if ($left <= 0) {
                        break;
                    }
                }
            }
            $sum = array_sum(array_column($options, 'quantity'));
        }

        $this->color_options = $options;
        $this->quantity_in_stock = $sum;
    }

    protected static function booted(): void
    {
        static::saving(fn (Product $product) => $product->syncColorStock());

        // Low-stock alert: tell the shop owner once, when stock drops to or
        // below the minimum (not again until it has been refilled above it)
        static::updated(function (Product $product) {
            if (! $product->wasChanged('quantity_in_stock')) {
                return;
            }
            $min = (int) $product->min_stock_level;
            $before = (int) $product->getOriginal('quantity_in_stock');
            $after = (int) $product->quantity_in_stock;
            if ($min <= 0 || $before <= $min || $after > $min || ! $product->owner_id) {
                return;
            }
            try {
                app(\App\Services\NotificationService::class)->notify(
                    [$product->owner_id],
                    'low_stock',
                    $after <= 0 ? "Out of stock: {$product->product_name}" : "Running low: {$product->product_name}",
                    $after <= 0 ? "Nothing left. The minimum is {$min}." : "{$after} left, at or under the minimum of {$min}.",
                    '/erp/procurement/inventory/stock',
                );
            } catch (\Throwable $e) {
                // An alert must never stop the stock change itself
                \Illuminate\Support\Facades\Log::warning('Low-stock alert not sent', ['product_id' => $product->id, 'error' => $e->getMessage()]);
            }
        });
    }

    /** Products the shop sells: everything except internal materials */
    public function scopeForSale($query)
    {
        return $query->where(fn ($q) => $q->whereNull('moderation_status')->orWhere('moderation_status', '!=', self::MODERATION_INTERNAL));
    }

    public function isMaterial(): bool
    {
        return $this->moderation_status === self::MODERATION_INTERNAL;
    }

    public function isTakenDown(): bool
    {
        if ($this->isMaterial()) {
            return false;
        }
        return !in_array($this->moderation_status ?? self::MODERATION_APPROVED, self::MODERATION_LIVE, true);
    }

    /** Every new listing is live straight away and waits in the admin's "Needs review" queue */
    protected $attributes = [
        'moderation_status' => self::MODERATION_PENDING,
    ];

    protected $fillable = [
        // Identity
        'product_name',
        'description',
        'sku',
        'category',
        'flower_type',
        'color',
        'color_other',
        'season',

        // Pricing
        'price',
        'cost_price',
        'purchase_price',
        'selling_price',
        'has_discount',
        'discount_price',
        'discount_starts_at',
        'discount_ends_at',
        'selling_type',

        // Stock
        'quantity_in_stock',
        'min_stock_level',
        'max_stock_level',
        'stock_kind',
        'stock_unit',

        // Supplier
        'supplier_id',
        'supplier_name',
        'supplier_contact',
        'supplier_sku',
        'preparation_days',
        'supplier_lead_time',

        // Product attributes
        'is_fragile',
        'requires_refrigeration',
        'care_instructions',
        'occasion_tags',
        'notes',
        'color_options', // [{name, value?, hex?, quantity?}] colours the customer can pick, each with its own stock
        'composition',   // [{flower, color?, count}] flowers in the bouquet

        // Status
        'status',

        // Owner
        'owner_id',
        'vendor_id',
    ];

    protected $casts = [
        'moderated_at'           => 'datetime',
        'is_fragile'             => 'boolean',
        'requires_refrigeration' => 'boolean',
        'has_discount'           => 'boolean',   // Cast handles 0/1 → false/true automatically
        'price'                  => 'decimal:2',
        'cost_price'             => 'decimal:2',
        'purchase_price'         => 'decimal:2',
        'selling_price'          => 'decimal:2',
        'discount_price'         => 'decimal:2',
        'discount_starts_at'     => 'datetime',
        'discount_ends_at'       => 'datetime',
        'occasion_tags'          => 'array',
        'color_options'          => 'array',
        'composition'            => 'array',
        'preparation_days'       => 'integer',
        'supplier_lead_time'     => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────────
    
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('display_order')->orderBy('id');
    }

    public function primaryImage(): HasOne
    {
        return $this->hasOne(ProductImage::class)->where('is_primary', true)->oldestOfMany();
    }

    public function models(): HasMany
    {
        return $this->hasMany(ProductModel::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(WarehouseBatch::class);
    }

    public function activeBatches(): HasMany
    {
        return $this->hasMany(WarehouseBatch::class)->where('status', 'active');
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    /**
     * selling_price falls back to the legacy `price` column for older records.
     */
    public function getSellingPriceAttribute($value): float
    {
        return (float) ($value ?? 0);
    }

    /**
     * When true, discount_price reads the stored value as-is. The vendor's own
     * product screens set this so their edit forms never pick up (and save) a
     * promotion price as the product's own discount.
     */
    public static bool $rawPricing = false;

    /**
     * The sale price customers pay right now, or null when there is none:
     * the product's own discount while its dates allow it, or a running
     * promotion from the shop, whichever is lower.
     */
    public function getDiscountPriceAttribute($value): ?string
    {
        if (static::$rawPricing) {
            return $value === null ? null : number_format((float) $value, 2, '.', '');
        }

        $best = $this->ownDiscountNow($value);
        $promotion = $this->runningPromotion();
        if ($promotion && ($best === null || $promotion['price'] < $best)) {
            $best = $promotion['price'];
        }

        return $best === null ? null : number_format($best, 2, '.', '');
    }

    /** The product's own discount if it is set, lower than the price and inside its dates. */
    public function ownDiscountNow($value = null): ?float
    {
        $value ??= $this->attributes['discount_price'] ?? null;
        $price = (float) ($this->attributes['selling_price'] ?? 0);
        if ($value === null || (float) $value <= 0 || (float) $value >= $price) {
            return null;
        }

        $now = now();
        $starts = $this->discount_starts_at;
        $ends = $this->discount_ends_at;
        if (($starts && $starts->gt($now)) || ($ends && $ends->lt($now))) {
            return null;
        }

        return (float) $value;
    }

    /** The best running shop promotion for this product: ['promotion' => VendorPromotion, 'price' => float]. */
    public function runningPromotion(): ?array
    {
        $ownerId = (int) ($this->attributes['owner_id'] ?? 0);
        $price = (float) ($this->attributes['selling_price'] ?? 0);
        if (! $ownerId || $price <= 0) {
            return null;
        }

        $best = null;
        foreach (VendorPromotion::runningFor($ownerId) as $promotion) {
            $after = $promotion->covers($this) ? $promotion->priceFor($price) : null;
            if ($after !== null && ($best === null || $after < $best['price'])) {
                $best = ['promotion' => $promotion, 'price' => $after];
            }
        }

        return $best;
    }

    // ── Stock helpers ──────────────────────────────────────────────────────

    public function getWarehouseQtyAttribute(): int
    {
        return $this->activeBatches()->sum('qty_remaining');
    }

    public function getPreparationDaysAttribute($value): int
    {
        return max(0, (int) ($value ?? $this->attributes['supplier_lead_time'] ?? 0));
    }

    public function setPreparationDaysAttribute($value): void
    {
        $normalized = max(0, (int) $value);
        $this->attributes['preparation_days'] = $normalized;
        $this->attributes['supplier_lead_time'] = $normalized;
    }

    public function setSupplierLeadTimeAttribute($value): void
    {
        $normalized = max(0, (int) $value);
        $this->attributes['supplier_lead_time'] = $normalized;
        $this->attributes['preparation_days'] = $normalized;
    }

    public function hasExpiringBatch(int $days = 3): bool
    {
        return $this->activeBatches()
            ->where('expiration_date', '<=', now()->addDays($days))
            ->exists();
    }
}
