<?php

namespace Tests\Concerns;

use App\Models\Employee;
use App\Models\EmployeeInfo;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** The tables attendance needs, built by hand for the SQLite test database. */
trait CreatesAttendanceTables
{
    use CreatesSubscriptionTables;

    // The shop pin used by these tests (Makati), and a spot about 2 km away
    protected float $shopLat = 14.5547;
    protected float $shopLng = 121.0244;
    protected float $farLat = 14.5730;

    protected function createAttendanceTables(): void
    {
        Schema::disableForeignKeyConstraints();
        foreach (['attendance_corrections', 'face_enrollments', 'attendance_sessions', 'attendance_devices', 'attendance_settings',
            'employee_attendance', 'employees_info', 'employee_module_permissions', 'employees', 'vendor_applications', 'users'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('role')->default('customer');
            $t->string('api_token', 80)->nullable()->unique();
            $t->rememberToken()->nullable();
            $t->timestamps();
        });
        Schema::create('vendor_applications', function (Blueprint $t) {
            $t->id();
            $t->string('email');
            $t->string('status')->default('approved');
            $t->string('store_address')->nullable();
            $t->decimal('store_latitude', 10, 7)->nullable();
            $t->decimal('store_longitude', 10, 7)->nullable();
            $t->timestamps();
        });
        Schema::create('employees', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id')->nullable();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('username')->nullable();
            $t->string('department')->nullable();
            $t->string('password');
            $t->string('status')->nullable();
            $t->string('api_token', 80)->nullable()->unique();
            $t->rememberToken()->nullable();
            $t->softDeletes();
            $t->timestamps();
        });
        Schema::create('employee_module_permissions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('employee_id');
            $t->string('module');
            $t->string('permission');
            $t->string('access')->nullable();
            $t->timestamps();
        });
        Schema::create('employees_info', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('created_by_employee_id')->nullable();
            $t->string('employee_id', 20);
            foreach (['first_name', 'middle_name', 'last_name', 'avatar', 'work_email', 'personal_email', 'employment_status', 'position', 'shift_start', 'shift_end', 'rest_days'] as $c) {
                $t->string($c)->nullable();
            }
            $t->date('date_hired')->nullable();
            $t->unsignedInteger('qr_version')->default(1);
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('employee_attendance', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('employee_id');
            $t->date('attendance_date');
            $t->string('day', 20)->nullable();
            $t->unsignedTinyInteger('month')->nullable();
            $t->unsignedSmallInteger('year')->nullable();
            $t->time('time_in')->nullable();
            $t->time('time_out')->nullable();
            $t->decimal('total_hours', 5, 2)->nullable();
            $t->string('status')->default('incomplete');
            $t->text('notes')->nullable();
            $t->string('review_status', 20)->nullable();
            $t->json('review_reasons')->nullable();
            $t->string('reviewed_by', 150)->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note', 500)->nullable();
            $t->json('time_in_evidence')->nullable();
            $t->json('time_out_evidence')->nullable();
            $t->string('source', 20)->nullable();
            $t->timestamps();
        });

        // The new tables come from the real migration
        $migration = require base_path('database/migrations/2026_10_22_100000_add_attendance_security.php');
        $migration->up();
        (require base_path('database/migrations/2026_10_22_300000_add_clock_in_rules_and_overtime.php'))->up();

        $this->createSubscriptionTables();
    }

    protected function shopOwner(): User
    {
        $vendor = User::create(['name' => 'Shop Owner', 'email' => fake()->unique()->safeEmail(), 'password' => 'x', 'role' => User::ROLE_VENDOR]);
        DB::table('vendor_applications')->insert([
            'email' => $vendor->email, 'store_address' => 'Makati', 'store_latitude' => $this->shopLat, 'store_longitude' => $this->shopLng,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->giveActivePlan($vendor, 'professional');

        return $vendor;
    }

    protected function hrStaff(User $vendor, array $permissions = ['view', 'create', 'edit'], string $email = null): Employee
    {
        $employee = Employee::forceCreate([
            'owner_id' => $vendor->id, 'name' => 'HR ' . fake()->firstName(), 'email' => $email ?? fake()->unique()->safeEmail(),
            'department' => 'HR', 'password' => 'x', 'status' => 'Active',
        ]);
        foreach ($permissions as $permission) {
            DB::table('employee_module_permissions')->insert([
                'owner_id' => $vendor->id, 'employee_id' => $employee->id, 'module' => 'attendance',
                'permission' => $permission, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $employee;
    }

    protected function profile(User $vendor, array $overrides = []): EmployeeInfo
    {
        return EmployeeInfo::forceCreate(array_merge([
            'owner_id' => $vendor->id, 'employee_id' => 'EMP-' . fake()->unique()->numberBetween(10000, 99999),
            'first_name' => 'Rosa', 'last_name' => 'Cruz', 'employment_status' => 'Regular', 'position' => 'Florist',
            'shift_start' => '08:00:00', 'shift_end' => '17:00:00', 'rest_days' => 'Sunday',
            'work_email' => fake()->unique()->safeEmail(), 'date_hired' => '2026-01-01',
        ], $overrides));
    }

    protected function atShop(array $extra = []): array
    {
        return array_merge(['latitude' => $this->shopLat, 'longitude' => $this->shopLng, 'accuracy' => 15], $extra);
    }

    protected function farAway(array $extra = []): array
    {
        return array_merge(['latitude' => $this->farLat, 'longitude' => $this->shopLng, 'accuracy' => 15], $extra);
    }
}
