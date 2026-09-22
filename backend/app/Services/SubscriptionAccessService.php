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

        $subscription = $vendor->subscription;

        return $subscription !== null
            && $subscription->isActive()
            && SubscriptionPlans::includesModule($subscription->plan_key, $module);
    }

    public function accessSummary(?Authenticatable $actor): array
    {
        $vendor = $this->vendorFor($actor);
        $subscription = $vendor?->subscription;
        $active = $subscription?->isActive() ?? false;

        return [
            'subscription_active' => $active,
            'status' => $subscription?->isExpired() ? 'expired' : $subscription?->status?->value,
            'plan_key' => $subscription?->plan_key,
            'modules' => $active ? SubscriptionPlans::get($subscription->plan_key)['included_modules'] : [],
            'required_plans' => SubscriptionPlans::requiredPlansByModule(),
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
