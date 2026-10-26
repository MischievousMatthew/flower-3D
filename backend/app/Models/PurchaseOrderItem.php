<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    use BelongsToOwner;

    public $timestamps = false; // ← FIX: table has no created_at / updated_at columns

    protected $fillable = [
        'owner_id',
        'purchase_order_id',
        'product_id',          // ← ADDED: FK to products table
        'product_name',
        'quantity',
        'price',
        'subtotal',
        'received_qty',
        'rejected_qty',
        'cancelled_qty',
        'unit',
        'supplier_product_id',
    ];

    protected $casts = [
        'quantity'     => 'integer',
        'received_qty'  => 'integer',
        'rejected_qty'  => 'integer',
        'cancelled_qty' => 'integer',
        'price'    => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    /** Still to come: ordered, less what was accepted and what won't come */
    public function outstanding(): int
    {
        return max(0, (int) $this->quantity - (int) $this->received_qty - (int) $this->cancelled_qty);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /**
     * The catalog product this line item refers to.
     * Uses withTrashed() so soft-deleted products don't break old orders.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withTrashed();
    }
}
