<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A help request from a vendor or customer, answered by admins */
class SupportTicket extends Model
{
    public const CATEGORIES = [
        'account' => 'Account & sign-in',
        'payments' => 'Payments & payouts',
        'orders' => 'Orders & delivery',
        'products' => 'Products & listings',
        'subscription' => 'Subscription & billing',
        'technical' => 'Something isn\'t working',
        'other' => 'Something else',
    ];
    public const STATUSES = ['open', 'waiting', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high'];

    protected $fillable = [
        'reference', 'requester_id', 'requester_role', 'subject', 'category', 'priority',
        'status', 'order_id', 'last_activity_at', 'resolved_at',
    ];

    protected $casts = ['last_activity_at' => 'datetime', 'resolved_at' => 'datetime'];

    protected static function booted(): void
    {
        static::creating(function (SupportTicket $ticket) {
            $ticket->reference ??= 'SUP-' . strtoupper(Str::random(6));
            $ticket->last_activity_at ??= now();
        });
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->orderBy('created_at')->orderBy('id');
    }
}
