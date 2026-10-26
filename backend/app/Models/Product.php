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

    protected static function booted(): void
    {
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
