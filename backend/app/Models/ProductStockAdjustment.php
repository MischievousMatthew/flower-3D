<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One manual change to a product's stock, with the reason the vendor gave. */
class ProductStockAdjustment extends Model
{
    public const UPDATED_AT = null;

    public const REASONS = [
        'restock' => 'Restocked',
        'sold_offline' => 'Sold in store',
        'damaged' => 'Damaged or wilted',
        'returned' => 'Customer return',
        'count' => 'Stock count',
        'other' => 'Other',
    ];

    protected $fillable = [
        'owner_id', 'product_id', 'change', 'quantity_before', 'quantity_after', 'reason', 'note', 'actor_name',
    ];

    protected $casts = [
        'change' => 'integer',
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'created_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes()->withTrashed();
    }
}
