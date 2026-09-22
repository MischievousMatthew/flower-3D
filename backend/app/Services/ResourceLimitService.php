<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\Warehouse;
use App\Subscriptions\SubscriptionPlans;
use LogicException;

/**
 * The single enforcement point for vendor-company resource capacity.
 * Branches are intentionally represented but have no usage resolver until a
 * real branch model/creation flow exists.
 */
class ResourceLimitService
{
    private const RESOURCES = [
        SubscriptionPlans::RESOURCE_BRANCHES => ['label' => 'Branch', 'plural' => 'branches', 'model' => null],
        SubscriptionPlans::RESOURCE_WAREHOUSES => ['label' => 'Warehouse', 'plural' => 'warehouses', 'model' => Warehouse::class],
        SubscriptionPlans::RESOURCE_STAFF_EMPLOYEES => ['label' => 'Staff member', 'plural' => 'staff members', 'model' => Employee::class],
    ];

    public function getResourceLimit(User $vendor, string $resource): ?int
    {
        return SubscriptionPlans::resourceLimit($this->activePlan($vendor), $resource);
    }

    public function canCreateResource(User $vendor, string $resource): bool
    {
        $limit = $this->getResourceLimit($vendor, $resource);

        return $limit === null || $this->currentUsage($vendor, $resource) < $limit;
    }

    public function assertCanCreateResource(User $vendor, string $resource): void
    {
        if (! $this->canCreateResource($vendor, $resource)) {
            throw new LogicException($this->limitMessage($vendor, $resource));
        }
    }

    public function summary(User $vendor): array
    {
        return collect(array_keys(self::RESOURCES))->mapWithKeys(function (string $resource) use ($vendor) {
            $limit = $this->getResourceLimit($vendor, $resource);
            return [$resource => [
                'label' => self::RESOURCES[$resource]['label'],
                'usage' => $this->currentUsage($vendor, $resource),
                'limit' => $limit,
                'unlimited' => $limit === null,
                'can_create' => $this->canCreateResource($vendor, $resource),
                'message' => $limit === null ? null : $this->limitMessage($vendor, $resource),
            ]];
        })->all();
    }

    public function limitMessage(User $vendor, string $resource): string
    {
        $plan = SubscriptionPlans::get($this->activePlan($vendor));
        $limit = $this->getResourceLimit($vendor, $resource);
        $label = strtolower(self::RESOURCES[$resource]['label'] ?? 'Resource');
        $plural = self::RESOURCES[$resource]['plural'] ?? "{$label}s";
        $nextPlan = SubscriptionPlans::nextPlanWithMoreOf($plan['key'], $resource);
        $quantity = $limit === 1 ? "1 {$label}" : "{$limit} {$plural}";

        $upgrade = $nextPlan
            ? " Upgrade to {$nextPlan['name']} to add additional {$plural}."
            : '';

        return "Your {$plan['name']} plan includes {$quantity}.{$upgrade}";
    }

    private function activePlan(User $vendor): string
    {
        $subscription = $vendor->subscription()->first();
        if (! $subscription || ! $subscription->isActive()) {
            // Subscription-module middleware remains responsible for access;
            // this safe default prevents capacity creation without a plan.
            return SubscriptionPlans::STARTER;
        }

        return $subscription->plan_key;
    }

    private function currentUsage(User $vendor, string $resource): int
    {
        $model = self::RESOURCES[$resource]['model'] ?? null;
        if (! $model) {
            return 0;
        }

        return $model::query()->withoutGlobalScope('owner')->where('owner_id', $vendor->id)->count();
    }
}
