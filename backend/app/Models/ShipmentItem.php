<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ShipmentItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shipment_id',
        'warehouse_item_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * A shipment item belongs to one shipment.
     */
    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }

    /**
     * A shipment item references one warehouse item.
     */
    public function warehouseItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseItem::class);
    }
}