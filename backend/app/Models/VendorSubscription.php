<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'plan_key', 'status', 'subscription_started_at', 'subscription_ends_at',
        'trial_started_at', 'trial_ends_at', 'current_period_started_at', 'current_period_ends_at',
        'next_billing_at', 'cancelled_at', 'expired_at', 'paymongo_checkout_session_id',
        'paymongo_payment_id', 'payment_status', 'paid_amount', 'paid_currency',
        'billing_period', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'subscription_started_at' => 'datetime', 'subscription_ends_at' => 'datetime',
            'trial_started_at' => 'datetime', 'trial_ends_at' => 'datetime',
            'current_period_started_at' => 'datetime', 'current_period_ends_at' => 'datetime',
            'next_billing_at' => 'datetime', 'cancelled_at' => 'datetime', 'expired_at' => 'datetime',
            'paid_at' => 'datetime', 'paid_amount' => 'decimal:2',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function isTrialActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_started_at !== null
            && $this->trial_ends_at !== null
            && $at->greaterThanOrEqualTo($this->trial_started_at)
            && $at->lessThan($this->trial_ends_at);
    }

    public function isExpired(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if ($this->status === SubscriptionStatus::Expired) {
            return true;
        }

        return $this->status === SubscriptionStatus::Trialing
            && $this->trial_ends_at !== null
            && $at->greaterThanOrEqualTo($this->trial_ends_at);
    }

    public function isActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        if ($this->isTrialActive($at)) {
            return true;
        }

        return $this->status === SubscriptionStatus::Active
            && ($this->subscription_ends_at === null || $at->lessThan($this->subscription_ends_at));
    }

    /** Persist expiration when a caller needs status normalization. */
    public function expireIfDue(?CarbonInterface $at = null): bool
    {
        if (! $this->isExpired($at) || $this->status === SubscriptionStatus::Expired) {
            return false;
        }

        $this->forceFill(['status' => SubscriptionStatus::Expired, 'expired_at' => $at ?? now()])->save();
        return true;
    }
}
