<?php

namespace Tests\Feature;

use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeave;
use App\Models\OvertimeRequest;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\EmployeeQRCodeService;
use App\Services\Leave\LeaveAccess;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Employees file leave and overtime from the shop's leave page after scanning their badge;
 * HR approves with a history kept; balances follow the shop's rules.
 */
class LeaveManagementTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
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

        Carbon::setTestNow(Carbon::parse('2026-10-19 09:00:00')); // a Monday
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Sign in on the leave page with a badge; returns the session token */
    private function leaveSession($vendor, $profile): string
    {
        $code = app(LeaveAccess::class)->shopCode($vendor->id);

        return $this->postJson('/api/public/leave/identify', ['shop' => $code, 'badge' => app(EmployeeQRCodeService::class)->payload($profile)])
            ->assertOk()->json('data.token');
    }

    private function fileLeave(string $token, array $overrides = [])
    {
        return $this->postJson('/api/public/leave/requests', array_merge([
            'token' => $token, 'leave_type' => 'vacation_leave', 'start_date' => '2026-10-20', 'end_date' => '2026-10-22', 'reason' => 'Family trip to Baguio',
        ], $overrides));
    }

    public function test_the_poster_code_and_badges_cannot_be_made_up(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->profile($vendor);
        $code = app(LeaveAccess::class)->shopCode($vendor->id);

        $this->postJson('/api/public/leave/shop', ['shop' => "{$vendor->id}-0000000000000000"])->assertStatus(422);
        $this->postJson('/api/public/leave/shop', ['shop' => $code])->assertOk();

        $forged = json_encode(['type' => 'employee_attendance', 'owner_id' => $vendor->id, 'employee_id' => $p->id, 'v' => 1, 'sig' => str_repeat('b', 32)]);
        $this->postJson('/api/public/leave/identify', ['shop' => $code, 'badge' => $forged])->assertStatus(422);

        $this->postJson('/api/public/leave/identify', ['shop' => $code, 'badge' => app(EmployeeQRCodeService::class)->payload($p)])
            ->assertOk()->assertJsonPath('data.employee.name', 'Rosa Cruz')
            ->assertJsonPath('data.balances.0.leave_type', 'vacation_leave')
            ->assertJsonPath('data.balances.0.available', 5);

        // The old made-up token endpoints are gone
        $this->postJson('/api/public/leave/verify-qr', ['token' => base64_encode(json_encode(['employee_id' => $p->id, 'owner_id' => $vendor->id]))])->assertStatus(410);
    }

    public function test_leave_counts_scheduled_days_and_stays_within_the_balance(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->profile($vendor); // works Mon–Sat, rests Sunday
        $token = $this->leaveSession($vendor, $p);

        $this->fileLeave($token)->assertCreated()->assertJsonPath('data.request.days', 3)
            ->assertJsonPath('data.balances.0.available', 2);
        // Overlapping days
        $this->fileLeave($token, ['start_date' => '2026-10-22', 'end_date' => '2026-10-22'])->assertStatus(422)->assertJsonValidationErrors('start_date');
        // Saturday + Sunday + Monday = 2 working days: fits; then nothing is left
        $this->fileLeave($token, ['start_date' => '2026-10-24', 'end_date' => '2026-10-26'])->assertCreated()->assertJsonPath('data.balances.0.available', 0);
        $this->fileLeave($token, ['start_date' => '2026-10-28', 'end_date' => '2026-10-28'])->assertStatus(422)->assertJsonValidationErrors('leave_type');
        // Sunday only: no leave needed
        $this->fileLeave($token, ['leave_type' => 'unpaid_leave', 'start_date' => '2026-11-01', 'end_date' => '2026-11-01'])->assertStatus(422);
    }

    public function test_some_leave_needs_a_document(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->profile($vendor);
        $this->fileLeave($this->leaveSession($vendor, $p), ['leave_type' => 'bereavement_leave'])
            ->assertStatus(422)->assertJsonValidationErrors('document');
    }

    public function test_hr_approves_with_a_history_and_cannot_approve_their_own(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor, [], 'hr.self@example.test');
        foreach (['view', 'approve', 'reject'] as $perm) {
            DB::table('employee_module_permissions')->insert(['owner_id' => $vendor->id, 'employee_id' => $hr->id, 'module' => 'leave_management', 'permission' => $perm, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now()]);
        }
        $p = $this->profile($vendor);
        $self = $this->profile($vendor, ['work_email' => 'hr.self@example.test']);
        $id = $this->fileLeave($this->leaveSession($vendor, $p))->json('data.request.id');
        $mine = $this->fileLeave($this->leaveSession($vendor, $self))->json('data.request.id');

        $this->signIn($hr)->putJson("/api/leaves/{$mine}/status", ['status' => 'approved'])->assertStatus(422);
        $this->signIn($hr)->putJson("/api/leaves/{$id}/status", ['status' => 'rejected'])->assertStatus(422)->assertJsonValidationErrors('admin_notes');
        $this->signIn($hr)->putJson("/api/leaves/{$id}/status", ['status' => 'approved', 'admin_notes' => 'Enjoy'])
            ->assertOk()
            ->assertJsonPath('data.reviewer', $hr->name)
            ->assertJsonPath('data.history.0.action', 'filed')
            ->assertJsonPath('data.history.1.action', 'approved');
    }

    public function test_employees_cancel_waiting_requests_only(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->profile($vendor);
        $token = $this->leaveSession($vendor, $p);
        $id = $this->fileLeave($token)->json('data.request.id');

        $this->postJson("/api/public/leave/requests/{$id}/cancel", ['token' => $token])->assertOk();
        $this->assertSame('cancelled', EmployeeLeave::withoutGlobalScopes()->find($id)->status);

        $id = $this->fileLeave($token)->json('data.request.id');
        EmployeeLeave::withoutGlobalScopes()->whereKey($id)->update(['status' => 'approved']);
        $this->postJson("/api/public/leave/requests/{$id}/cancel", ['token' => $token])->assertStatus(422);
    }

    public function test_approved_leave_now_reaches_attendance_and_payroll_by_shop(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->profile($vendor);
        $id = $this->fileLeave($this->leaveSession($vendor, $p), ['start_date' => '2026-10-19', 'end_date' => '2026-10-19'])->json('data.request.id');
        $this->signIn($vendor)->putJson("/api/leaves/{$id}/status", ['status' => 'approved'])->assertOk();

        $this->assertSame($vendor->id, EmployeeLeave::withoutGlobalScopes()->find($id)->owner_id);
        Carbon::setTestNow(Carbon::parse('2026-10-19 18:00:00'));
        $this->signIn($vendor)->getJson('/api/vendor/staff/attendance/report?from=2026-10-19&to=2026-10-19')
            ->assertOk()->assertJsonPath('data.totals.on_leave', 1)->assertJsonPath('data.totals.absent', 0);
    }

    public function test_overtime_approved_ahead_is_approved_at_time_out(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->profile($vendor);
        $token = $this->leaveSession($vendor, $p);
        $this->postJson('/api/public/leave/overtime', ['token' => $token, 'date' => '2026-10-19', 'minutes' => 90, 'reason' => 'Wedding order rush'])->assertCreated();
        $request = OvertimeRequest::first();
        $this->signIn($vendor)->putJson("/api/overtime-requests/{$request->id}/status", ['status' => 'approved'])->assertOk();

        $record = EmployeeAttendance::withoutGlobalScopes()->create([
            'owner_id' => $vendor->id, 'employee_id' => $p->id, 'attendance_date' => '2026-10-19',
            'time_in' => '08:00:00', 'time_out' => '18:00:00', 'total_hours' => 10, 'status' => 'complete',
        ]);
        app(AttendanceCalculator::class)->applyOvertime($vendor->id, $record, $p);
        $this->assertSame('approved', $record->overtime_status);
        $this->assertSame(60, $record->overtime_approved_minutes);

        // Staying past what was approved goes to HR
        $record->time_out = '19:00:00';
        app(AttendanceCalculator::class)->applyOvertime($vendor->id, $record, $p);
        $this->assertSame('pending', $record->overtime_status);
    }

    public function test_hr_adjusts_balances_and_rules(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->profile($vendor);
        $this->signIn($vendor)->postJson('/api/leaves/balances/adjust', ['employee_id' => $p->id, 'leave_type' => 'vacation_leave', 'year' => 2026, 'days' => 2, 'note' => 'Carried over'])
            ->assertOk()->assertJsonPath('data.0.available', 7);

        $this->signIn($vendor)->putJson('/api/leaves/policies', ['policies' => [
            ['leave_type' => 'sick_leave', 'annual_days' => 10, 'is_paid' => true, 'enabled' => true, 'requires_document' => true],
        ]])->assertOk();
        $this->signIn($vendor)->getJson('/api/leaves/balances?year=2026')->assertOk()
            ->assertJsonPath('data.people.0.balances.1.allowance', 10);
    }
}
