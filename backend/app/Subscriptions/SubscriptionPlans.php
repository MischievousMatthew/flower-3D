<?php

namespace App\Subscriptions;

/**
 * Single source of truth for vendor subscription catalog, features, and
 * enforceable resource limits. Route mappings and enforcement are deferred to
 * later stages; authentication and profile features are intentionally absent.
 */
final class SubscriptionPlans
{
    public const STARTER = 'starter';
    public const BUSINESS = 'business';
    public const PROFESSIONAL = 'professional';
    public const ENTERPRISE = 'enterprise';

    public const MODULES = [
        'products', 'reservations', 'calendar', 'finance', 'staff',
        'procurement', 'suppliers', 'warehouse', 'supply_chain', 'orders',
        'logistics', 'deliveries', 'scanning', 'crm', 'hr', 'employees',
        'attendance', 'payroll', 'leave',
    ];

    /** Existing employee-RBAC keys mapped to subscription module keys. */
    private const MODULE_ALIASES = [
        'hr_dashboard' => 'hr',
        'leave_management' => 'leave',
        'finance_dashboard' => 'finance',
        'funding_requests' => 'finance',
        'payroll_requests' => 'finance',
        'inventory_products' => 'products',
        'inventory_funding' => 'finance',
        'sc_dashboard' => 'supply_chain',
        'sc_orders' => 'orders',
        'order_scan' => 'scanning',
    ];

    /**
     * A null resource limit means it is custom/unlimited and must be resolved
     * by an Enterprise agreement before an enforcement stage uses it.
     */
    private const PLANS = [
        self::STARTER => [
            'key' => self::STARTER,
            'name' => 'Starter',
            'monthly_price' => 999.00,
            'currency' => 'PHP',
            'custom_pricing' => false,
            'included_modules' => ['products', 'reservations', 'calendar', 'orders', 'deliveries', 'scanning'],
            'resource_limits' => ['branches' => 1, 'warehouses' => 0, 'staff_employees' => 5],
            'free_trial_available' => false,
            'trial_duration_months' => 0,
        ],
        self::BUSINESS => [
            'key' => self::BUSINESS,
            'name' => 'Business',
            'monthly_price' => 2999.00,
            'currency' => 'PHP',
            'custom_pricing' => false,
            'included_modules' => ['products', 'reservations', 'calendar', 'finance', 'staff', 'procurement', 'suppliers', 'warehouse', 'supply_chain', 'orders', 'logistics', 'deliveries', 'scanning', 'crm'],
            'resource_limits' => ['branches' => 2, 'warehouses' => 1, 'staff_employees' => 15],
            'free_trial_available' => true,
            'trial_duration_months' => 1,
        ],
        self::PROFESSIONAL => [
            'key' => self::PROFESSIONAL,
            'name' => 'Professional',
            'monthly_price' => 6999.00,
            'currency' => 'PHP',
            'custom_pricing' => false,
            'included_modules' => self::MODULES,
            'resource_limits' => ['branches' => 3, 'warehouses' => 1, 'staff_employees' => 30],
            'free_trial_available' => false,
            'trial_duration_months' => 0,
        ],
        self::ENTERPRISE => [
            'key' => self::ENTERPRISE,
            'name' => 'Enterprise',
            'monthly_price' => null,
            'minimum_monthly_price' => 15000.00,
            'currency' => 'PHP',
            'custom_pricing' => true,
            'included_modules' => self::MODULES,
            'resource_limits' => ['branches' => null, 'warehouses' => null, 'staff_employees' => null],
            'free_trial_available' => false,
            'trial_duration_months' => 0,
        ],
    ];

    public static function all(): array
    {
        return self::PLANS;
    }

    public static function get(string $key): array
    {
        if (! isset(self::PLANS[$key])) {
            throw new \InvalidArgumentException("Unknown subscription plan [{$key}].");
        }

        return self::PLANS[$key];
    }

    public static function exists(string $key): bool
    {
        return isset(self::PLANS[$key]);
    }

    public static function includesModule(string $plan, string $module): bool
    {
        return in_array(self::canonicalModule($module), self::get($plan)['included_modules'], true);
    }

    public static function resourceLimit(string $plan, string $resource): ?int
    {
        return self::get($plan)['resource_limits'][$resource] ?? null;
    }

    public static function canonicalModule(string $module): string
    {
        return self::MODULE_ALIASES[$module] ?? $module;
    }

    public static function requiredPlanForModule(string $module): ?array
    {
        $module = self::canonicalModule($module);
        $keys = array_keys(self::PLANS);

        foreach ($keys as $index => $key) {
            if (in_array($module, self::PLANS[$key]['included_modules'], true)) {
                return [
                    'key' => $key,
                    'name' => self::PLANS[$key]['name'],
                    'or_higher' => $index < count($keys) - 1,
                ];
            }
        }

        return null;
    }

    public static function requiredPlansByModule(): array
    {
        $requirements = [];
        foreach (self::MODULES as $module) {
            $requirements[$module] = self::requiredPlanForModule($module);
        }

        return $requirements;
    }
}
