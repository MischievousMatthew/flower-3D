<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryMovement extends Model
{
    use BelongsToOwner;
    public $timestamps = false; // only created_at defined in schema

    protected $fillable = [
        'owner_id',
        'warehouse_item_id',
        'type',
        'quantity',
        'reference',
    ];

    protected $casts = [
        'type'     => 'string',
        'quantity' => 'integer',
    ];

    protected $dates = ['created_at'];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * A movement belongs to one warehouse item.
     */
    public function warehouseItem(): BelongsTo
    {
        return $this->belongsTo(WarehouseItem::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeInbound($query)
    {
        return $query->where('type', 'IN');
    }

    public function scopeOutbound($query)
    {
        return $query->where('type', 'OUT');
    }
}
