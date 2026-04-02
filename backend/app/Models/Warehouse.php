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
     * A warehouse holds many items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(WarehouseItem::class);
    }

    /**
     * A warehouse has many inventory movements through its items.
     */
    public function inventoryMovements(): HasManyThrough
    {
        return $this->hasManyThrough(InventoryMovement::class, WarehouseItem::class);
    }
}
