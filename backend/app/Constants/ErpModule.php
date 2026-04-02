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
        'leave',

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

    public const ACCESS_LEVELS = ['view', 'edit'];

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
        return 'in:' . implode(',', self::ACCESS_LEVELS);
    }

    /**
     * Check if a module key is valid.
     */
    public static function isValid(string $key): bool
    {
        return in_array($key, self::MODULES, true);
    }
}
