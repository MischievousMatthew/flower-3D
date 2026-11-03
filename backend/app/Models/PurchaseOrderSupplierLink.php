<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * The private link a supplier uses to answer a purchase order. Only its hash
 * is kept. It works until it expires or a newer email replaces it.
 */
class PurchaseOrderSupplierLink extends Model
{
    public const VALID_DAYS = 14;

    public $timestamps = false;

    protected $fillable = ['owner_id', 'purchase_order_id', 'token_hash', 'expires_at', 'revoked_at', 'last_opened_at', 'created_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_opened_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    /** A new link for the order; older ones stop working. Returns [link, plain token]. */
    public static function issue(PurchaseOrder $order): array
    {
        static::where('purchase_order_id', $order->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);

        $token = Str::random(48);
        $link = static::create([
            'owner_id' => $order->owner_id,
            'purchase_order_id' => $order->id,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now()->addDays(self::VALID_DAYS),
            'created_at' => now(),
        ]);

        return [$link, $token];
    }

    /** The link for a token, whatever its state (null if it never existed) */
    public static function forToken(string $token): ?self
    {
        return strlen($token) < 20 ? null : static::with('order.items', 'order.supplier')->where('token_hash', hash('sha256', $token))->first();
    }

    public function isUsable(): bool
    {
        return ! $this->revoked_at && $this->expires_at->isFuture();
    }

    /** Stops every link for an order (e.g. it was declined) */
    public static function revokeFor(PurchaseOrder $order): void
    {
        static::where('purchase_order_id', $order->id)->whereNull('revoked_at')->update(['revoked_at' => now()]);
    }
}
