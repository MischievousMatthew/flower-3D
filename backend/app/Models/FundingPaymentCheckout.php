<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One attempt at paying a supplier through PayMongo Checkout */
class FundingPaymentCheckout extends Model
{
    protected $table = 'funding_payment_checkouts';

    protected $fillable = [
        'owner_id',
        'funding_request_id',
        'purchase_order_id',
        'status',
        'amount',
        'payment_channel',
        'reference_number',
        'paymongo_checkout_session_id',
        'paymongo_payment_id',
        'checkout_url',
        'paid_by_name',
        'funding_request_payment_id',
        'paid_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function fundingRequest(): BelongsTo
    {
        return $this->belongsTo(FundingRequest::class);
    }
}
