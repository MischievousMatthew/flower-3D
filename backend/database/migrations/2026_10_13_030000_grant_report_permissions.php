<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Give the built-in roles that work with numbers the new report permissions */
    private const GRANTS = [
        'Operations' => ['reports.view'],
        'Finance' => ['reports.view', 'reports.export'],
    ];

    public function up(): void
    {
        foreach (self::GRANTS as $name => $permissions) {
            $role = DB::table('admin_roles')->where('name', $name)->first();
            if (!$role) {
                continue;
            }
            $current = json_decode($role->permissions, true) ?: [];
            DB::table('admin_roles')->where('id', $role->id)->update([
                'permissions' => json_encode(array_values(array_unique([...$current, ...$permissions]))),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        foreach (self::GRANTS as $name => $permissions) {
            $role = DB::table('admin_roles')->where('name', $name)->first();
            if ($role) {
                $current = json_decode($role->permissions, true) ?: [];
                DB::table('admin_roles')->where('id', $role->id)->update([
                    'permissions' => json_encode(array_values(array_diff($current, $permissions))),
                ]);
            }
        }
    }
};
