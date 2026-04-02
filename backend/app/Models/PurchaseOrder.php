<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PurchaseOrder extends Model
{
    use BelongsToOwner;

    public $timestamps = false; // only created_at defined in schema

    protected $fillable = [
        'owner_id',
        'supplier_id',
        'order_number',
        'status',
        'total_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    protected $dates = ['created_at'];

    // ─── Relationships ────────────────────────────────────────────────────────

    /**
     * A purchase order belongs to one supplier.
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * A purchase order has many line items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /**
     * A purchase order has one shipment.
     */
    public function shipment(): HasOne
    {
        return $this->hasOne(Shipment::class);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Recalculate and persist total_amount from order items.
     */
    public function recalculateTotal(): static
    {
        $this->total_amount = $this->items()->sum('subtotal');
        $this->save();

        return $this;
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
