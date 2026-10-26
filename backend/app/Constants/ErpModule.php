<?php

namespace App\Constants;

/**
 * Centralized list of ERP modules.
 * Mirrors frontend/src/constants/erpModules.js — keep in sync.
 */
class ErpModule
{
    public const MODULES = [
        // HR
        'hr_dashboard',
        'employees',
        'attendance',
        'payroll',
        'leave_management',
        'schedules',

        // Finance
        'finance_dashboard',
        'sales_revenue',
        'payments',
        'expenses',
        'profit_loss',
        'refunds',
        'financial_reports',
        'subscription_billing',
        'funding_requests',
        'payroll_requests',

        // CRM
        'crm_dashboard',
        'crm_customers',
        'crm_cases',
        'crm_segments',
        'crm_reviews',
        'crm',

        // Procurement / Inventory
        'inventory_products',
        'inventory_funding',

        // Supply Chain
        'sc_dashboard',
        'suppliers',
        'warehouse',
        'sc_orders',
        'deliveries',
        'order_scan',
    ];

    public const PERMISSIONS = [
        'view', 'create', 'edit', 'delete', 'approve', 'reject', 'export', 'print', 'manage',
    ];

    /** Permissions that are implemented for each ERP module. */
    public const MODULE_PERMISSIONS = [
        'hr_dashboard'       => ['view', 'export'],
        'employees'          => ['view', 'create', 'edit', 'delete', 'export', 'print'],
        // edit = request corrections; approve = approve flagged scans and others' corrections
        'attendance'         => ['view', 'create', 'edit', 'delete', 'approve'],
        'payroll'            => ['view', 'create', 'delete', 'approve'],
        // create = file leave/overtime for someone; edit = leave rules, balances, cancelling approved leave
        'leave_management'   => ['view', 'create', 'edit', 'approve', 'reject', 'delete'],
        // create = put people on shifts; edit = shifts, replacements, day-off overrides; delete = remove shifts
        'schedules'          => ['view', 'create', 'edit', 'delete'],
        'finance_dashboard'  => ['view'],
        'sales_revenue'      => ['view'],
        // edit = record cash-on-delivery collections and settlements
        'payments'           => ['view', 'edit'],
        'expenses'           => ['view', 'create', 'edit', 'delete'],
        'profit_loss'        => ['view'],
        // edit = record a refund as sent
        'refunds'            => ['view', 'edit'],
        'financial_reports'  => ['view', 'export'],
        'subscription_billing' => ['view'],
        'funding_requests'   => ['view', 'approve', 'reject'],
        'payroll_requests'   => ['view', 'edit', 'approve', 'reject'],
        'crm_dashboard'      => ['view'],
        'crm_customers'      => ['view', 'create', 'edit', 'delete'],
        'crm_cases'          => ['view', 'create', 'edit'],
        'crm_segments'       => ['view', 'create'],
        'crm_reviews'        => ['view', 'create'],
        'crm'                => ['view', 'create'],
        'inventory_products' => ['view', 'create', 'edit', 'delete'],
        'inventory_funding'  => ['view', 'create', 'edit', 'delete'],
        'sc_dashboard'       => ['view'],
        'suppliers'          => ['view', 'create', 'edit', 'delete'],
        'warehouse'          => ['view', 'create', 'edit', 'delete'],
        // approve = approve orders and send them to the supplier, or send them back
        'sc_orders'          => ['view', 'create', 'edit', 'delete', 'approve'],
        'deliveries'         => ['view', 'edit', 'approve', 'reject', 'export', 'print'],
        'order_scan'         => ['view', 'edit'],
    ];

    /**
     * Comma-separated list for Laravel "in:" validation rule.
     */
    public static function validKeysRule(): string
    {
        return 'in:' . implode(',', self::MODULES);
    }

    /**
     * Comma-separated list for access level validation.
     */
    public static function validAccessRule(): string
    {
        return 'in:' . implode(',', self::PERMISSIONS);
    }

    public static function permissionsFor(string $module): array
    {
        return self::MODULE_PERMISSIONS[$module] ?? [];
    }

    public static function isPermissionValidForModule(string $module, string $permission): bool
    {
        return in_array($permission, self::permissionsFor($module), true);
    }

    /**
     * Check if a module key is valid.
     */
    public static function isValid(string $key): bool
    {
        return in_array($key, self::MODULES, true);
    }
}
