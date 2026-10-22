<?php

namespace Tests\Feature;

use App\Models\ShiftTemplate;
use App\Models\StaffShift;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesAttendanceTables;
use Tests\Concerns\SignsInWithApiToken;
use Tests\TestCase;

/**
 * HR builds shifts, links people to them, drags people around, and has someone cover
 * a no-show — without double-booking anyone or scheduling them on leave.
 */
class ShiftPlannerTest extends TestCase
{
    use CreatesAttendanceTables, SignsInWithApiToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createAttendanceTables();
        Schema::dropIfExists('staff_shifts');
        Schema::dropIfExists('shift_templates');
        Schema::dropIfExists('employee_leaves');
        Schema::create('staff_shifts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('employee_id');
            $t->date('shift_date');
            $t->time('start_time')->nullable();
            $t->time('end_time')->nullable();
            $t->boolean('is_off')->default(false);
            $t->string('label', 40)->nullable();
            $t->string('note', 255)->nullable();
            $t->timestamps();
            $t->unique(['employee_id', 'shift_date']);
        });
        Schema::create('employee_leaves', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('owner_id');
            $t->unsignedBigInteger('employee_id');
            $t->date('start_date');
            $t->date('end_date');
            $t->string('status');
            $t->timestamps();
        });
        (require base_path('database/migrations/2026_10_22_200000_add_shift_planning.php'))->up();

        // Monday 2026-10-19 → Sunday 2026-10-25
        Carbon::setTestNow(Carbon::parse('2026-10-19 07:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function scheduler($vendor, array $permissions = ['view', 'create', 'edit', 'delete'])
    {
        $hr = $this->hrStaff($vendor, []);
        foreach ($permissions as $permission) {
            DB::table('employee_module_permissions')->insert([
                'owner_id' => $vendor->id, 'employee_id' => $hr->id, 'module' => 'schedules',
                'permission' => $permission, 'access' => 'granular', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        return $hr;
    }

    private function morning($vendor): ShiftTemplate
    {
        return ShiftTemplate::create([
            'owner_id' => $vendor->id, 'name' => 'Morning counter', 'start_time' => '08:00:00', 'end_time' => '17:00:00',
            'headcount' => 2, 'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        ]);
    }

    public function test_hr_creates_a_shift_and_links_people_skipping_rest_days_and_leave(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->scheduler($vendor);
        $john = $this->profile($vendor, ['first_name' => 'John', 'last_name' => 'Doe', 'rest_days' => 'Wednesday']);
        $jane = $this->profile($vendor, ['first_name' => 'Jane', 'last_name' => 'Doe', 'rest_days' => 'Sunday']);
        DB::table('employee_leaves')->insert(['owner_id' => $vendor->id, 'employee_id' => $jane->id, 'start_date' => '2026-10-22', 'end_date' => '2026-10-22', 'status' => 'approved']);

        $id = $this->signIn($hr)->postJson('/api/schedules/templates', [
            'name' => 'Morning counter', 'start_time' => '08:00', 'end_time' => '17:00', 'headcount' => 2,
            'days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
        ])->assertCreated()->json('data.id');

        $result = $this->signIn($hr)->postJson("/api/schedules/templates/{$id}/link", [
            'employee_ids' => [$john->id, $jane->id], 'from' => '2026-10-19', 'to' => '2026-10-25',
        ])->assertOk()->json('data');

        // 6 days each, minus John's Wednesday rest day and Jane's Thursday leave
        $this->assertSame(10, $result['added']);
        $this->assertEqualsCanonicalizing(['rest day', 'on leave'], array_column($result['skipped'], 'why'));
        $this->assertFalse(StaffShift::where('employee_id', $john->id)->whereDate('shift_date', '2026-10-21')->exists());
    }

    public function test_nobody_is_double_booked_and_rest_days_need_confirming(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->scheduler($vendor);
        $morning = $this->morning($vendor);
        $evening = ShiftTemplate::create(['owner_id' => $vendor->id, 'name' => 'Evening', 'start_time' => '13:00:00', 'end_time' => '21:00:00', 'headcount' => 1, 'days' => ['Monday', 'Tuesday']]);
        $john = $this->profile($vendor, ['first_name' => 'John', 'rest_days' => 'Tuesday']);

        $this->signIn($hr)->postJson('/api/schedules/assign', ['employee_id' => $john->id, 'date' => '2026-10-19', 'template_id' => $morning->id])->assertOk();

        // Dropping him on Evening the same day asks to move him
        $this->signIn($hr)->postJson('/api/schedules/assign', ['employee_id' => $john->id, 'date' => '2026-10-19', 'template_id' => $evening->id])
            ->assertStatus(409)->assertJsonPath('confirm', 'move');
        $this->signIn($hr)->postJson('/api/schedules/assign', ['employee_id' => $john->id, 'date' => '2026-10-19', 'template_id' => $evening->id, 'confirm' => ['move']])->assertOk();
        $this->assertSame(1, StaffShift::where('employee_id', $john->id)->count());
        $this->assertSame($evening->id, StaffShift::where('employee_id', $john->id)->value('shift_template_id'));

        // Tuesday is his rest day
        $this->signIn($hr)->postJson('/api/schedules/assign', ['employee_id' => $john->id, 'date' => '2026-10-20', 'template_id' => $morning->id])
            ->assertStatus(409)->assertJsonPath('confirm', 'allow_rest_day');
        $this->signIn($hr)->postJson('/api/schedules/assign', ['employee_id' => $john->id, 'date' => '2026-10-20', 'template_id' => $morning->id, 'confirm' => ['allow_rest_day']])->assertOk();
    }

    public function test_people_on_leave_or_inactive_cannot_be_scheduled(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->scheduler($vendor);
        $morning = $this->morning($vendor);
        $jane = $this->profile($vendor, ['first_name' => 'Jane']);
        $gone = $this->profile($vendor, ['first_name' => 'Gone', 'employment_status' => 'Inactive']);
        DB::table('employee_leaves')->insert(['owner_id' => $vendor->id, 'employee_id' => $jane->id, 'start_date' => '2026-10-19', 'end_date' => '2026-10-20', 'status' => 'approved']);

        $this->signIn($hr)->postJson('/api/schedules/assign', ['employee_id' => $jane->id, 'date' => '2026-10-20', 'template_id' => $morning->id, 'confirm' => ['move', 'allow_rest_day']])
            ->assertStatus(422)->assertJsonValidationErrors('employee_id');
        $this->signIn($hr)->postJson('/api/schedules/assign', ['employee_id' => $gone->id, 'date' => '2026-10-20', 'template_id' => $morning->id])
            ->assertStatus(422);
    }

    public function test_jane_covers_johns_no_show_and_john_still_counts_absent(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->scheduler($vendor);
        $morning = $this->morning($vendor);
        $john = $this->profile($vendor, ['first_name' => 'John', 'last_name' => 'Doe']);
        $jane = $this->profile($vendor, ['first_name' => 'Jane', 'last_name' => 'Doe', 'shift_start' => null, 'shift_end' => null]);
        $shift = StaffShift::create(['owner_id' => $vendor->id, 'employee_id' => $john->id, 'shift_date' => '2026-10-19', 'shift_template_id' => $morning->id,
            'start_time' => '08:00:00', 'end_time' => '17:00:00', 'status' => 'scheduled']);

        $this->signIn($hr)->postJson("/api/schedules/shifts/{$shift->id}/replace", ['employee_id' => $jane->id, 'reason' => 'no_show', 'note' => 'Not answering calls'])
            ->assertOk()->assertJsonPath('message', 'Jane Doe covers John Doe\'s shift.');

        $this->assertSame('replaced', $shift->fresh()->status);
        $cover = StaffShift::where('employee_id', $jane->id)->first();
        $this->assertSame($shift->id, $cover->replaces_shift_id);
        $this->assertSame('08:00:00', $cover->start_time);

        Carbon::setTestNow(Carbon::parse('2026-10-19 18:00:00'));
        $report = $this->signIn($vendor)->getJson('/api/vendor/staff/attendance/report?from=2026-10-19&to=2026-10-19')->assertOk()->json('data');
        $byName = collect($report['days'])->keyBy('employee');
        $this->assertSame('absent', $byName['John Doe']['status'], 'a no-show still counts absent');
        $this->assertSame('absent', $byName['Jane Doe']['status'], 'Jane is now expected and has not scanned in');

        // Undo: John is back on, Jane's cover is gone
        $this->signIn($hr)->postJson("/api/schedules/shifts/{$shift->id}/undo-replace")->assertOk();
        $this->assertSame('scheduled', $shift->fresh()->status);
        $this->assertFalse(StaffShift::where('employee_id', $jane->id)->exists());
    }

    public function test_an_excused_absence_does_not_count_and_moving_the_cover_gives_the_shift_back(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->scheduler($vendor);
        $morning = $this->morning($vendor);
        $john = $this->profile($vendor, ['first_name' => 'John', 'last_name' => 'Doe']);
        $jane = $this->profile($vendor, ['first_name' => 'Jane', 'last_name' => 'Doe']);
        $shift = StaffShift::create(['owner_id' => $vendor->id, 'employee_id' => $john->id, 'shift_date' => '2026-10-19', 'shift_template_id' => $morning->id,
            'start_time' => '08:00:00', 'end_time' => '17:00:00', 'status' => 'scheduled']);
        $this->signIn($hr)->postJson("/api/schedules/shifts/{$shift->id}/replace", ['employee_id' => $jane->id, 'reason' => 'sick'])->assertOk();

        Carbon::setTestNow(Carbon::parse('2026-10-19 18:00:00'));
        $report = $this->signIn($vendor)->getJson('/api/vendor/staff/attendance/report?from=2026-10-19&to=2026-10-19')->json('data');
        $this->assertNotContains('John Doe', collect($report['days'])->where('status', 'absent')->pluck('employee')->all());

        // Taking Jane off the cover puts John back on his shift
        $cover = StaffShift::where('employee_id', $jane->id)->first();
        $this->signIn($hr)->deleteJson("/api/schedules/shifts/{$cover->id}")->assertOk();
        $this->assertSame('scheduled', $shift->fresh()->status);
    }

    public function test_the_board_shows_who_is_free_resting_or_on_a_shift(): void
    {
        $vendor = $this->shopOwner();
        $hr = $this->scheduler($vendor, ['view']);
        $morning = $this->morning($vendor);
        $john = $this->profile($vendor, ['first_name' => 'John', 'rest_days' => 'Sunday']);
        $jane = $this->profile($vendor, ['first_name' => 'Jane', 'shift_start' => null, 'shift_end' => null, 'rest_days' => 'Monday']);
        $ana = $this->profile($vendor, ['first_name' => 'Ana', 'shift_start' => null, 'shift_end' => null, 'rest_days' => null]);
        StaffShift::create(['owner_id' => $vendor->id, 'employee_id' => $john->id, 'shift_date' => '2026-10-19', 'shift_template_id' => $morning->id,
            'start_time' => '08:00:00', 'end_time' => '17:00:00', 'status' => 'scheduled']);

        $board = $this->signIn($hr)->getJson('/api/schedules/board?from=2026-10-19&to=2026-10-25')->assertOk()->json('data');
        $staff = collect($board['staff'])->keyBy('id');
        $this->assertSame('shift', $staff[$john->id]['availability']['2026-10-19']['status']);
        $this->assertSame('rest', $staff[$jane->id]['availability']['2026-10-19']['status']);
        $this->assertSame('free', $staff[$ana->id]['availability']['2026-10-19']['status']);
        $this->assertSame('usual', $staff[$john->id]['availability']['2026-10-20']['status']);
        $this->assertCount(1, $board['templates']);

        // View-only HR can look but not change
        $this->signIn($hr)->postJson('/api/schedules/assign', ['employee_id' => $ana->id, 'date' => '2026-10-19', 'template_id' => $morning->id])->assertForbidden();
    }

    public function test_the_shop_owner_uses_the_same_planner(): void
    {
        $vendor = $this->shopOwner();
        $morning = $this->morning($vendor);
        $john = $this->profile($vendor, ['first_name' => 'John']);

        $this->signIn($vendor)->postJson('/api/vendor/staff/planner/assign', ['employee_id' => $john->id, 'date' => '2026-10-19', 'template_id' => $morning->id])->assertOk();
        $this->assertSame('Shop owner', StaffShift::first()->assigned_by);
        $this->signIn($vendor)->postJson('/api/vendor/staff/planner/copy-week', ['week' => '2026-10-26'])->assertOk()->assertJsonPath('data.copied', 1);
    }
}
