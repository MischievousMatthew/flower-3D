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
        // Complaints and service issues about an order; these go to the shop that sold it
        'complaint' => 'A problem with my order',
        'quality' => 'Flower quality',
        'delivery' => 'Late or missed delivery',
        'service' => 'Service from the shop',
    ];

    /** Categories a customer files about a shop's order: the ticket goes to that shop */
    public const SHOP_CATEGORIES = ['complaint', 'quality', 'delivery', 'service'];
    public const STATUSES = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'reference', 'requester_id', 'requester_role', 'subject', 'category', 'priority',
        'status', 'order_id', 'last_activity_at', 'resolved_at',
        'vendor_id', 'assigned_employee_id', 'order_request_id', 'recorded_by_shop',
    ];

    protected $casts = ['last_activity_at' => 'datetime', 'resolved_at' => 'datetime', 'recorded_by_shop' => 'boolean'];

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

    /** The shop handling it, for complaints about an order (null: BloomCraft support) */
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    /** A refund or return case it's about */
    public function orderRequest(): BelongsTo
    {
        return $this->belongsTo(OrderRequest::class, 'order_request_id')->withoutGlobalScopes();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class, 'ticket_id')->orderBy('created_at')->orderBy('id');
    }
}
