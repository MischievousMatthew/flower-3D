<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Helpers\CloudinaryHelper;

/**
 * A return/refund case the customer and the vendor settle together.
 *
 * type:    "refund" (customer keeps the item) | "return" (item goes back first)
 * status:  pending (still agreeing) → approved (agreed) → completed,
 *          or rejected (by the vendor) / withdrawn (by the customer)
 */
class OrderRequest extends Model
{
    use BelongsToOwner;

    public const FULL_REFUND = 'full_refund';
    public const PARTIAL_REFUND = 'partial_refund';
    public const REPLACEMENT = 'replacement';
    public const RESOLUTIONS = [self::FULL_REFUND, self::PARTIAL_REFUND, self::REPLACEMENT];

    public const OPEN_STATUSES = ['pending', 'approved'];
    public const CLOSED_STATUSES = ['completed', 'rejected', 'withdrawn'];

    protected $fillable = [
        'owner_id',
        'order_id',
        'user_id',
        'type',
        'reason',
        'reason_category',
        'resolution_requested',
        'amount_requested',
        'media_path',
        'media_type',
        'status',
        'admin_notes',
        'offer_resolution',
        'offer_amount',
        'offer_by',
        'offered_at',
        'settled_resolution',
        'settled_amount',
        'settled_at',
        'item_received_at',
        'refund_method',
        'refund_reference',
        'refund_sent_at',
        'replacement_note',
        'replacement_sent_at',
        'completed_at',
        'closed_reason',
        'last_activity_at',
    ];

    protected $casts = [
        'amount_requested' => 'float',
        'offer_amount' => 'float',
        'settled_amount' => 'float',
        'offered_at' => 'datetime',
        'settled_at' => 'datetime',
        'item_received_at' => 'datetime',
        'refund_sent_at' => 'datetime',
        'replacement_sent_at' => 'datetime',
        'completed_at' => 'datetime',
        'last_activity_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(OrderRequestMessage::class)->orderBy('created_at')->orderBy('id');
    }

    public function getMediaUrlAttribute(): ?string
    {
        return $this->media_path
            ? CloudinaryHelper::getUrl($this->media_path, $this->media_type === 'image' ? 'image' : 'raw')
            : null;
    }

    public function isPending(): bool  { return $this->status === 'pending';  }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
    public function isOpen(): bool     { return in_array($this->status, self::OPEN_STATUSES, true); }

    public static function isRefund(?string $resolution): bool
    {
        return in_array($resolution, [self::FULL_REFUND, self::PARTIAL_REFUND], true);
    }

    /**
     * Where the case is now, and who has to act:
     * awaiting_vendor, awaiting_customer, awaiting_return, awaiting_refund,
     * confirm_refund, awaiting_replacement, confirm_replacement,
     * completed, rejected, withdrawn
     */
    public function stage(): string
    {
        return match ($this->status) {
            'pending' => $this->offer_by === 'vendor' ? 'awaiting_customer' : 'awaiting_vendor',
            'approved' => match (true) {
                $this->type === 'return' && !$this->item_received_at => 'awaiting_return',
                self::isRefund($this->settled_resolution) => $this->refund_sent_at ? 'confirm_refund' : 'awaiting_refund',
                default => $this->replacement_sent_at ? 'confirm_replacement' : 'awaiting_replacement',
            },
            default => $this->status,
        };
    }

    /** "customer", "vendor" or null when the case is closed */
    public function nextActor(): ?string
    {
        return match ($this->stage()) {
            'awaiting_customer', 'confirm_refund', 'confirm_replacement' => 'customer',
            'awaiting_vendor', 'awaiting_return', 'awaiting_refund', 'awaiting_replacement' => 'vendor',
            default => null,
        };
    }
}
