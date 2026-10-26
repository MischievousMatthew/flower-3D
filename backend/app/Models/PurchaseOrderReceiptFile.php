<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A delivery receipt (or invoice) or an inspection photo for a delivery.
 */
class PurchaseOrderReceiptFile extends Model
{
    public $timestamps = false;

    protected $fillable = ['owner_id', 'receipt_id', 'kind', 'url', 'public_id', 'mime', 'original_name', 'created_at'];

    protected $casts = ['created_at' => 'datetime'];
}
