<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A product an admin has picked for the shop's "Featured" row */
class FeaturedProduct extends Model
{
    protected $fillable = ['product_id', 'sort_order', 'added_by'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
