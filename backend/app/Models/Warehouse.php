<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasManyThrough as HasManyThroughRelation;

class Warehouse extends Model
{
    use BelongsToOwner;
    public $timestamps = false; // only created_at defined in schema

    protected $fillable = [
        'owner_id',
        'name',
        'location',
        'manager',
    ];

    protected $dates = ['created_at'];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * A warehouse holds many items (SKU summaries).
     */
    public function items(): HasMany
    {
        return $this->hasMany(WarehouseItem::class);
    }

    /**
     * A warehouse contains multiple storage locations (Storages/Vaults).
     */
    public function locations(): HasMany
    {
        return $this->hasMany(WarehouseLocation::class, 'warehouse_id');
    }

    /**
     * Alias used by the UI/business language: warehouse -> storages.
     */
    public function storages(): HasMany
    {
        return $this->locations();
    }

    /**
     * All flower batches stored in all locations of this warehouse.
     */
    public function batches(): HasManyThrough
    {
        return $this->hasManyThrough(
            WarehouseBatch::class,
            WarehouseLocation::class,
            'warehouse_id',           // Foreign key on locations table
            'warehouse_location_id', // Foreign key on batches table
            'id',                     // Local key on warehouses table
            'id'                      // Local key on locations table
        );
    }

    /**
     * Alias used by the UI/business language: warehouse -> flowers.
     * Backed by physical flower batches stored across linked locations.
     */
    public function flowers(): HasManyThroughRelation
    {
        return $this->batches();
    }

    /**
     * Only valid, countable flower inventory for warehouse totals.
     */
    public function activeFlowerBatches(): HasManyThroughRelation
    {
        return $this->batches()
            ->where('warehouse_batches.status', 'active')
            ->where('warehouse_batches.qty_remaining', '>', 0)
            ->whereHas('product', fn ($query) => $query->whereNull('deleted_at'));
    }

    /**
     * A warehouse has many inventory movements through its items.
     */
    public function inventoryMovements(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryMovement::class, WarehouseItem::class);
    }
}
