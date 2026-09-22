<?php

namespace App\Enums;

/**
 * The lifecycle states used by vendor subscriptions.
 *
 * This is deliberately separate from payment status: a payment can be
 * pending while a subscription is active through the end of its period.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case Pending = 'pending';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
    case PastDue = 'past_due';
}
