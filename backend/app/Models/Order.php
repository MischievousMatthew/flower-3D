<?php

namespace App\Models;

use App\Services\VendorFinanceService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'user_id',
        'vendor_id',
        'status',
        'payment_status',
        'payment_method',
        'subtotal',
        'delivery_fee',
        'tax_amount',
        'discount_amount',
        'total_amount',
        'delivery_type',
        'delivery_address',
        'delivery_contact_name',
        'delivery_contact_number',
        'paymongo_payment_intent_id',
        'paymongo_source_id',
        'paymongo_checkout_url',
        'paymongo_response',
        'store_name',
        'store_address',
        'customer_notes',
        'paid_at',
        'stock_deducted_at',
        'stock_restored_at',
        'delivered_at',
        'cancelled_at',
        'reservation_date',
        'model_3d_reference',
    ];

    protected $casts = [
        'subtotal'          => 'decimal:2',
        'delivery_fee'      => 'decimal:2',
        'tax_amount'        => 'decimal:2',
        'discount_amount'   => 'decimal:2',
        'total_amount'      => 'decimal:2',
        'paymongo_response' => 'array',
        'paid_at'           => 'datetime',
        'stock_deducted_at' => 'datetime',
        'stock_restored_at' => 'datetime',
        'delivered_at'      => 'datetime',
        'cancelled_at'      => 'datetime',
        'reservation_date'  => 'date',
    ];

    const DELIVERY_STATUSES = [
        'pending',
        'processing',
        'out_for_delivery',
        'completed',
        'returned',
        'refunded',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($order) {
            if (! $order->order_number) {
                $order->order_number = 'ORD-' . strtoupper(Str::random(3)) . '-' .
                    now()->format('Ymd') . '-' .
                    str_pad(static::count() + 1, 4, '0', STR_PAD_LEFT);
            }
        });
    }

    // ─── Relationships ─────────────────────────────────────────────────────────

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function delivery(): HasOne
    {
        return $this->hasOne(Delivery::class);
    }

    public function orderRequests(): HasMany
    {
        return $this->hasMany(\App\Models\OrderRequest::class);
    }

    public function adminNotes(): HasMany
    {
        return $this->hasMany(OrderAdminNote::class)->latest();
    }

    // ─── Scopes ────────────────────────────────────────────────────────────────

    public function scopePending($query)    { return $query->where('status', 'pending'); }
    public function scopeProcessing($query) { return $query->where('status', 'processing'); }
    public function scopeCompleted($query)  { return $query->where('status', 'completed'); }
    public function scopePaid($query)       { return $query->where('payment_status', 'paid'); }
    public function scopeUnpaid($query)     { return $query->where('payment_status', 'unpaid'); }

    // ─── Helper Methods ────────────────────────────────────────────────────────

    public function isPaid(): bool      { return $this->payment_status === 'paid'; }
    public function isCompleted(): bool { return $this->status === 'completed'; }
    public function isCancelled(): bool { return $this->status === 'cancelled'; }

    public function markAsPaid(): void
    {
        $this->update(['payment_status' => 'paid', 'paid_at' => now()]);
    }

    public function markAsProcessing(): void
    {
        $this->update(['status' => 'processing']);

        $fresh = $this->fresh(['items']);
        app(VendorFinanceService::class)->handleOrderVendorReceipt($fresh);
    }

    public function markAsOutForDelivery(): void
    {
        $this->update(['status' => 'out_for_delivery']);
    }

    /**
     * Mark order as completed and trigger finance + stock logic.
     *
     * This is the single source of truth for order completion.
     * VendorFinanceService now ensures stock was already deducted when the
     * vendor received the order, and backfills it here only if that earlier
     * transition was skipped.
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed', 'delivered_at' => now()]);

        // Reload fresh instance so the service sees the updated status
        $fresh = $this->fresh(['items']);

        app(VendorFinanceService::class)->handleOrderCompletion($fresh);
    }

    /**
     * Mark order as refunded and trigger vendor balance deduction.
     */
    public function markAsRefunded(): void
    {
        $this->update([
            'status'         => 'refunded',
            'payment_status' => 'refunded',
        ]);

        app(VendorFinanceService::class)->handleOrderRefund($this->fresh());
    }

    public function cancel(): void
    {
        app(VendorFinanceService::class)->cancelPendingOrder($this);
    }

    /** PayMongo's failed_code → what the customer reads */
    private const PAYMENT_FAILURE_REASONS = [
        'insufficient_funds' => "Your e-wallet or card didn't have enough balance for this order.",
        'card_expired' => 'The card you used has expired.',
        'cvc_invalid' => "The card's security code (CVC) didn't match.",
        'cvc_incorrect' => "The card's security code (CVC) didn't match.",
        'lost_card' => 'Your bank blocked this card.',
        'stolen_card' => 'Your bank blocked this card.',
        'blocked' => 'Your bank or e-wallet blocked this payment.',
        'fraudulent' => 'Your bank or e-wallet blocked this payment.',
        'processor_blocked' => 'Your bank or e-wallet blocked this payment.',
        'generic_decline' => 'Your bank or e-wallet declined the payment.',
        'processor_declined' => 'Your bank or e-wallet declined the payment.',
        'authentication_failed' => "The payment wasn't confirmed in your e-wallet or bank app.",
        'processor_unavailable' => "Your bank or e-wallet couldn't be reached at that moment.",
        'processing_error' => "Your bank or e-wallet couldn't be reached at that moment.",
        'payment_expired' => 'The payment page timed out before the payment was finished.',
    ];

    /**
     * Why an unpaid online order didn't go through, in plain words, from what
     * checkout and PayMongo left on the order. Null when the payment hasn't failed.
     */
    public function paymentFailure(): ?array
    {
        if ($this->payment_status !== 'failed') {
            return null;
        }

        $response = is_array($this->paymongo_response) ? $this->paymongo_response : [];
        $code = $response['failed_code'] ?? null;

        if ($code || ! empty($response['failed_message'])) {
            return [
                'reason' => 'declined',
                'title' => 'Your payment was declined',
                'message' => self::PAYMENT_FAILURE_REASONS[$code] ?? 'Your bank or e-wallet declined the payment.',
                'next' => 'Nothing was charged. Check your balance or use another e-wallet, then place the order again.',
                'code' => $code,
            ];
        }

        if (! empty($response['cancelled_at'])) {
            return [
                'reason' => 'cancelled',
                'title' => 'Payment was cancelled',
                'message' => 'You left the PayMongo payment page before the payment was finished.',
                'next' => 'Nothing was charged. Place the order again when you are ready to pay.',
                'code' => null,
            ];
        }

        if (! empty($response['error'])) {
            return [
                'reason' => 'not_started',
                'title' => "We couldn't open the payment page",
                'message' => "PayMongo didn't respond when we tried to start your payment.",
                'next' => 'Nothing was charged. Please try again in a few minutes, or choose Cash on Delivery if the shop offers it.',
                'code' => null,
            ];
        }

        return [
            'reason' => 'unconfirmed',
            'title' => "Payment didn't go through",
            'message' => "PayMongo didn't confirm this payment.",
            'next' => 'Nothing was charged. Please place the order again, or message the shop if money left your account.',
            'code' => null,
        ];
    }
}
