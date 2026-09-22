<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\VendorSubscription;
use App\Subscriptions\SubscriptionPlans;
use Illuminate\Contracts\Auth\Authenticatable;

/** Company-level subscription access, deliberately separate from employee RBAC. */
class SubscriptionAccessService
{
    public function canAccess(?Authenticatable $actor, string $module): bool
    {
        $vendor = $this->vendorFor($actor);

        // Subscriptions only govern vendor companies and their employees.
        if (! $vendor) {
            return true;
        }

        // Query rather than use a potentially stale loaded relationship so a
        // downgrade takes effect on the next request immediately.
        $subscription = $vendor->subscription()->first();

        return $subscription !== null
            && $subscription->isActive()
            && SubscriptionPlans::includesModule($subscription->plan_key, $module);
    }

    public function accessSummary(?Authenticatable $actor): array
    {
        $vendor = $this->vendorFor($actor);
        $subscription = $vendor?->subscription()->first();
        $active = $subscription?->isActive() ?? false;

        return [
            'subscription_active' => $active,
            'status' => $subscription?->isExpired() ? 'expired' : $subscription?->status?->value,
            'plan_key' => $subscription?->plan_key,
            'modules' => $active ? SubscriptionPlans::get($subscription->plan_key)['included_modules'] : [],
            'required_plans' => SubscriptionPlans::requiredPlansByModule(),
            'resource_limits' => $vendor ? app(ResourceLimitService::class)->summary($vendor) : [],
        ];
    }

    private function vendorFor(?Authenticatable $actor): ?User
    {
        if ($actor instanceof Employee) {
            return $actor->owner;
        }

        return $actor instanceof User && $actor->isVendor() ? $actor : null;
    }
}
