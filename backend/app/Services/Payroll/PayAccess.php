<?php

namespace App\Services\Payroll;

use App\Models\Employee;
use App\Models\EmployeeInfo;

/**
 * Salary information is for the shop owner, HR with payroll access, and Finance with payroll
 * request access only. Everyone else gets profiles without pay or bank details.
 */
class PayAccess
{
    /** Hidden on every EmployeeInfo unless the viewer may see pay (see EmployeeInfo::$hidden) */
    public const FIELDS = ['basic_salary', 'salary_type', 'payment_method', 'bank_name', 'account_number', 'tax_status', 'allowance'];

    public static function canSee($user): bool
    {
        if (! $user) {
            return false;
        }
        if (! $user instanceof Employee) {
            return true; // the shop owner
        }

        return $user->hasModulePermission('payroll', 'view') || $user->hasModulePermission('payroll_requests', 'view');
    }

    /** Changing someone's pay: the owner, or HR who can create payroll */
    public static function canEdit($user): bool
    {
        return $user && (! $user instanceof Employee || $user->hasModulePermission('payroll', 'create'));
    }

    /** Show pay fields on these profiles if the viewer may see them. */
    public static function reveal($user, $profiles)
    {
        if (self::canSee($user)) {
            foreach ($profiles instanceof EmployeeInfo ? [$profiles] : $profiles as $p) {
                $p->makeVisible(self::FIELDS);
            }
        }

        return $profiles;
    }
}
