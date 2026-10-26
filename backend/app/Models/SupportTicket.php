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

    /** Only vendors run listings and plans, so customers don't see these topics */
    public const VENDOR_CATEGORIES = ['products', 'subscription'];

    /** One line under each topic, so people know which to pick */
    public const HINTS = [
        'complaint' => 'Wrong or missing items, card message, changes',
        'quality' => 'Wilted, damaged or the wrong flowers',
        'delivery' => 'Late, missed or sent to the wrong place',
        'service' => 'How the shop treated you',
        'orders' => 'Placing, tracking or cancelling an order',
        'payments' => 'Charges, refunds and payouts',
        'account' => 'Signing in, your details, security',
        'products' => 'Listings, stock and photo-to-3D',
        'subscription' => 'Your plan and its charges',
        'technical' => 'Errors, pages not loading, the 3D builder',
        'other' => 'Questions, feedback or a report',
    ];

    /**
     * The exact problem under each topic. A label, or [label, role] for an
     * issue only customers or only vendors have.
     */
    public const ISSUES = [
        'complaint' => [
            'wrong_item' => 'I got the wrong item',
            'missing_item' => 'Something is missing from my order',
            'not_as_pictured' => "It doesn't look like the photo",
            'broken_packaging' => 'The wrapping or vase arrived broken',
            'card_message' => 'The card message is wrong or missing',
            'change_order' => 'I need to change or cancel my order',
            'other' => 'Something else about my order',
        ],
        'quality' => [
            'wilted' => 'Flowers arrived wilted or drooping',
            'damaged' => 'Flowers are bruised or broken',
            'short_life' => "They didn't last as long as they should",
            'wrong_flowers' => 'Wrong colours or types of flowers',
            'fewer_stems' => 'Fewer stems than I paid for',
            'mould_pests' => 'Mould, a bad smell or insects',
            'other' => 'Another quality problem',
        ],
        'delivery' => [
            'late' => 'It arrived late',
            'not_arrived' => 'It never arrived',
            'marked_delivered' => "It says delivered, but I didn't get it",
            'wrong_address' => 'It went to the wrong address',
            'damaged_on_way' => 'It was damaged on the way',
            'rider' => 'A problem with the rider',
            'other' => 'Another delivery problem',
        ],
        'service' => [
            'no_reply' => "The shop isn't replying",
            'rude' => 'Rude or unhelpful staff',
            'cancelled' => 'The shop cancelled without telling me',
            'refund_refused' => "The shop won't refund or replace it",
            'broken_promise' => "They didn't do what they promised",
            'other' => 'Something else about the shop',
        ],
        'orders' => [
            'cant_order' => ["I couldn't place my order", 'customer'],
            'tracking' => 'The order status or tracking looks wrong',
            'cancel' => 'Cancelling an order',
            'delivery_area' => 'Delivery fees or delivery areas',
            'customer_issue' => ['A problem with a customer', 'vendor'],
            'other' => 'Something else about orders',
        ],
        'payments' => [
            'charged_twice' => 'I was charged twice',
            'payment_failed' => 'My payment failed',
            'refund_missing' => ["I haven't received my refund", 'customer'],
            'payout_late' => ['My payout is late or missing', 'vendor'],
            'wrong_amount' => 'The amount is wrong',
            'other' => 'Something else about payments',
        ],
        'account' => [
            'cant_sign_in' => "I can't sign in",
            'password_reset' => "Password reset isn't working",
            'change_details' => 'Change my email, phone or name',
            'not_me' => 'Someone else used my account',
            'close_account' => 'Close my account',
            'other' => 'Something else about my account',
        ],
        'products' => [
            'taken_down' => 'A listing was rejected or taken down',
            'cant_edit' => "I can't add or edit a product",
            'photo_3d' => "Photo-to-3D isn't working",
            'stock' => 'Stock numbers are wrong',
            'other' => 'Something else about products',
        ],
        'subscription' => [
            'change_plan' => 'Upgrading or changing my plan',
            'charges' => 'A charge I don\'t recognise',
            'cancel_plan' => 'Cancelling my plan',
            'missing_feature' => "A feature I paid for isn't available",
            'other' => 'Something else about my plan',
        ],
        'technical' => [
            'wont_load' => "A page won't load",
            'error' => 'I see an error message',
            'builder' => "The 3D bouquet builder isn't working",
            'slow' => "It's very slow",
            'no_emails' => "I'm not getting emails or notifications",
            'other' => 'Something else is broken',
        ],
        'other' => [
            'question' => 'A general question',
            'feedback' => 'Feedback or an idea',
            'report' => 'Report a shop, listing or review',
            'other' => 'Something else',
        ],
    ];

    /** What a customer would like the shop to do about a complaint */
    public const OUTCOMES = [
        'refund' => 'A refund',
        'partial_refund' => 'A partial refund',
        'replacement' => 'A replacement',
        'redeliver' => 'Deliver it again',
        'just_letting_know' => 'Nothing, just letting the shop know',
    ];

    /** Issues serious enough to go to the top of the queue */
    public const HIGH_PRIORITY_ISSUES = ['not_arrived', 'marked_delivered', 'charged_twice', 'not_me', 'mould_pests'];

    /** The issues under a topic that this role can pick: [key => label] */
    public static function issuesFor(string $category, string $role): array
    {
        $issues = [];
        foreach (self::ISSUES[$category] ?? [] as $key => $entry) {
            [$label, $only] = is_array($entry) ? $entry : [$entry, null];
            if ($only === null || $only === $role) {
                $issues[$key] = $label;
            }
        }

        return $issues;
    }

    public static function issueLabel(?string $category, ?string $issue): ?string
    {
        $entry = self::ISSUES[$category][$issue] ?? null;

        return is_array($entry) ? $entry[0] : $entry;
    }
    public const STATUSES = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];
    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    protected $fillable = [
        'reference', 'requester_id', 'requester_role', 'subject', 'category', 'issue', 'desired_outcome', 'priority',
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
