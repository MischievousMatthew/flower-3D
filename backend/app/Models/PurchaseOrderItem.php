<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseOrderItem extends Model
{
    public $timestamps = false; // ← FIX: table has no created_at / updated_at columns

    protected $fillable = [
        'purchase_order_id',
        'product_id',          // ← ADDED: FK to products table
        'product_name',
        'quantity',
        'price',
        'subtotal',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price'    => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

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