<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Staff & operations for vendors:
 *  - roles (like the admin console): a named set of permissions each employee is given;
 *  - work schedules (one shift per HR profile per day, on top of their usual hours);
 *  - an activity log of important employee actions.
 *
 * Existing per-employee permissions are turned into roles so nobody loses access:
 * employees of a shop with the same permissions share one role, named after
 * their job title.
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::create('staff_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('description', 255)->nullable();
            $table->string('color', 12)->nullable();
            $table->json('permissions');
            $table->timestamps();

            $table->unique(['owner_id', 'name']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('staff_role_id')->nullable()->after('role')->constrained('staff_roles')->nullOnDelete();
        });

        Schema::create('staff_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            // The HR profile (employees_info), like attendance and payroll
            $table->foreignId('employee_id')->constrained('employees_info')->cascadeOnDelete();
            $table->date('shift_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->boolean('is_off')->default(false); // a day off instead of their usual hours
            $table->string('label', 40)->nullable();
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'shift_date']);
            $table->index(['owner_id', 'shift_date']);
        });

        Schema::create('staff_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('employee_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('actor_name', 120)->nullable();
            $table->string('action', 160);
            $table->string('area', 40);
            $table->string('method', 8);
            $table->unsignedSmallInteger('status');
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['owner_id', 'created_at']);
            $table->index(['employee_id', 'created_at']);
        });

        $this->convertExistingPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_activity_logs');
        Schema::dropIfExists('staff_shifts');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('staff_role_id');
        });
        Schema::dropIfExists('staff_roles');
    }

    /** One role per distinct permission set in each shop. */
    private function convertExistingPermissions(): void
    {
        $palette = ['#48bb78', '#4299e1', '#9f7aea', '#ed8936', '#ed64a6', '#38b2ac', '#a0aec0'];
        $pairs = DB::table('employee_module_permissions')
            ->select('employee_id', 'module', 'permission')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($rows) => $rows->map(fn ($r) => "{$r->module}.{$r->permission}")->unique()->sort()->values()->all());

        $employees = DB::table('employees')->whereNull('deleted_at')->orderBy('id')->get(['id', 'owner_id', 'name', 'role']);
        $made = []; // owner => [signature => role id]
        $names = []; // owner => [lowercased names]

        foreach ($employees as $employee) {
            $permissions = $pairs[$employee->id] ?? [];
            if (! $permissions) {
                continue;
            }
            $signature = implode('|', $permissions);

            if (! isset($made[$employee->owner_id][$signature])) {
                $base = trim((string) $employee->role) !== '' ? ucwords(trim($employee->role)) : 'Staff';
                $name = $base;
                $n = 2;
                while (in_array(strtolower($name), $names[$employee->owner_id] ?? [], true)) {
                    $name = "{$base} {$n}";
                    $n++;
                }
                $names[$employee->owner_id][] = strtolower($name);

                $made[$employee->owner_id][$signature] = DB::table('staff_roles')->insertGetId([
                    'owner_id' => $employee->owner_id,
                    'name' => mb_substr($name, 0, 60),
                    'description' => 'Created from the permissions this team member already had.',
                    'color' => $palette[count($names[$employee->owner_id]) % count($palette)],
                    'permissions' => json_encode($permissions),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('employees')->where('id', $employee->id)->update(['staff_role_id' => $made[$employee->owner_id][$signature]]);
        }
    }
};
