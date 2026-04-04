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
        'selling_type',

        // Stock
        'quantity_in_stock',
        'min_stock_level',
        'max_stock_level',

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
        'is_fragile'             => 'boolean',
        'requires_refrigeration' => 'boolean',
        'has_discount'           => 'boolean',   // Cast handles 0/1 → false/true automatically
        'price'                  => 'decimal:2',
        'cost_price'             => 'decimal:2',
        'purchase_price'         => 'decimal:2',
        'selling_price'          => 'decimal:2',
        'discount_price'         => 'decimal:2',
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
