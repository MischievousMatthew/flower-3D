<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WarehouseItem extends Model
{
    use BelongsToOwner;
    public $timestamps = false;

    protected $fillable = [
        'owner_id',
        'warehouse_id',
        'product_name',
        'sku',
        'quantity',
        'barcode',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected $dates = ['created_at'];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * A warehouse item belongs to one warehouse.
     */
    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * A warehouse item has many inventory movements.
     */
    public function inventoryMovements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * A warehouse item can appear in many shipment items.
     */
    public function shipmentItems(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Increment stock and record an IN movement.
     */
    public function receiveStock(int $qty, ?string $reference = null): InventoryMovement
    {
        $this->increment('quantity', $qty);

        return $this->inventoryMovements()->create([
            'owner_id'  => $this->owner_id,
            'type'      => 'IN',
            'quantity'  => $qty,
            'reference' => $reference,
        ]);
    }

    /**
     * Decrement stock and record an OUT movement.
     *
     * @throws \RuntimeException when insufficient stock.
     */
    public function dispatchStock(int $qty, ?string $reference = null): InventoryMovement
    {
        if ($this->quantity < $qty) {
            throw new \RuntimeException("Insufficient stock for SKU [{$this->sku}].");
        }

        $this->decrement('quantity', $qty);

        return $this->inventoryMovements()->create([
            'owner_id'  => $this->owner_id,
            'type'      => 'OUT',
            'quantity'  => $qty,
            'reference' => $reference,
        ]);
    }
}
