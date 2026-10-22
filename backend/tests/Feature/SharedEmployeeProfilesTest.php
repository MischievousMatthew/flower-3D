<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesSubscriptionTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * HR profiles and their documents are one shared record: what the shop owner adds
 * in the vendor portal, HR sees in the ERP, and the other way round.
 */
class SharedEmployeeProfilesTest extends TestCase
{
    use CreatesSubscriptionTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::disableForeignKeyConstraints();
        foreach (['employee_documents', 'employees_info', 'employee_module_permissions', 'employees', 'users'] as $name) {
            Schema::dropIfExists($name);
        }
        Schema::enableForeignKeyConstraints();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('role')->default('customer');
            $table->string('api_token', 80)->nullable()->unique();
            $table->rememberToken()->nullable();
            $table->timestamps();
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('role')->nullable();
            $table->string('department')->nullable();
            $table->string('password');
            $table->date('joining_date')->nullable();
            $table->string('status')->nullable();
            $table->string('api_token', 80)->nullable()->unique();
            $table->rememberToken()->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::create('employee_module_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('employee_id');
            $table->string('module');
            $table->string('permission');
            $table->string('access')->nullable();
            $table->timestamps();
        });

        Schema::create('employees_info', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('created_by_employee_id')->nullable();
            $table->string('employee_id', 20);
            foreach (['first_name', 'middle_name', 'last_name', 'gender', 'civil_status', 'avatar', 'personal_email', 'work_email',
                'mobile_number', 'address', 'emergency_contact_name', 'emergency_contact_number', 'emergency_relationship',
                'employment_status', 'position', 'department', 'employment_type', 'work_location', 'reporting_manager',
                'work_schedule', 'shift_start', 'shift_end', 'rest_days', 'salary_type', 'payment_method', 'bank_name',
                'account_number', 'tax_status'] as $column) {
                $table->string($column)->nullable();
            }
            $table->date('date_of_birth')->nullable();
            $table->date('date_hired')->nullable();
            $table->decimal('standard_work_hours_per_day', 5, 2)->nullable();
            $table->integer('working_days_per_week')->nullable();
            $table->integer('working_days_per_month')->nullable();
            $table->decimal('basic_salary', 12, 2)->nullable();
            $table->decimal('allowance', 12, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('owner_id');
            $table->unsignedBigInteger('employee_info_id');
            $table->string('category', 40);
            $table->string('title', 150);
            $table->string('file_name', 255);
            $table->string('public_id', 255);
            $table->string('resource_type', 20)->default('image');
            $table->string('format', 20)->nullable();
            $table->unsignedInteger('size')->nullable();
            $table->date('expires_on')->nullable();
            $table->string('uploaded_by', 150)->nullable();
            $table->timestamps();
        });

        $this->createSubscriptionTables();
    }

    private function vendor(): User
    {
        $vendor = User::create([
            'name' => 'Shop Owner',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => User::ROLE_VENDOR,
        ]);
        $this->giveActivePlan($vendor, 'professional');

        return $vendor;
    }

    private function employee(User $vendor, string $department, array $permissions): Employee
    {
        $employee = Employee::forceCreate([
            'owner_id' => $vendor->id,
            'name' => "{$department} Person",
            'email' => fake()->unique()->safeEmail(),
            'department' => $department,
            'password' => 'password',
            'status' => 'Active',
        ]);
        foreach ($permissions as $permission) {
            DB::table('employee_module_permissions')->insert([
                'owner_id' => $vendor->id, 'employee_id' => $employee->id,
                'module' => 'employees', 'permission' => $permission, 'access' => 'granular',
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $employee;
    }

    private function profilePayload(array $overrides = []): array
    {
        return array_merge([
            'first_name' => 'Rosa', 'last_name' => 'Cruz', 'gender' => 'Female', 'date_of_birth' => '1998-04-12',
            'civil_status' => 'Single', 'personal_email' => 'rosa@home.test', 'work_email' => 'rosa@shop.test',
            'mobile_number' => '09171234567', 'address' => 'Quezon City',
            'emergency_contact_name' => 'Ana Cruz', 'emergency_contact_number' => '09181234567', 'emergency_relationship' => 'Mother',
            'employment_status' => 'Probationary', 'position' => 'Florist', 'department' => 'Production',
            'employment_type' => 'Full-time', 'date_hired' => '2026-10-01', 'work_location' => 'Main shop',
            'reporting_manager' => 'Shop Owner', 'work_schedule' => 'Fixed', 'shift_start' => '08:00', 'shift_end' => '17:00',
            'rest_days' => 'Sunday',
        ], $overrides);
    }

    public function test_a_profile_the_shop_owner_adds_is_visible_to_hr_and_stays_owner_added_after_hr_edits(): void
    {
        $vendor = $this->vendor();
        $hr = $this->employee($vendor, 'HR', ['view', 'edit']);

        $id = $this->signIn($vendor)->postJson('/api/employees-info', $this->profilePayload())
            ->assertCreated()
            ->json('data.id');

        $this->signIn($hr)->getJson('/api/employees-info')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.emergency_contact_name', 'Ana Cruz')
            ->assertJsonPath('data.0.added_by', 'Shop owner');

        $this->signIn($hr)->putJson("/api/employees-info/{$id}", $this->profilePayload(['position' => 'Senior florist']))
            ->assertOk();

        $this->signIn($vendor)->getJson("/api/employees-info/{$id}")
            ->assertOk()
            ->assertJsonPath('data.position', 'Senior florist')
            ->assertJsonPath('data.added_by', 'Shop owner');
    }

    public function test_a_profile_hr_adds_is_visible_to_the_shop_owner_and_names_who_added_it(): void
    {
        $vendor = $this->vendor();
        $hr = $this->employee($vendor, 'HR', ['view', 'create']);

        $id = $this->signIn($hr)->postJson('/api/employees-info', $this->profilePayload())
            ->assertCreated()
            ->json('data.id');

        $this->signIn($vendor)->getJson("/api/employees-info/{$id}")
            ->assertOk()
            ->assertJsonPath('data.added_by', $hr->name)
            ->assertJsonMissingPath('data.created_by');
    }

    public function test_documents_are_shared_and_only_the_owner_or_hr_can_remove_them(): void
    {
        $vendor = $this->vendor();
        $hr = $this->employee($vendor, 'HR', ['view', 'create', 'edit']);
        $cashier = $this->employee($vendor, 'Sales', ['view', 'edit']);

        $id = $this->signIn($hr)->postJson('/api/employees-info', $this->profilePayload())->json('data.id');
        $documentId = DB::table('employee_documents')->insertGetId([
            'owner_id' => $vendor->id, 'employee_info_id' => $id, 'category' => 'Contract', 'title' => 'Employment contract',
            'file_name' => 'contract.pdf', 'public_id' => 'employee-documents/1/contract', 'resource_type' => 'image',
            'format' => 'pdf', 'uploaded_by' => $hr->name, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->signIn($vendor)->getJson("/api/employees-info/{$id}/documents")
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Employment contract')
            ->assertJsonPath('data.0.uploaded_by', $hr->name)
            ->assertJsonMissingPath('data.0.public_id');

        $this->signIn($cashier)->deleteJson("/api/employees-info/{$id}/documents/{$documentId}")->assertForbidden();

        $this->signIn($vendor)->deleteJson("/api/employees-info/{$id}/documents/{$documentId}")->assertOk();
        $this->assertDatabaseMissing('employee_documents', ['id' => $documentId]);
    }

    public function test_another_shop_cannot_see_the_profile_or_its_documents(): void
    {
        $vendor = $this->vendor();
        $other = $this->vendor();

        $id = $this->signIn($vendor)->postJson('/api/employees-info', $this->profilePayload())->json('data.id');

        $this->signIn($other)->getJson("/api/employees-info/{$id}")->assertNotFound();
        $this->signIn($other)->getJson("/api/employees-info/{$id}/documents")->assertNotFound();
    }
}
