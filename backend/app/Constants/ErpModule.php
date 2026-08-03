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

        // Finance
        'finance_dashboard',
        'funding_requests',
        'payroll_requests',

        // CRM
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
        'attendance'         => ['view', 'create', 'edit', 'delete'],
        'payroll'            => ['view', 'create', 'delete', 'approve'],
        'leave_management'   => ['view', 'approve', 'reject', 'delete'],
        'finance_dashboard'  => ['view'],
        'funding_requests'   => ['view', 'approve', 'reject'],
        'payroll_requests'   => ['view', 'edit', 'approve', 'reject'],
        'crm'                => ['view', 'create'],
        'inventory_products' => ['view', 'create', 'edit', 'delete'],
        'inventory_funding'  => ['view', 'create', 'edit', 'delete'],
        'sc_dashboard'       => ['view'],
        'suppliers'          => ['view', 'create', 'edit', 'delete'],
        'warehouse'          => ['view', 'create', 'edit', 'delete'],
        'sc_orders'          => ['view', 'create', 'edit', 'delete'],
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
