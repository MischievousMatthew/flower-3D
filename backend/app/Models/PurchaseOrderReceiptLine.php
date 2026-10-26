<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a delivery: what came, what passed inspection, what didn't.
 */
class PurchaseOrderReceiptLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'receipt_id', 'purchase_order_item_id', 'product_id', 'product_name', 'expected_qty', 'delivered_qty',
        'accepted_qty', 'rejected_qty', 'missing_qty', 'condition', 'checks', 'reject_reason', 'close_short',
        'warehouse_batch_id', 'notes',
    ];

    protected $casts = [
        'checks'        => 'array',
        'close_short'   => 'boolean',
        'expected_qty'  => 'integer',
        'delivered_qty' => 'integer',
        'accepted_qty'  => 'integer',
        'rejected_qty'  => 'integer',
        'missing_qty'   => 'integer',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderReceipt::class, 'receipt_id');
    }
}
