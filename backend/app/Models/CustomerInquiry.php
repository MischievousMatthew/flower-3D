<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A customer's question to a shop about a product or one of their orders. */
class CustomerInquiry extends Model
{
    protected $fillable = [
        'vendor_id', 'customer_id', 'product_id', 'order_id', 'question',
        'answer', 'answered_by', 'answered_at', 'status', 'is_public', 'customer_seen_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
        'customer_seen_at' => 'datetime',
        'is_public' => 'boolean',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class)->withoutGlobalScopes();
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class)->withoutGlobalScopes();
    }
}
