<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One delivery received against a purchase order, with its inspection.
 */
class PurchaseOrderReceipt extends Model
{
    use BelongsToOwner;

    /** How a delivered line can look at inspection */
    public const CONDITIONS = ['fresh', 'slightly_open', 'wilting', 'damaged'];

    /** Why stems were turned away */
    public const REJECT_REASONS = ['wilted', 'damaged', 'pests_mold', 'wrong_item', 'short_stems', 'wrong_color', 'other'];

    /** What the inspector ticks for each line */
    public const CHECKS = ['stems_firm', 'heads_intact', 'no_pests', 'right_variety', 'packed_well'];

    protected $fillable = [
        'owner_id', 'purchase_order_id', 'receipt_number', 'delivery_ref', 'received_date',
        'delivered_total', 'accepted_total', 'rejected_total', 'missing_total', 'notes',
        'received_by_type', 'received_by_id', 'received_by_name',
    ];

    protected $casts = [
        'received_date'   => 'date:Y-m-d',
        'delivered_total' => 'integer',
        'accepted_total'  => 'integer',
        'rejected_total'  => 'integer',
        'missing_total'   => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptLine::class, 'receipt_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(PurchaseOrderReceiptFile::class, 'receipt_id');
    }
}
