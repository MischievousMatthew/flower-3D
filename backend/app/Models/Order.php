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
    }

    public function markAsOutForDelivery(): void
    {
        $this->update(['status' => 'out_for_delivery']);
    }

    /**
     * Mark order as completed and trigger finance + stock logic.
     *
     * This is the single source of truth for order completion.
     * VendorFinanceService handles:
     *   - Deducting product stock (quantity_in_stock)
     *   - Crediting vendor balance
     *   - Writing the finance ledger entry (vendor_transactions)
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
        $this->update(['status' => 'cancelled', 'cancelled_at' => now()]);
    }
}