<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->onDelete('cascade');
            $table->string('module');   // e.g. 'payroll', 'warehouse', 'hr_dashboard'
            $table->string('access');   // 'view' or 'edit'
            $table->timestamps();

            $table->unique(['employee_id', 'module'], 'emp_module_unique');
            $table->index('employee_id');
        });

        // ── Auto-migrate existing assignment data ────────────────────────────
        // Read each employee's active assignments → role → accessible_modules
        // and create module permission records with 'edit' access.
        if (Schema::hasTable('employee_assignments') && Schema::hasTable('roles')) {
            $assignments = \DB::table('employee_assignments')
                ->where('is_active', true)
                ->join('roles', 'employee_assignments.role_id', '=', 'roles.id')
                ->select('employee_assignments.employee_id', 'roles.accessible_modules')
                ->get();

            $validModules = \App\Constants\ErpModule::MODULES;
            $seen = [];

            foreach ($assignments as $assignment) {
                $modules = json_decode($assignment->accessible_modules, true) ?? [];

                foreach ($modules as $moduleKey) {
                    if (!in_array($moduleKey, $validModules, true)) continue;

                    $comboKey = "{$assignment->employee_id}:{$moduleKey}";
                    if (isset($seen[$comboKey])) continue;
                    $seen[$comboKey] = true;

                    \DB::table('employee_module_permissions')->insert([
                        'employee_id' => $assignment->employee_id,
                        'module'      => $moduleKey,
                        'access'      => 'edit', // grant edit by default from old system
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_module_permissions');
    }
};
