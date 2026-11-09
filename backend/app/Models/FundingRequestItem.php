<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One product line on a funding request */
class FundingRequestItem extends Model
{
    protected $table = 'funding_request_items';

    protected $fillable = [
        'funding_request_id',
        'product_id',
        'product_name',
        'sku',
        'uom',
        'current_stock',
        'quantity',
        'approved_quantity',
        'unit_cost',
        'line_total',
    ];

    protected $casts = [
        'quantity'      => 'integer',
        'approved_quantity' => 'integer',
        'current_stock' => 'integer',
        'unit_cost'     => 'decimal:2',
        'line_total'    => 'decimal:2',
    ];

    public function fundingRequest(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
