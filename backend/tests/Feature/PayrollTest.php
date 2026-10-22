<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendance;
use App\Models\PayComponent;
use App\Models\Payroll;
use App\Services\GovernmentContributionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Payroll: pay lines, prorated contributions, approval controls with history, and salary
 * information kept to people with payroll access.
 */
class PayrollTest extends TestCase
{
    use CreatesAttendanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        $this->createPayrollTables();
        // The profile list counts documents
        \Illuminate\Support\Facades\Schema::dropIfExists('employee_documents');
        \Illuminate\Support\Facades\Schema::create('employee_documents', function ($t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('employee_info_id');
            $t->timestamps();
        });
        Carbon::setTestNow(Carbon::parse('2026-11-02 09:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function grant($vendor, $employee, string $module, array $perms): void
    {
        foreach ($perms as $p) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $employee->id, 'module' => $module, 'permission' => $p, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    /** ₱800 a day for 8 hours, worked Mon 19 – Wed 21 Oct */
    private function paidWorker($vendor)
    {
        $p = $this->profile($vendor, ['basic_salary' => 800, 'salary_type' => 'daily', 'standard_work_hours_per_day' => 8, 'date_hired' => '2026-01-01']);
        foreach (['2026-10-19', '2026-10-20', '2026-10-21'] as $d) {
            EmployeeAttendance::withoutGlobalScopes()->create(['owner_id' => $vendor->id, 'employee_id' => $p->id, 'attendance_date' => $d,
                'time_in' => '08:00:00', 'time_out' => '17:00:00', 'total_hours' => 9, 'status' => 'complete']);
        }

        return $p;
    }

    public function test_contribution_rates_and_each_payroll_takes_its_share_of_the_month(): void
    {
        $gov = new GovernmentContributionService();
        $this->assertSame(1000.0, $gov->computeSSS(20000));   // MSC 20,000 × 5%
        $this->assertSame(1750.0, $gov->computeSSS(80000));   // capped at MSC 35,000
        $this->assertSame(250.0, $gov->computePhilHealth(5000)); // floor ₱10,000 × 2.5%
        $this->assertSame(500.0, $gov->computePhilHealth(20000));
        $this->assertSame(200.0, $gov->computePagIbig(20000)); // 2% of ₱10,000 max

        $vendor = $this->shopOwner();
        $p = $this->paidWorker($vendor);
        $week = $this->signIn($vendor)->postJson('/api/payroll/preview', [
            'employee_id' => $p->id, 'period_start' => '2026-10-19', 'period_end' => '2026-10-25', 'include_contributions' => true,
        ])->assertOk()->json('data');
        // ₱800/day → ₱20,800 a month (SSS credit ₱21,000); a week is 7/31 of October, not a whole month's worth
        $monthly = 1050 + 520 + 200;
        $this->assertEqualsWithDelta($monthly * 7 / 31, (float) str_replace(',', '', $week['total_contributions']), 0.05);
    }

    public function test_allowances_bonuses_and_deductions_make_up_the_pay(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->paidWorker($vendor);
        PayComponent::create(['owner_id' => $vendor->id, 'employee_info_id' => $p->id, 'kind' => 'allowance', 'label' => 'Meal', 'amount' => 100, 'basis' => 'per_day', 'active' => true]);
        PayComponent::create(['owner_id' => $vendor->id, 'employee_info_id' => $p->id, 'kind' => 'deduction', 'label' => 'Uniform', 'amount' => 150, 'basis' => 'per_payroll', 'active' => true]);

        $this->signIn($vendor)->postJson('/api/payroll/preview', [
            'employee_id' => $p->id, 'period_start' => '2026-10-19', 'period_end' => '2026-10-25',
            'items' => [['kind' => 'bonus', 'label' => 'Wedding rush', 'amount' => 500], ['kind' => 'deduction', 'label' => 'Cash advance', 'amount' => 300]],
        ])->assertOk()
            ->assertJsonPath('data.allowances_amount', '300.00')       // ₱100 × 3 days worked
            ->assertJsonPath('data.bonuses_amount', '500.00')
            ->assertJsonPath('data.other_deductions_amount', '450.00') // uniform + cash advance
            ->assertJsonPath('data.gross_salary', '3,200.00')          // 2,400 + 300 + 500
            ->assertJsonPath('data.net_salary', '2,750.00');
    }

    public function test_approval_needs_someone_else_and_keeps_a_history(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor, []);
        $this->grant($vendor, $hr, 'payroll', ['view', 'create', 'delete']);
        $this->grant($vendor, $hr, 'payroll_requests', ['view', 'approve', 'reject', 'edit']);
        $finance = $this->hrStaff($vendor, []);
        $this->grant($vendor, $finance, 'payroll_requests', ['view', 'approve', 'reject', 'edit']);
        $p = $this->paidWorker($vendor);

        $id = $this->signIn($hr)->postJson('/api/payroll', ['employee_id' => $p->id, 'period_start' => '2026-10-19', 'period_end' => '2026-10-25'])
            ->assertCreated()->json('data.id');
        // Same person can't also be the approver
        $this->signIn($hr)->postJson('/api/payroll/finance-approve', ['ids' => [$id]])->assertStatus(422);
        // Overlapping period for the same person
        $this->signIn($hr)->postJson('/api/payroll', ['employee_id' => $p->id, 'period_start' => '2026-10-25', 'period_end' => '2026-10-31'])->assertStatus(422);

        $this->signIn($finance)->postJson('/api/payroll/finance-reject', ['ids' => [$id]])->assertStatus(422); // needs a note
        $this->signIn($finance)->postJson('/api/payroll/finance-reject', ['ids' => [$id], 'finance_notes' => 'Add the wedding bonus'])->assertOk();
        $this->signIn($hr)->putJson("/api/payroll/{$id}", ['items' => [['kind' => 'bonus', 'label' => 'Wedding rush', 'amount' => 500]]])
            ->assertOk()->assertJsonPath('data.status', 'pending');
        $this->signIn($finance)->postJson('/api/payroll/finance-approve', ['ids' => [$id]])->assertOk();

        $this->assertSame('approved', Payroll::withoutGlobalScopes()->find($id)->status);
        $this->signIn($hr)->putJson("/api/payroll/{$id}", ['items' => []])->assertStatus(422);
        $this->signIn($hr)->deleteJson("/api/payroll/{$id}")->assertStatus(422);
        $this->signIn($finance)->postJson('/api/payroll/mark-paid', ['ids' => [$id]])->assertOk();

        $slip = $this->signIn($finance)->getJson("/api/payroll/finance/{$id}")->assertOk()->json('data');
        $this->assertSame(['created', 'rejected', 'resubmitted', 'approved', 'paid'], array_column($slip['history'], 'action'));
        $this->assertEquals(2900, $slip['net']);
        $this->assertSame('Wedding rush', $slip['earnings'][1]['label']);
    }

    public function test_salary_is_only_shown_to_people_with_payroll_access(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->paidWorker($vendor);
        $p->forceFill(['bank_name' => 'BDO', 'account_number' => '001234567890'])->save();
        $hr = $this->hrStaff($vendor, []);
        $this->grant($vendor, $hr, 'employees', ['view']);
        $this->grant($vendor, $hr, 'attendance', ['view']);

        $row = $this->signIn($hr)->getJson('/api/employees-info')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('basic_salary', $row);
        $this->assertArrayNotHasKey('account_number', $row);
        $attendance = $this->signIn($hr)->getJson('/api/attendance')->assertOk()->json('data.0.employee');
        $this->assertArrayNotHasKey('basic_salary', $attendance);

        $this->grant($vendor, $hr, 'payroll', ['view']);
        $this->signIn($hr)->getJson('/api/employees-info')->assertOk()->assertJsonPath('data.0.basic_salary', '800.00');
    }

    public function test_search_finds_people_by_name(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->paidWorker($vendor);
        $this->signIn($vendor)->postJson('/api/payroll', ['employee_id' => $p->id, 'period_start' => '2026-10-19', 'period_end' => '2026-10-25'])->assertCreated();

        $this->signIn($vendor)->getJson('/api/payroll?search=Rosa')->assertOk()->assertJsonCount(1, 'data');
        $this->signIn($vendor)->getJson('/api/payroll?search=Nobody')->assertOk()->assertJsonCount(0, 'data');
    }
}
