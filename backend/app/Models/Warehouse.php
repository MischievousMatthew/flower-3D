<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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
     * A warehouse has many inventory movements through its items.
     */
    public function inventoryMovements(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryMovement::class, WarehouseItem::class);
    }
}
