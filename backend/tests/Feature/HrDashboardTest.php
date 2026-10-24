<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeave;
use App\Models\Payroll;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/** The HR overview: headcount, who is in today, what is waiting, the past week; HR dashboard permission only. */
class HrDashboardTest extends TestCase
{
    use CreatesAttendanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        foreach (['request_events', 'overtime_requests', 'leave_balance_adjustments', 'leave_policies', 'employee_leaves'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::create('employee_leaves', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('employee_id');
            $t->date('start_date');
            $t->date('end_date');
            $t->integer('total_days');
            $t->string('leave_type');
            $t->boolean('is_paid')->default(true);
            $t->string('status')->default('pending');
            $t->text('reason')->nullable();
            $t->text('admin_notes')->nullable();
            $t->unsignedBigInteger('reviewed_by')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('submission_token')->unique();
            $t->string('submission_ip', 45)->nullable();
            $t->string('submission_device')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_22_400000_rework_leave_management.php'))->up();

        Carbon::setTestNow(Carbon::parse('2026-10-21 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function scan($vendor, $profile, string $date, ?string $out = null, ?string $review = null): void
    {
        EmployeeAttendance::forceCreate([
            'owner_id' => $vendor->id, 'employee_id' => $profile->id, 'attendance_date' => $date,
            'time_in' => '08:00:00', 'time_out' => $out, 'review_status' => $review,
        ]);
    }

    private function leave($vendor, $profile, string $from, string $to, string $status): void
    {
        EmployeeLeave::forceCreate([
            'owner_id' => $vendor->id, 'employee_id' => $profile->id, 'start_date' => $from, 'end_date' => $to, 'total_days' => 1,
            'leave_type' => 'vacation_leave', 'status' => $status, 'submission_token' => Str::random(20),
        ]);
    }

    public function test_it_sums_up_today_and_the_week(): void
    {
        $vendor = $this->shopOwner();
        [$rosa, $ana, $ben, $cy] = [$this->profile($vendor), $this->profile($vendor, ['first_name' => 'Ana']), $this->profile($vendor, ['first_name' => 'Ben']), $this->profile($vendor, ['first_name' => 'Cy'])];
        $this->profile($vendor, ['first_name' => 'Gone', 'employment_status' => 'Inactive']);

        $this->scan($vendor, $rosa, '2026-10-21');                      // still in
        $this->scan($vendor, $ana, '2026-10-21', '09:30:00', 'pending'); // came and left, flagged
        $this->scan($vendor, $ben, '2026-10-21', null, 'rejected');      // doesn't count
        $this->scan($vendor, $rosa, '2026-10-20', '17:00:00');
        $this->scan($vendor, $rosa, '2026-10-10', '17:00:00');           // outside the week
        $this->leave($vendor, $cy, '2026-10-20', '2026-10-22', 'approved');
        $this->leave($vendor, $ben, '2026-10-25', '2026-10-26', 'approved');
        $this->leave($vendor, $ben, '2026-10-28', '2026-10-28', 'pending');
        Payroll::forceCreate(['owner_id' => $vendor->id, 'employee_id' => $rosa->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-15', 'net_salary' => 8000, 'status' => 'paid']);
        Payroll::forceCreate(['owner_id' => $vendor->id, 'employee_id' => $ana->id, 'period_start' => '2026-10-01', 'period_end' => '2026-10-15', 'net_salary' => 7000.5, 'status' => 'approved']);
        Payroll::forceCreate(['owner_id' => $vendor->id, 'employee_id' => $ben->id, 'period_start' => '2026-10-16', 'period_end' => '2026-10-31', 'net_salary' => 100, 'status' => 'pending']);

        // Another shop's people stay out
        $other = $this->shopOwner();
        $this->scan($other, $this->profile($other), '2026-10-21');

        $res = $this->signIn($vendor)->getJson('/api/hr/dashboard')->assertOk();
        $res->assertJsonPath('data.headcount', 4)
            ->assertJsonPath('data.present', 2)
            ->assertJsonPath('data.clocked_in', 1)
            ->assertJsonPath('data.on_leave', 1)
            ->assertJsonPath('data.not_in', 1)
            ->assertJsonPath('data.scheduled', null)
            ->assertJsonPath('data.waiting.leave', 1)
            ->assertJsonPath('data.waiting.attendance', 1)
            ->assertJsonPath('data.waiting.payroll', 1)
            ->assertJsonPath('data.payroll.period_end', '2026-10-15')
            ->assertJsonPath('data.payroll.people', 2)
            ->assertJsonPath('data.leave_today.0.name', 'Cy Cruz')
            ->assertJsonPath('data.upcoming_leave.0.start_date', '2026-10-25');
        $this->assertEquals(15000.5, $res->json('data.payroll.total'));
        $this->assertSame([0, 0, 0, 0, 0, 1, 2], array_column($res->json('data.week'), 'present'));
        $this->assertSame('2026-10-21', $res->json('data.week.6.date'));
        $this->assertCount(2, $res->json('data.activity'));
        $this->assertTrue(collect($res->json('data.activity'))->firstWhere('name', 'Ana Cruz')['flagged']);
    }

    public function test_staff_need_only_the_hr_dashboard_permission(): void
    {
        $vendor = $this->shopOwner();
        $grant = function (Employee $e, string $module) use ($vendor) {
            DB::table('employee_module_permissions')->insert([
                'owner_id' => $vendor->id, 'employee_id' => $e->id, 'module' => $module, 'permission' => 'view',
                'access' => 'granular', 'created_at' => now(), 'updated_at' => now(),
            ]);
        };
        $hr = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Hana', 'email' => 'hana@example.com', 'password' => 'x', 'status' => 'Active']);
        $grant($hr, 'hr_dashboard');
        $cashier = Employee::forceCreate(['owner_id' => $vendor->id, 'name' => 'Cass', 'email' => 'cass@example.com', 'password' => 'x', 'status' => 'Active']);
        $grant($cashier, 'sales_revenue');

        $this->signIn($hr)->getJson('/api/hr/dashboard')->assertOk()->assertJsonPath('data.headcount', 0);
        $this->app['auth']->forgetGuards();
        $this->signIn($cashier)->getJson('/api/hr/dashboard')->assertForbidden();
    }
}
