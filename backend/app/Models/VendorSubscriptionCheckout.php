<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A pending/paid PayMongo attempt; it never replaces the current plan by itself. */
class VendorSubscriptionCheckout extends Model
{
    protected $fillable = [
        'vendor_id', 'plan_key', 'status', 'amount', 'currency', 'billing_period',
        'reference_number', 'payment_attempt', 'paymongo_checkout_session_id',
        'paymongo_payment_id', 'checkout_url', 'paid_at', 'cancelled_at', 'metadata',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime', 'cancelled_at' => 'datetime', 'metadata' => 'array'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
}
