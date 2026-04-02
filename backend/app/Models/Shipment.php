<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shipment extends Model
{
    use BelongsToOwner;

    public $timestamps = false;

    protected $fillable = [
        'owner_id',
        'purchase_order_id',
        'tracking_number',
        'carrier',
        'shipped_date',
        'received_date',
        'status',
    ];

    protected $casts = [
        'shipped_date'  => 'date',
        'received_date' => 'date',
        'status'        => 'string',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * A shipment belongs to one purchase order.
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * A shipment has many shipment items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShipmentItem::class);
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeInTransit($query)
    {
        return $query->where('status', 'in_transit');
    }

    public function scopeDelivered($query)
    {
        return $query->where('status', 'delivered');
    }
}
