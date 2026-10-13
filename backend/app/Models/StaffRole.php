<?php

namespace App\Models;

use App\Constants\ErpModule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vendor's staff role, e.g. "Florist" or "Cashier": a set of ERP
 * permissions ("deliveries.approve"). Employees get one role; the
 * permissions are copied onto them (employee_module_permissions) so the
 * `employee.module:` route checks keep working unchanged.
 */
class StaffRole extends Model
{
    protected $fillable = ['owner_id', 'name', 'description', 'color', 'permissions'];

    protected $casts = ['permissions' => 'array'];

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'staff_role_id');
    }

    /** Every permission key a role can hold, e.g. "attendance.edit". */
    public static function allKeys(): array
    {
        return collect(ErpModule::MODULE_PERMISSIONS)
            ->flatMap(fn ($actions, $module) => array_map(fn ($a) => "{$module}.{$a}", $actions))
            ->values()
            ->all();
    }

    /** Keep only real keys, and add "view" when a module has any other action (you can't approve what you can't see). */
    public static function normalize(array $keys): array
    {
        $valid = array_flip(self::allKeys());
        $keys = array_values(array_filter(array_unique($keys), fn ($k) => isset($valid[$k])));
        foreach ($keys as $key) {
            [$module] = explode('.', $key, 2);
            if (isset($valid["{$module}.view"]) && ! in_array("{$module}.view", $keys, true)) {
                $keys[] = "{$module}.view";
            }
        }
        sort($keys);

        return $keys;
    }
}
