<?php

namespace App\Subscriptions;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Single source of truth for the vendor subscription plans: Basic, Pro and
 * Premium, with their modules and resource limits.
 *
 * The plans below are the defaults. Admins change a plan's name, price,
 * description, highlights and limits on the admin "Plans & pricing" page;
 * those changes live in subscription_plan_settings and are laid over the
 * defaults here, so every screen and every check reads the same plan.
 * The keys (starter, business, professional) never change: subscriptions
 * and checkouts point at them.
 */
final class SubscriptionPlans
{
    public const STARTER = 'starter';
    public const BUSINESS = 'business';
    public const PROFESSIONAL = 'professional';

    public const RESOURCE_WAREHOUSES = 'warehouses';
    public const RESOURCE_STAFF_EMPLOYEES = 'staff_employees';
    public const RESOURCE_PRODUCTS = 'products';

    /** Limits an admin can change on a plan */
    public const EDITABLE_LIMITS = [self::RESOURCE_PRODUCTS, self::RESOURCE_STAFF_EMPLOYEES, self::RESOURCE_WAREHOUSES];

    public const MODULES = [
        'products', 'reservations', 'calendar', 'finance', 'staff',
        'procurement', 'suppliers', 'warehouse', 'supply_chain', 'orders',
        'logistics', 'deliveries', 'scanning', 'crm', 'hr', 'employees',
        'attendance', 'payroll', 'leave', 'photo_to_3d',
    ];

    /** Existing employee-RBAC keys mapped to subscription module keys. */
    private const MODULE_ALIASES = [
        'hr_dashboard' => 'hr',
        'leave_management' => 'leave',
        'finance_dashboard' => 'finance',
        'sales_revenue' => 'finance',
        'payments' => 'finance',
        'expenses' => 'finance',
        'profit_loss' => 'finance',
        'refunds' => 'finance',
        'financial_reports' => 'finance',
        'subscription_billing' => 'finance',
        'crm_dashboard' => 'crm',
        'crm_customers' => 'crm',
        'crm_cases' => 'crm',
        'crm_segments' => 'crm',
        'crm_reviews' => 'crm',
        'funding_requests' => 'finance',
        'payroll_requests' => 'finance',
        'inventory_products' => 'products',
        'inventory_funding' => 'finance',
        'sc_dashboard' => 'supply_chain',
        'sc_orders' => 'orders',
        'order_scan' => 'scanning',
        // The ERP shift planner is the same feature as the vendor's Staff → Schedules
        'schedules' => 'staff',
    ];

    /** A null resource limit means unlimited. */
    private const DEFAULTS = [
        self::STARTER => [
            'key' => self::STARTER,
            'name' => 'Basic',
            'monthly_price' => 999.00,
            'currency' => 'PHP',
            'description' => 'Essential ecommerce and day-to-day business tools for getting started.',
            'highlights' => ['Ecommerce functionality', 'Products, reservations, calendar, and chat', 'Basic business management'],
            'featured' => false,
            'included_modules' => ['products', 'reservations', 'calendar', 'orders', 'deliveries', 'scanning'],
            'resource_limits' => ['warehouses' => 0, 'staff_employees' => 5, 'products' => 50],
            'free_trial_available' => false,
            'trial_duration_months' => 0,
        ],
        self::BUSINESS => [
            'key' => self::BUSINESS,
            'name' => 'Pro',
            'monthly_price' => 2999.00,
            'currency' => 'PHP',
            'description' => 'Everything in Basic with the foundational ERP tools for a connected business.',
            'highlights' => ['Everything in Basic', 'Basic ERP functionality', 'Finance, procurement, and supplier workflows'],
            'featured' => false,
            'included_modules' => ['products', 'reservations', 'calendar', 'finance', 'staff', 'procurement', 'suppliers', 'warehouse', 'supply_chain', 'orders', 'logistics', 'deliveries', 'scanning', 'crm', 'photo_to_3d'],
            'resource_limits' => ['warehouses' => 1, 'staff_employees' => 15, 'products' => 300],
            'free_trial_available' => true,
            'trial_duration_months' => 1,
        ],
        self::PROFESSIONAL => [
            'key' => self::PROFESSIONAL,
            'name' => 'Premium',
            'monthly_price' => 4999.00,
            'currency' => 'PHP',
            'description' => 'The complete BloomCraft ERP for teams running end-to-end operations.',
            'highlights' => ['Everything in Pro', 'Full ERP functionality', 'Warehouse, supply chain, CRM, and HR'],
            'featured' => true,
            'included_modules' => self::MODULES,
            'resource_limits' => ['warehouses' => 1, 'staff_employees' => 30, 'products' => 1000],
            'free_trial_available' => false,
            'trial_duration_months' => 0,
        ],
    ];

    /** The plans with admin changes laid over, read once per request */
    private static ?array $plans = null;

    /** The defaults, before any admin change (the admin page shows them) */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    public static function all(): array
    {
        return self::$plans ??= self::withSettings();
    }

    /** Forget the plans read for this request, after an admin saves a change */
    public static function flush(): void
    {
        self::$plans = null;
    }

    private static function withSettings(): array
    {
        $plans = self::DEFAULTS;
        try {
            if (! Schema::hasTable('subscription_plan_settings')) {
                return $plans;
            }
            foreach (DB::table('subscription_plan_settings')->get() as $row) {
                if (! isset($plans[$row->plan_key])) {
                    continue;
                }
                $plan = &$plans[$row->plan_key];
                if (filled($row->name)) $plan['name'] = $row->name;
                if ($row->monthly_price !== null) $plan['monthly_price'] = (float) $row->monthly_price;
                if ($row->description !== null) $plan['description'] = $row->description;
                if ($row->highlights !== null) $plan['highlights'] = array_values((array) json_decode($row->highlights, true));
                if ($row->featured !== null) $plan['featured'] = (bool) $row->featured;
                if ($row->resource_limits !== null) {
                    foreach ((array) json_decode($row->resource_limits, true) as $resource => $limit) {
                        if (in_array($resource, self::EDITABLE_LIMITS, true)) {
                            $plan['resource_limits'][$resource] = $limit === null ? null : (int) $limit;
                        }
                    }
                }
                unset($plan);
            }
        } catch (\Throwable $e) {
            // The plans must always be readable: fall back to the defaults
            Log::warning('Subscription plan settings could not be read', ['error' => $e->getMessage()]);
        }

        return $plans;
    }

    public static function get(string $key): array
    {
        $plans = self::all();
        if (! isset($plans[$key])) {
            throw new \InvalidArgumentException("Unknown subscription plan [{$key}].");
        }

        return $plans[$key];
    }

    public static function exists(string $key): bool
    {
        return isset(self::DEFAULTS[$key]);
    }

    public static function includesModule(string $plan, string $module): bool
    {
        return in_array(self::canonicalModule($module), self::get($plan)['included_modules'], true);
    }

    public static function resourceLimit(string $plan, string $resource): ?int
    {
        return self::get($plan)['resource_limits'][$resource] ?? null;
    }

    public static function nextPlan(string $plan): ?array
    {
        $keys = array_keys(self::DEFAULTS);
        $index = array_search($plan, $keys, true);

        if ($index === false || !isset($keys[$index + 1])) {
            return null;
        }

        return self::get($keys[$index + 1]);
    }

    public static function nextPlanWithMoreOf(string $plan, string $resource): ?array
    {
        $keys = array_keys(self::DEFAULTS);
        $index = array_search($plan, $keys, true);
        if ($index === false) return null;

        $current = self::resourceLimit($plan, $resource);
        for ($position = $index + 1; isset($keys[$position]); $position++) {
            $candidate = self::get($keys[$position]);
            $limit = $candidate['resource_limits'][$resource] ?? null;
            if ($limit === null || ($current !== null && $limit > $current)) {
                return $candidate;
            }
        }

        return null;
    }

    public static function canonicalModule(string $module): string
    {
        return self::MODULE_ALIASES[$module] ?? $module;
    }

    public static function requiredPlanForModule(string $module): ?array
    {
        $module = self::canonicalModule($module);
        $keys = array_keys(self::DEFAULTS);

        foreach ($keys as $index => $key) {
            if (in_array($module, self::DEFAULTS[$key]['included_modules'], true)) {
                return [
                    'key' => $key,
                    'name' => self::get($key)['name'],
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

    /** What the pricing pages show (no limits a visitor doesn't need) */
    public static function publicList(): array
    {
        return array_values(array_map(fn ($p) => [
            'key' => $p['key'],
            'name' => $p['name'],
            'monthly_price' => $p['monthly_price'],
            'currency' => $p['currency'],
            'description' => $p['description'],
            'highlights' => $p['highlights'],
            'featured' => $p['featured'],
            'free_trial_available' => $p['free_trial_available'],
            'trial_duration_months' => $p['trial_duration_months'],
            'resource_limits' => $p['resource_limits'],
        ], self::all()));
    }
}
