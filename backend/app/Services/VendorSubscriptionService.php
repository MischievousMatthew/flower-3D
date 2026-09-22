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
