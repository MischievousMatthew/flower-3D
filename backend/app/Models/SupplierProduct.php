<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a supplier's catalog: a flower, a wrap, a box, with its price
 * per unit and the least the supplier will sell.
 */
class SupplierProduct extends Model
{
    use BelongsToOwner;

    public const UNITS = ['stem', 'bunch', 'box', 'piece', 'roll', 'pack', 'kg'];

    public const CATEGORIES = ['flowers', 'greenery', 'packaging', 'supplies'];

    protected $fillable = [
        'owner_id', 'supplier_id', 'name', 'category', 'unit', 'price', 'min_qty',
        'product_id', 'is_available', 'notes', 'price_updated_at',
    ];

    protected $casts = [
        'price'            => 'decimal:2',
        'min_qty'          => 'integer',
        'is_available'     => 'boolean',
        'price_updated_at' => 'datetime',
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
