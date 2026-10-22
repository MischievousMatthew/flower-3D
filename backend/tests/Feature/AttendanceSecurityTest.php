<?php

namespace Tests\Feature;

use App\Models\AttendanceDevice;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use App\Models\FaceEnrollment;
use App\Services\Attendance\AttendanceKioskService;
use App\Services\EmployeeQRCodeService;
use Carbon\Carbon;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\CreatesPayrollTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * Attendance can only be taken on an owner-approved device at the shop, with a signed QR code
 * and a face the server matches; doubtful scans wait for review and don't count for payroll.
 */
class AttendanceSecurityTest extends TestCase
{
    use CreatesAttendanceTables, CreatesPayrollTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        Carbon::setTestNow(Carbon::parse('2026-10-21 08:05:00'));

        // No real uploads in tests: pretend every snapshot was stored
        $this->app->bind(AttendanceKioskService::class, fn ($app) => new class($app->make(\App\Services\Attendance\AttendanceLocationService::class), $app->make(EmployeeQRCodeService::class)) extends AttendanceKioskService {
            protected function storeSnapshots(int $ownerId, $snapshots, string $label): array
            {
                return $snapshots ? ['attendance-evidence/test-photo'] : [];
            }
        });
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function face(float $shift = 0.0): array
    {
        return array_map(fn ($i) => round(sin($i) * 0.1 + $shift, 6), range(1, 128));
    }

    /** A device the owner approved, and its token */
    private function approvedDevice($vendor, $hr): string
    {
        $token = $this->signIn($hr)->postJson('/api/attendance/kiosk/devices', ['name' => 'Front counter'] + $this->atShop())
            ->assertCreated()->json('data.device_token');
        AttendanceDevice::where('owner_id', $vendor->id)->update(['status' => 'approved', 'approved_at' => now()]);

        return $token;
    }

    private function qr(EmployeeInfo $p): string
    {
        return app(EmployeeQRCodeService::class)->payload($p->fresh());
    }

    /** Everything a browser would send after doing the session's checks correctly */
    private function goodScan(array $session, array $overrides = []): array
    {
        return array_merge($this->atShop(), [
            'challenge_results' => array_map(fn ($id) => ['id' => $id, 'passed' => true, 'duration_ms' => 1500], $session['challenges']),
            'descriptor' => $this->face(),
            'antispoof' => 0.92,
            'liveness' => 0.88,
            'flash' => array_map(fn ($c) => ['color' => $c, 'delta' => [
                $c === 'red' ? 9 : 1, $c === 'green' ? 9 : 1, $c === 'blue' ? 9 : 1,
            ]], $session['flash']),
            'camera' => ['label' => 'Integrated Camera', 'virtual' => false],
            'snapshots' => ['data:image/jpeg;base64,/9j/'],
        ], $overrides);
    }

    private function startScan($hr, string $token, EmployeeInfo $p, array $position = null): \Illuminate\Testing\TestResponse
    {
        return $this->signIn($hr)->postJson('/api/attendance/kiosk/sessions', ['device_token' => $token, 'qr_data' => $this->qr($p)] + ($position ?? $this->atShop()));
    }

    public function test_the_old_endpoints_that_trusted_the_browser_are_gone(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor);
        $p = $this->profile($vendor);

        foreach (['/api/attendance/scan', '/api/attendance/time-in', '/api/attendance/time-out'] as $url) {
            $this->signIn($hr)->postJson($url, ['employee_id' => $p->id, 'face_verified' => true, 'qr_data' => $this->qr($p)])->assertStatus(410);
        }
        $this->assertSame(0, EmployeeAttendance::withoutGlobalScopes()->count());
    }

    public function test_a_device_can_only_be_requested_at_the_shop_and_scans_need_the_owners_approval(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor);
        $p = $this->profile($vendor, ['avatar' => 'https://example.test/rosa.jpg']);

        $this->signIn($hr)->postJson('/api/attendance/kiosk/devices', ['name' => 'Home laptop'] + $this->farAway())
            ->assertStatus(422)->assertJsonValidationErrors('location');

        $token = $this->signIn($hr)->postJson('/api/attendance/kiosk/devices', ['name' => 'Front counter'] + $this->atShop())
            ->assertCreated()->json('data.device_token');
        $this->startScan($hr, $token, $p)->assertStatus(422)->assertJsonPath('errors.device.0', 'This device is waiting for the shop owner\'s approval.');

        $device = AttendanceDevice::first();
        $this->signIn($vendor)->postJson("/api/vendor/staff/attendance/devices/{$device->id}/approve")->assertOk();
        $this->startScan($hr, $token, $p)->assertOk()->assertJsonPath('data.purpose', 'time_in');
    }

    public function test_the_owner_can_approve_at_most_three_devices(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor);
        foreach (range(1, 4) as $n) {
            $this->signIn($hr)->postJson('/api/attendance/kiosk/devices', ['name' => "Tablet {$n}"] + $this->atShop())->assertCreated();
        }
        $ids = AttendanceDevice::orderBy('id')->pluck('id');
        foreach ($ids->take(3) as $id) {
            $this->signIn($vendor)->postJson("/api/vendor/staff/attendance/devices/{$id}/approve")->assertOk();
        }
        $this->signIn($vendor)->postJson("/api/vendor/staff/attendance/devices/{$ids[3]}/approve")->assertStatus(422);
        $this->assertSame(3, AttendanceDevice::where('status', 'approved')->count());
    }

    public function test_an_approved_device_cannot_take_attendance_away_from_the_shop(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor);
        $p = $this->profile($vendor);
        $token = $this->approvedDevice($vendor, $hr);

        $this->startScan($hr, $token, $p, $this->farAway())->assertStatus(422)->assertJsonValidationErrors('location');
        $this->startScan($hr, $token, $p, $this->atShop(['accuracy' => 900]))->assertStatus(422)->assertJsonValidationErrors('location');
    }

    public function test_forged_and_replaced_qr_codes_are_refused(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor);
        $p = $this->profile($vendor);
        $token = $this->approvedDevice($vendor, $hr);

        $forged = json_encode(['type' => 'employee_attendance', 'owner_id' => $vendor->id, 'employee_id' => $p->id, 'v' => 1, 'sig' => str_repeat('a', 32)]);
        $this->signIn($hr)->postJson('/api/attendance/kiosk/sessions', ['device_token' => $token, 'qr_data' => $forged] + $this->atShop())
            ->assertStatus(422)->assertJsonPath('errors.qr_data.0', 'This QR code isn\'t genuine.');

        $old = $this->qr($p);
        $p->forceFill(['qr_version' => 2])->save();
        $this->signIn($hr)->postJson('/api/attendance/kiosk/sessions', ['device_token' => $token, 'qr_data' => $old] + $this->atShop())
            ->assertStatus(422)->assertJsonPath('errors.qr_data.0', 'This QR code was replaced. Use the newest one.');
    }

    public function test_a_clean_scan_of_an_enrolled_face_counts_right_away(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor);
        $p = $this->profile($vendor);
        FaceEnrollment::create(['owner_id' => $vendor->id, 'employee_info_id' => $p->id, 'descriptors' => [$this->face(), $this->face(0.01)]]);
        $token = $this->approvedDevice($vendor, $hr);

        $session = $this->startScan($hr, $token, $p)->assertOk()->json('data');
        $this->assertCount(4, $session['challenges']);
        $this->assertNull($session['employee']['avatar_url'], 'enrolled faces stay on the server');

        Carbon::setTestNow(now()->addSeconds(12));
        $this->signIn($hr)->postJson("/api/attendance/kiosk/sessions/{$session['session_id']}/complete", ['device_token' => $token] + $this->goodScan($session))
            ->assertOk()->assertJsonPath('data.type', 'time_in')->assertJsonPath('data.needs_review', false);

        $record = EmployeeAttendance::withoutGlobalScopes()->first();
        $this->assertNull($record->review_status);
        $this->assertSame('kiosk', $record->source);

        // The same session can't be used twice
        $this->signIn($hr)->postJson("/api/attendance/kiosk/sessions/{$session['session_id']}/complete", ['device_token' => $token] + $this->goodScan($session))
            ->assertStatus(422)->assertJsonValidationErrors('session');
    }

    public function test_a_different_face_or_a_screen_is_refused_and_nothing_is_recorded(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor);
        $p = $this->profile($vendor);
        FaceEnrollment::create(['owner_id' => $vendor->id, 'employee_info_id' => $p->id, 'descriptors' => [$this->face()]]);
        $token = $this->approvedDevice($vendor, $hr);

        $session = $this->startScan($hr, $token, $p)->json('data');
        Carbon::setTestNow(now()->addSeconds(12));
        $this->signIn($hr)->postJson("/api/attendance/kiosk/sessions/{$session['session_id']}/complete", ['device_token' => $token] + $this->goodScan($session, ['descriptor' => $this->face(0.2)]))
            ->assertStatus(422)->assertJsonValidationErrors('face');

        $session = $this->startScan($hr, $token, $p)->json('data');
        Carbon::setTestNow(now()->addSeconds(12));
        $this->signIn($hr)->postJson("/api/attendance/kiosk/sessions/{$session['session_id']}/complete", ['device_token' => $token] + $this->goodScan($session, ['antispoof' => 0.1]))
            ->assertStatus(422)->assertJsonValidationErrors('antispoof');

        $this->assertSame(0, EmployeeAttendance::withoutGlobalScopes()->count());
        $this->signIn($vendor)->getJson('/api/vendor/staff/attendance/review')->assertOk()->assertJsonCount(2, 'data.blocked');
    }

    public function test_challenges_must_be_the_servers_in_order_and_take_real_time(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor);
        $p = $this->profile($vendor);
        FaceEnrollment::create(['owner_id' => $vendor->id, 'employee_info_id' => $p->id, 'descriptors' => [$this->face()]]);
        $token = $this->approvedDevice($vendor, $hr);

        $session = $this->startScan($hr, $token, $p)->json('data');
        Carbon::setTestNow(now()->addSeconds(12));
        $scan = $this->goodScan($session);
        $scan['challenge_results'] = array_reverse($scan['challenge_results']);
        $this->signIn($hr)->postJson("/api/attendance/kiosk/sessions/{$session['session_id']}/complete", ['device_token' => $token] + $scan)
            ->assertStatus(422)->assertJsonValidationErrors('challenges');

        $session = $this->startScan($hr, $token, $p)->json('data');
        Carbon::setTestNow(now()->addSeconds(1)); // faster than a person could do it
        $this->signIn($hr)->postJson("/api/attendance/kiosk/sessions/{$session['session_id']}/complete", ['device_token' => $token] + $this->goodScan($session))
            ->assertStatus(422)->assertJsonValidationErrors('session');
    }

    public function test_doubtful_scans_wait_for_review_and_dont_count_for_payroll_until_approved(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor, ['view', 'create', 'edit', 'approve']);
        // Not enrolled yet, and an old unsigned QR code
        $p = $this->profile($vendor, ['avatar' => 'https://example.test/rosa.jpg']);
        $token = $this->approvedDevice($vendor, $hr);
        $legacyQr = json_encode(['type' => 'employee_attendance', 'owner_id' => $vendor->id, 'employee_id' => $p->id]);

        $session = $this->signIn($hr)->postJson('/api/attendance/kiosk/sessions', ['device_token' => $token, 'qr_data' => $legacyQr] + $this->atShop())
            ->assertOk()->json('data');
        $this->assertSame('https://example.test/rosa.jpg', $session['employee']['avatar_url']);
        Carbon::setTestNow(now()->addSeconds(12));
        $this->signIn($hr)->postJson("/api/attendance/kiosk/sessions/{$session['session_id']}/complete", ['device_token' => $token] + $this->goodScan($session, ['descriptor' => null, 'photo_distance' => 0.38]))
            ->assertOk()->assertJsonPath('data.needs_review', true);

        $record = EmployeeAttendance::withoutGlobalScopes()->first();
        $this->assertSame('pending', $record->review_status);
        $this->assertCount(2, $record->review_reasons);
        $this->assertSame(0, EmployeeAttendance::withoutGlobalScopes()->countable()->count());

        $this->signIn($vendor)->postJson("/api/vendor/staff/attendance/review/{$record->id}", ['decision' => 'approve'])->assertOk();
        $this->assertSame(1, EmployeeAttendance::withoutGlobalScopes()->countable()->count());
    }

    public function test_people_cannot_approve_their_own_attendance_or_their_own_correction(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor, ['view', 'create', 'edit', 'approve'], 'hr.self@example.test');
        $other = $this->hrStaff($vendor, ['view', 'edit', 'approve']);
        $self = $this->profile($vendor, ['work_email' => 'hr.self@example.test']);
        $p = $this->profile($vendor);

        $mine = $this->signIn($hr)->postJson('/api/attendance/corrections', ['employee_id' => $p->id, 'date' => '2026-10-20', 'time_in' => '08:00', 'time_out' => '17:00', 'reason' => 'Scanner was down'])
            ->assertCreated()->json('data.id');
        $this->signIn($hr)->postJson("/api/attendance/corrections/{$mine}/review", ['decision' => 'approve'])->assertStatus(422);

        $forSelf = $this->signIn($other)->postJson('/api/attendance/corrections', ['employee_id' => $self->id, 'date' => '2026-10-20', 'time_in' => '08:00', 'time_out' => '17:00', 'reason' => 'Forgot to scan'])
            ->assertCreated()->json('data.id');
        $this->signIn($hr)->postJson("/api/attendance/corrections/{$forSelf}/review", ['decision' => 'approve'])->assertStatus(422);

        $this->signIn($other)->postJson("/api/attendance/corrections/{$mine}/review", ['decision' => 'approve'])->assertOk();
        $record = EmployeeAttendance::withoutGlobalScopes()->where('employee_id', $p->id)->first();
        $this->assertSame('correction', $record->source);
        $this->assertSame('9.00', (string) $record->total_hours);
    }

    public function test_the_report_counts_late_undertime_and_absent_days(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->profile($vendor);
        $make = fn ($date, $in, $out) => EmployeeAttendance::withoutGlobalScopes()->create([
            'owner_id' => $vendor->id, 'employee_id' => $p->id, 'attendance_date' => $date, 'time_in' => $in, 'time_out' => $out,
            'total_hours' => 8, 'status' => 'complete',
        ]);
        $make('2026-10-19', '08:30:00', '17:00:00'); // Monday: 30 min late
        $make('2026-10-20', '08:00:00', '16:00:00'); // Tuesday: left an hour early
        // Wednesday 21: no record, and it's past 17:00

        Carbon::setTestNow(Carbon::parse('2026-10-21 18:00:00'));
        $this->signIn($vendor)->getJson('/api/vendor/staff/attendance/report?from=2026-10-18&to=2026-10-21')
            ->assertOk()
            ->assertJsonPath('data.totals.late', 1)
            ->assertJsonPath('data.totals.late_minutes', 30)
            ->assertJsonPath('data.totals.undertime_minutes', 60)
            ->assertJsonPath('data.totals.absent', 1)
            ->assertJsonPath('data.totals.scheduled', 3); // Sunday 18 is a rest day
    }

    // ── Clock-in rules and overtime ─────────────────────────────────────────

    /** An enrolled person, an approved scanner, and the HR operator */
    private function readyKiosk(array $hrPermissions = ['view', 'create', 'edit']): array
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor, $hrPermissions);
        $p = $this->profile($vendor); // 08:00–17:00, rests Sunday
        FaceEnrollment::create(['owner_id' => $vendor->id, 'employee_info_id' => $p->id, 'descriptors' => [$this->face()]]);
        $token = $this->approvedDevice($vendor, $hr);

        return [$vendor, $hr, $p, $token];
    }

    private function scanAt(string $time, $hr, string $token, EmployeeInfo $p): \Illuminate\Testing\TestResponse
    {
        Carbon::setTestNow(Carbon::parse($time));
        $start = $this->startScan($hr, $token, $p);
        if ($start->status() !== 200) {
            return $start;
        }
        $session = $start->json('data');
        Carbon::setTestNow(Carbon::parse($time)->addSeconds(12));

        return $this->signIn($hr)->postJson("/api/attendance/kiosk/sessions/{$session['session_id']}/complete", ['device_token' => $token] + $this->goodScan($session));
    }

    public function test_nobody_can_time_in_before_their_shift_starts(): void
    {
        [$vendor, $hr, $p, $token] = $this->readyKiosk();

        Carbon::setTestNow(Carbon::parse('2026-10-21 07:50:00'));
        $this->startScan($hr, $token, $p)->assertStatus(422)
            ->assertJsonPath('errors.qr_data.0', 'Rosa Cruz\'s shift starts at 8:00 AM. Time in from 8:00 AM.');

        $this->scanAt('2026-10-21 08:20:00', $hr, $token, $p)->assertOk()->assertJsonPath('data.late_minutes', 20);
    }

    public function test_the_owner_can_allow_timing_in_a_few_minutes_early(): void
    {
        [$vendor, $hr, $p, $token] = $this->readyKiosk();
        $this->signIn($vendor)->putJson('/api/vendor/staff/attendance/setup', [
            'latitude' => $this->shopLat, 'longitude' => $this->shopLng, 'radius_m' => 100, 'late_grace_minutes' => 10, 'early_clock_in_minutes' => 15,
        ])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-10-21 07:40:00'));
        $this->startScan($hr, $token, $p)->assertStatus(422);
        $this->scanAt('2026-10-21 07:46:00', $hr, $token, $p)->assertOk()->assertJsonPath('data.late_minutes', 0);
    }

    public function test_no_time_in_on_an_unscheduled_day_or_after_the_shift(): void
    {
        [$vendor, $hr, $p, $token] = $this->readyKiosk();

        Carbon::setTestNow(Carbon::parse('2026-10-25 09:00:00')); // Sunday, a rest day
        $this->startScan($hr, $token, $p)->assertStatus(422)->assertJsonPath('errors.qr_data.0', 'Rosa Cruz isn\'t scheduled to work today. HR can put them on a shift in Schedules.');

        Carbon::setTestNow(Carbon::parse('2026-10-21 17:30:00'));
        $this->startScan($hr, $token, $p)->assertStatus(422)->assertJsonPath('errors.qr_data.0', 'Rosa Cruz\'s shift ended at 5:00 PM. Ask HR for a correction if they worked.');
    }

    public function test_staying_late_is_overtime_that_waits_for_hr(): void
    {
        [$vendor, $hr, $p, $token] = $this->readyKiosk(['view', 'create', 'edit', 'approve']);
        $this->scanAt('2026-10-21 08:00:00', $hr, $token, $p)->assertOk();
        $this->scanAt('2026-10-21 18:10:00', $hr, $token, $p)->assertOk()->assertJsonPath('data.overtime_minutes', 70);

        $record = EmployeeAttendance::withoutGlobalScopes()->first();
        $this->assertSame('pending', $record->overtime_status);
        $this->signIn($hr)->getJson('/api/attendance/review')->assertOk()->assertJsonPath('data.overtime.0.minutes', 70);

        // HR approves only the hour that was asked for
        $this->signIn($hr)->postJson("/api/attendance/{$record->id}/overtime", ['decision' => 'approve', 'minutes' => 80])->assertStatus(422);
        $this->signIn($hr)->postJson("/api/attendance/{$record->id}/overtime", ['decision' => 'approve', 'minutes' => 60])->assertOk();
        $this->assertSame(60, $record->fresh()->overtime_approved_minutes);
        $this->signIn($hr)->postJson("/api/attendance/{$record->id}/overtime", ['decision' => 'reject', 'note' => 'late'])->assertStatus(422);
    }

    public function test_a_few_minutes_past_the_end_is_not_overtime(): void
    {
        [$vendor, $hr, $p, $token] = $this->readyKiosk();
        $this->scanAt('2026-10-21 08:00:00', $hr, $token, $p)->assertOk();
        $this->scanAt('2026-10-21 17:10:00', $hr, $token, $p)->assertOk()->assertJsonPath('data.overtime_minutes', null);
        $this->assertNull(EmployeeAttendance::withoutGlobalScopes()->first()->overtime_status);
    }

    public function test_hr_cannot_approve_their_own_overtime(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->hrStaff($vendor, ['view', 'approve'], 'hr.self@example.test');
        $self = $this->profile($vendor, ['work_email' => 'hr.self@example.test']);
        $record = EmployeeAttendance::withoutGlobalScopes()->create([
            'owner_id' => $vendor->id, 'employee_id' => $self->id, 'attendance_date' => '2026-10-20', 'time_in' => '08:00:00', 'time_out' => '19:00:00',
            'total_hours' => 11, 'status' => 'complete', 'overtime_minutes' => 120, 'overtime_status' => 'pending',
        ]);

        $this->signIn($hr)->postJson("/api/attendance/{$record->id}/overtime", ['decision' => 'approve'])->assertStatus(422);
        $this->signIn($vendor)->postJson("/api/vendor/staff/attendance/overtime/{$record->id}", ['decision' => 'approve'])->assertOk();
        $this->assertSame(120, $record->fresh()->overtime_approved_minutes);
    }

    public function test_the_owner_entering_times_settles_overtime_at_once(): void
    {
        $vendor = $this->shopOwner();
        $p = $this->profile($vendor);
        $this->signIn($vendor)->postJson('/api/vendor/staff/attendance', [
            'employee_id' => $p->id, 'date' => '2026-10-20', 'time_in' => '08:00', 'time_out' => '18:30',
        ])->assertCreated();

        $record = EmployeeAttendance::withoutGlobalScopes()->first();
        $this->assertSame('approved', $record->overtime_status);
        $this->assertSame(90, $record->overtime_approved_minutes);
    }

    public function test_payroll_pays_only_approved_overtime_at_the_overtime_rate(): void
    {
        $vendor = $this->shopOwner();
        $this->createPayrollTables();
        // ₱800 a day for 8 hours → ₱100 an hour
        $p = $this->profile($vendor, ['basic_salary' => 800, 'salary_type' => 'daily', 'standard_work_hours_per_day' => 8]);
        $day = fn ($date, $status, $minutes) => EmployeeAttendance::withoutGlobalScopes()->create([
            'owner_id' => $vendor->id, 'employee_id' => $p->id, 'attendance_date' => $date, 'time_in' => '08:00:00', 'time_out' => '19:00:00',
            'total_hours' => 11, 'status' => 'complete', 'overtime_minutes' => 120, 'overtime_status' => $status, 'overtime_approved_minutes' => $minutes,
        ]);
        $day('2026-10-19', 'approved', 60);
        $day('2026-10-20', 'pending', null);
        $day('2026-10-21', 'rejected', 0);

        $preview = app(\App\Services\PayrollService::class)->previewPayroll($vendor->id, $p->id, '2026-10-19', '2026-10-21');
        $this->assertTrue($preview['success'], $preview['message'] ?? '');
        $this->assertSame('1.00', $preview['data']['overtime_hours']);
        $this->assertSame('125.00', $preview['data']['overtime_amount']); // 1 h × ₱100 × 1.25
        $this->assertSame('2,525.00', $preview['data']['gross_salary']); // 3 days × ₱800 + ₱125
    }
}
