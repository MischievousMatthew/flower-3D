<?php

namespace App\Services;

use App\Enums\SubscriptionStatus;
use App\Models\User;
use App\Models\VendorSubscription;
use App\Models\VendorSubscriptionTrial;
use App\Subscriptions\SubscriptionPlans;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

class VendorSubscriptionService
{
    /** Start the single, calendar-month Business trial for an approved vendor. */
    /**
     * An admin puts a vendor on any plan (or none), starting now. Months null
     * means no end date (e.g. an Enterprise contract). No payment is taken.
     */
    public function setPlanByAdmin(User $vendor, ?string $planKey, ?int $months): ?VendorSubscription
    {
        return DB::transaction(function () use ($vendor, $planKey, $months) {
            $subscription = VendorSubscription::query()->where('vendor_id', $vendor->id)->lockForUpdate()->first();
            $now = now();

            if ($planKey === null) {
                $subscription?->forceFill([
                    'status' => SubscriptionStatus::Cancelled,
                    'cancelled_at' => $now,
                    'subscription_ends_at' => $now,
                    'current_period_ends_at' => $now,
                    'trial_ends_at' => $subscription->status === SubscriptionStatus::Trialing ? $now : $subscription->trial_ends_at,
                    'next_billing_at' => null,
                ])->save();

                return $subscription;
            }

            $end = $months ? $now->copy()->addMonthsNoOverflow($months) : null;
            $subscription ??= new VendorSubscription(['vendor_id' => $vendor->id]);
            $subscription->fill([
                'plan_key' => $planKey,
                'status' => SubscriptionStatus::Active,
                'subscription_started_at' => $now,
                'subscription_ends_at' => $end,
                'trial_started_at' => null,
                'trial_ends_at' => null,
                'current_period_started_at' => $now,
                'current_period_ends_at' => $end,
                'next_billing_at' => $end,
                'cancelled_at' => null,
                'expired_at' => null,
                'payment_status' => 'set_by_admin',
                'billing_period' => $months ? "{$months}_months" : 'no_end_date',
            ])->save();

            return $subscription;
        });
    }

    public function startBusinessTrial(User $vendor, ?CarbonInterface $startedAt = null): VendorSubscription
    {
        if (! $vendor->isVendor()) {
            throw new LogicException('Only vendor owners can start a vendor subscription trial.');
        }

        $plan = SubscriptionPlans::get(SubscriptionPlans::BUSINESS);
        $start = ($startedAt ?? now())->copy();
        $end = $start->copy()->addMonthsNoOverflow($plan['trial_duration_months']);

        return DB::transaction(function () use ($vendor, $start, $end) {
            $vendor = User::query()->lockForUpdate()->findOrFail($vendor->id);

            if (VendorSubscriptionTrial::query()
                ->where('vendor_id', $vendor->id)
                ->where('plan_key', SubscriptionPlans::BUSINESS)
                ->exists()) {
                throw new LogicException('This vendor has already used the Business free trial.');
            }

            $subscription = VendorSubscription::query()
                ->where('vendor_id', $vendor->id)
                ->lockForUpdate()
                ->first();

            if ($subscription?->isActive($start)) {
                throw new LogicException('This vendor already has an active subscription.');
            }

            $subscription ??= new VendorSubscription(['vendor_id' => $vendor->id]);
            $subscription->fill([
                'plan_key' => SubscriptionPlans::BUSINESS,
                'status' => SubscriptionStatus::Trialing,
                'subscription_started_at' => $start,
                'subscription_ends_at' => $end,
                'trial_started_at' => $start,
                'trial_ends_at' => $end,
                'current_period_started_at' => $start,
                'current_period_ends_at' => $end,
                'next_billing_at' => $end,
                'cancelled_at' => null,
                'expired_at' => null,
            ])->save();

            VendorSubscriptionTrial::create([
                'vendor_id' => $vendor->id,
                'vendor_subscription_id' => $subscription->id,
                'plan_key' => SubscriptionPlans::BUSINESS,
                'started_at' => $start,
                'ends_at' => $end,
            ]);

            return $subscription;
        });
    }

    public function hasConsumedTrial(User $vendor, string $planKey): bool
    {
        return VendorSubscriptionTrial::query()
            ->where('vendor_id', $vendor->id)
            ->where('plan_key', $planKey)
            ->exists();
    }
}
