<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeModulePermission;
use App\Models\StaffRole;
use Illuminate\Support\Facades\DB;

/**
 * Copies a role's permissions onto the employees who hold it. Everything
 * that checks access (employee.module: middleware, ERP sign-in, the ERP
 * sidebar) reads employee_module_permissions, so it follows the role.
 */
class StaffAccessService
{
    public function syncEmployee(Employee $employee): void
    {
        $keys = $employee->staff_role_id
            ? (StaffRole::find($employee->staff_role_id)?->permissions ?? [])
            : [];

        DB::transaction(function () use ($employee, $keys) {
            EmployeeModulePermission::withoutGlobalScopes()->where('employee_id', $employee->id)->delete();

            $now = now();
            $rows = collect($keys)->map(function ($key) use ($employee, $now) {
                [$module, $permission] = explode('.', $key, 2);

                return [
                    'owner_id' => $employee->owner_id,
                    'employee_id' => $employee->id,
                    'module' => $module,
                    'permission' => $permission,
                    'access' => 'role',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            })->values()->all();

            if ($rows) {
                EmployeeModulePermission::withoutGlobalScopes()->insert($rows);
            }
        });
    }

    public function syncRole(StaffRole $role): void
    {
        Employee::withoutGlobalScopes()->where('staff_role_id', $role->id)->get()
            ->each(fn (Employee $e) => $this->syncEmployee($e));
    }
}
