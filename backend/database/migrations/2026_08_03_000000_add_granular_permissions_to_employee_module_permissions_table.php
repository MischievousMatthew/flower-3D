<?php

use App\Constants\ErpModule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasPermissionColumn = Schema::hasColumn('employee_module_permissions', 'permission');

        if (! $hasPermissionColumn) {
            Schema::table('employee_module_permissions', function (Blueprint $table) {
                $table->string('permission')->nullable()->after('module');
                $table->dropUnique('emp_module_unique');
            });
        }

        // A retry after an interruption may find the new column but not the
        // new constraint. Inspect the index separately instead of treating the
        // column as proof that the migration completed.
        $indexNames = collect(Schema::getIndexes('employee_module_permissions'))
            ->pluck('name')
            ->map(fn ($name) => strtolower($name));
        if (! $indexNames->contains('emp_module_permission_unique')) {
            Schema::table('employee_module_permissions', function (Blueprint $table) {
                $table->unique(['employee_id', 'module', 'permission'], 'emp_module_permission_unique');
            });
        }

        // Only legacy rows are expanded. Rows already assigned a granular action
        // are left untouched, making the data conversion safe to resume or rerun.
        DB::table('employee_module_permissions')->whereNull('permission')->orderBy('id')->get()->each(function ($record): void {
            $permissions = $record->access === 'edit'
                ? ErpModule::permissionsFor($record->module)
                : ['view'];

            foreach ($permissions as $index => $permission) {
                if ($index === 0) {
                    DB::table('employee_module_permissions')->where('id', $record->id)->update([
                        'permission' => $permission,
                        'updated_at' => now(),
                    ]);
                    continue;
                }

                DB::table('employee_module_permissions')->insertOrIgnore([
                    'employee_id' => $record->employee_id,
                    'module' => $record->module,
                    'permission' => $permission,
                    'access' => $record->access,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

    }

    public function down(): void
    {
        Schema::table('employee_module_permissions', function (Blueprint $table) {
            $table->dropUnique('emp_module_permission_unique');
            $table->dropColumn('permission');
            $table->unique(['employee_id', 'module'], 'emp_module_unique');
        });
    }
};
