<?php

namespace App\Services\Schedules;

use App\Exceptions\ScheduleConflict;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use App\Models\EmployeeLeave;
use App\Models\ShiftTemplate;
use App\Models\StaffShift;
use App\Services\Attendance\AttendanceCalculator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Shift planning for the ERP and the vendor portal. Every assignment is a staff_shifts row
 * (one per person per day), so double-booking is impossible and attendance follows the plan.
 *
 * Hard stops: inactive people, approved leave, days before they were hired.
 * Confirmable: working on a rest day (allow_rest_day), moving someone off another shift (move).
 * Warnings only: more than MAX_WEEK_HOURS in the week, a shift short of people.
 */
class ShiftPlanner
{
    public const MAX_WEEK_HOURS = 48;
    public const MAX_RANGE_DAYS = 62;

    private ?bool $hasLeaves = null; // per instance: one request, no stale answer across tests

    public function __construct(private AttendanceCalculator $calculator) {}

    // ── The board ──────────────────────────────────────────────────────────

    /** Shifts, who's on them, and everyone's availability, day by day. */
    public function board(int $ownerId, Carbon $from, Carbon $to): array
    {
        $templates = ShiftTemplate::where('owner_id', $ownerId)->whereNull('archived_at')->orderBy('start_time')->get();
        $people = EmployeeInfo::forOwner($ownerId)->orderBy('first_name')->get()->filter(fn ($p) => $this->calculator->isActive($p))->values();
        $names = EmployeeInfo::forOwner($ownerId)->withTrashed()->get(['id', 'first_name', 'middle_name', 'last_name'])->mapWithKeys(fn ($p) => [$p->id => $p->full_name]);
        $shifts = StaffShift::where('owner_id', $ownerId)
            ->onDays($from->toDateString(), $to->toDateString())
            ->with('template:id,name,color')
            ->get();
        $byPersonDay = $shifts->keyBy(fn ($s) => $s->employee_id . '|' . $s->shift_date->toDateString());
        $leave = $this->leaveDays($ownerId, $from, $to);
        $days = collect(CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()))->map->toDateString()->values();

        $assignments = $shifts->map(fn (StaffShift $s) => [
            'id' => $s->id,
            'employee_id' => $s->employee_id,
            'employee' => $names[$s->employee_id] ?? 'Former employee',
            'date' => $s->shift_date->toDateString(),
            'template_id' => $s->shift_template_id,
            'kind' => $s->is_off ? 'off' : ($s->shift_template_id ? 'shift' : 'custom'),
            'start' => AttendanceCalculator::hm($s->start_time),
            'end' => AttendanceCalculator::hm($s->end_time),
            'label' => $s->label,
            'status' => $s->status ?? 'scheduled',
            'replaced_reason' => $s->replaced_reason,
            'replaced_note' => $s->replaced_note,
            'replaced_by' => $s->replaced_by_employee_id ? ($names[$s->replaced_by_employee_id] ?? null) : null,
            'covers' => $s->replaces_shift_id ? $this->coveredName($shifts, $s->replaces_shift_id, $names) : null,
            'assigned_by' => $s->assigned_by,
        ])->values();

        $staff = $people->map(function (EmployeeInfo $p) use ($days, $byPersonDay, $leave) {
            $restDays = $this->calculator->restDays($p);
            $availability = [];
            $weekHours = [];
            foreach ($days as $date) {
                $shift = $byPersonDay->get($p->id . '|' . $date);
                $availability[$date] = $this->dayStatus($p, $date, $shift, $restDays, isset($leave[$p->id][$date]));
                $expected = $this->calculator->expected($p, $date, $shift);
                $week = Carbon::parse($date)->startOfWeek()->toDateString();
                $weekHours[$week] = round(($weekHours[$week] ?? 0) + ($expected ? self::hours($expected['start'], $expected['end']) : 0), 1);
            }

            return [
                'id' => $p->id,
                'name' => $p->full_name,
                'position' => $p->position,
                'department' => $p->department,
                'avatar' => $p->avatar_url,
                'usual_hours' => $p->shift_start && $p->shift_end ? AttendanceCalculator::hm($p->shift_start) . '–' . AttendanceCalculator::hm($p->shift_end) : null,
                'rest_days' => $restDays,
                'availability' => $availability,
                'week_hours' => $weekHours,
            ];
        })->values();

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'days' => $days,
            'templates' => $templates->map(fn ($t) => $this->formatTemplate($t))->values(),
            'assignments' => $assignments,
            'staff' => $staff,
            'max_week_hours' => self::MAX_WEEK_HOURS,
        ];
    }

    /** free | usual | shift | custom | off | rest | leave | replaced */
    private function dayStatus(EmployeeInfo $p, string $date, ?StaffShift $shift, array $restDays, bool $onLeave): array
    {
        if ($onLeave) {
            return ['status' => 'leave'];
        }
        if ($p->date_hired && $date < Carbon::parse($p->date_hired)->toDateString()) {
            return ['status' => 'not_hired'];
        }
        if ($shift) {
            if ($shift->status === 'replaced') {
                return ['status' => 'replaced', 'shift_id' => $shift->id];
            }
            if ($shift->is_off) {
                return ['status' => 'off', 'shift_id' => $shift->id];
            }

            return ['status' => $shift->shift_template_id ? 'shift' : 'custom', 'shift_id' => $shift->id, 'template_id' => $shift->shift_template_id,
                'start' => AttendanceCalculator::hm($shift->start_time), 'end' => AttendanceCalculator::hm($shift->end_time)];
        }
        if (in_array(Carbon::parse($date)->format('l'), $restDays, true)) {
            return ['status' => 'rest'];
        }
        if ($p->shift_start && $p->shift_end) {
            return ['status' => 'usual', 'start' => AttendanceCalculator::hm($p->shift_start), 'end' => AttendanceCalculator::hm($p->shift_end)];
        }

        return ['status' => 'free'];
    }

    // ── Assigning ──────────────────────────────────────────────────────────

    /**
     * Put someone on a shift, custom hours, or a day off on one date.
     * $what: ['template_id' => id] | ['start' => 'H:i', 'end' => 'H:i', 'label' => ?] | ['day_off' => true]
     */
    public function assign(int $ownerId, int $employeeId, string $date, array $what, array $confirm, $actor): StaffShift
    {
        $employee = $this->employee($ownerId, $employeeId);
        $date = Carbon::parse($date)->toDateString();
        [$template, $start, $end, $off, $label] = $this->resolveWhat($ownerId, $what);

        return DB::transaction(function () use ($ownerId, $employee, $date, $template, $start, $end, $off, $label, $confirm, $actor) {
            $existing = StaffShift::where('owner_id', $ownerId)->where('employee_id', $employee->id)->whereDate('shift_date', $date)->lockForUpdate()->first();
            if (! $off) {
                $this->guardWorkDay($ownerId, $employee, $date, $existing, $template, $confirm);
            } else {
                $this->guardHardStops($ownerId, $employee, $date, allowLeave: true);
                if ($existing && ! $existing->is_off && ! in_array('move', $confirm, true) && $existing->status !== 'replaced') {
                    throw new ScheduleConflict("{$employee->full_name} is on {$this->shiftName($existing)} that day. Give them the day off instead?", 'move');
                }
            }
            if ($existing?->status === 'replaced') {
                throw ValidationException::withMessages(['employee_id' => "{$employee->full_name}'s shift that day was covered by someone else. Undo the replacement first."]);
            }

            $this->releaseCoveredShift($ownerId, $existing);
            $shift = $existing ?? new StaffShift(['owner_id' => $ownerId, 'employee_id' => $employee->id, 'shift_date' => $date]);
            $shift->fill([
                'owner_id' => $ownerId,
                'shift_template_id' => $template?->id,
                'start_time' => $off ? null : $start,
                'end_time' => $off ? null : $end,
                'is_off' => $off,
                'label' => $off ? null : ($label ?? $template?->name),
                'status' => 'scheduled',
                'replaced_reason' => null,
                'replaced_note' => null,
                'replaced_by_employee_id' => null,
                'replaces_shift_id' => null,
                'assigned_by' => $this->name($actor),
            ])->save();

            return $shift;
        });
    }

    /** Someone else covers a shift (John didn't show up; Jane takes it). */
    public function replace(int $ownerId, int $shiftId, int $replacementId, string $reason, ?string $note, array $confirm, $actor): array
    {
        return DB::transaction(function () use ($ownerId, $shiftId, $replacementId, $reason, $note, $confirm, $actor) {
            $original = StaffShift::where('owner_id', $ownerId)->with('employee')->lockForUpdate()->findOrFail($shiftId);
            if ($original->is_off || $original->status === 'replaced') {
                throw ValidationException::withMessages(['shift' => 'Only a scheduled shift can be covered.']);
            }
            if ($replacementId === $original->employee_id) {
                throw ValidationException::withMessages(['employee_id' => 'Choose someone else to cover the shift.']);
            }
            $date = $original->shift_date->toDateString();
            $absentee = $original->employee?->full_name ?? 'They';
            $timedIn = EmployeeAttendance::forOwner($ownerId)->where('employee_id', $original->employee_id)->whereDate('attendance_date', $date)->exists();
            if ($timedIn && $reason === 'no_show') {
                throw ValidationException::withMessages(['reason' => "{$absentee} already timed in that day, so it isn't a no-show."]);
            }

            $replacement = $this->employee($ownerId, $replacementId);
            $existing = StaffShift::where('owner_id', $ownerId)->where('employee_id', $replacement->id)->whereDate('shift_date', $date)->lockForUpdate()->first();
            $this->guardWorkDay($ownerId, $replacement, $date, $existing, $original->template, $confirm);
            if ($existing?->status === 'replaced') {
                throw ValidationException::withMessages(['employee_id' => "{$replacement->full_name}'s own shift that day was covered by someone else."]);
            }

            $this->releaseCoveredShift($ownerId, $existing);
            $cover = $existing ?? new StaffShift(['owner_id' => $ownerId, 'employee_id' => $replacement->id, 'shift_date' => $date]);
            $cover->fill([
                'owner_id' => $ownerId,
                'shift_template_id' => $original->shift_template_id,
                'start_time' => $original->start_time,
                'end_time' => $original->end_time,
                'is_off' => false,
                'label' => $original->label,
                'status' => 'scheduled',
                'replaces_shift_id' => $original->id,
                'replaced_reason' => null,
                'replaced_by_employee_id' => null,
                'assigned_by' => $this->name($actor),
            ])->save();

            $original->fill([
                'status' => 'replaced',
                'replaced_reason' => $reason,
                'replaced_note' => $note ? trim($note) : null,
                'replaced_by_employee_id' => $replacement->id,
            ])->save();

            return ['original' => $original, 'cover' => $cover, 'message' => "{$replacement->full_name} covers {$absentee}'s shift."];
        });
    }

    /** Put the original person back on their shift; the cover goes back to what they had (their usual hours). */
    public function undoReplace(int $ownerId, int $shiftId): void
    {
        DB::transaction(function () use ($ownerId, $shiftId) {
            $original = StaffShift::where('owner_id', $ownerId)->lockForUpdate()->findOrFail($shiftId);
            if ($original->status !== 'replaced') {
                throw ValidationException::withMessages(['shift' => 'This shift wasn\'t covered by anyone.']);
            }
            StaffShift::where('owner_id', $ownerId)->where('replaces_shift_id', $original->id)->delete();
            $original->fill(['status' => 'scheduled', 'replaced_reason' => null, 'replaced_note' => null, 'replaced_by_employee_id' => null])->save();
        });
    }

    /** Take someone off the board for a day: back to their usual hours. */
    public function remove(int $ownerId, int $shiftId): void
    {
        DB::transaction(function () use ($ownerId, $shiftId) {
            $shift = StaffShift::where('owner_id', $ownerId)->lockForUpdate()->findOrFail($shiftId);
            if ($shift->status === 'replaced') {
                throw ValidationException::withMessages(['shift' => 'Undo the replacement instead.']);
            }
            $this->releaseCoveredShift($ownerId, $shift);
            $shift->delete();
        });
    }

    /** When a cover moves elsewhere, the shift goes back to the person they were covering. */
    private function releaseCoveredShift(int $ownerId, ?StaffShift $cover): void
    {
        if ($cover?->replaces_shift_id) {
            StaffShift::where('owner_id', $ownerId)->whereKey($cover->replaces_shift_id)
                ->update(['status' => 'scheduled', 'replaced_reason' => null, 'replaced_note' => null, 'replaced_by_employee_id' => null]);
        }
    }

    /**
     * Link people to a shift over a range: they're put on it every day it runs. Days they're
     * resting, on leave or already scheduled are skipped and reported, never overwritten.
     */
    public function link(int $ownerId, int $templateId, array $employeeIds, Carbon $from, Carbon $to, $actor): array
    {
        $template = ShiftTemplate::where('owner_id', $ownerId)->whereNull('archived_at')->findOrFail($templateId);
        $this->guardRange($from, $to);
        $leave = $this->leaveDays($ownerId, $from, $to);
        $added = 0;
        $skipped = [];

        foreach (array_unique($employeeIds) as $employeeId) {
            $employee = $this->employee($ownerId, (int) $employeeId);
            $restDays = $this->calculator->restDays($employee);
            $existing = StaffShift::where('owner_id', $ownerId)->where('employee_id', $employee->id)
                ->onDays($from->toDateString(), $to->toDateString())->get()
                ->keyBy(fn ($s) => $s->shift_date->toDateString());

            foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
                $date = $day->toDateString();
                if (! $template->runsOn($date)) {
                    continue;
                }
                $why = match (true) {
                    isset($leave[$employee->id][$date]) => 'on leave',
                    $employee->date_hired && $date < Carbon::parse($employee->date_hired)->toDateString() => 'not hired yet',
                    $existing->has($date) && (int) $existing[$date]->shift_template_id === $template->id => null,
                    $existing->has($date) => 'already scheduled',
                    in_array($day->format('l'), $restDays, true) => 'rest day',
                    default => false,
                };
                if ($why === null) {
                    continue; // already on this shift
                }
                if ($why) {
                    $skipped[] = ['employee' => $employee->full_name, 'date' => $date, 'why' => $why];
                    continue;
                }
                StaffShift::create([
                    'owner_id' => $ownerId, 'employee_id' => $employee->id, 'shift_date' => $date,
                    'shift_template_id' => $template->id, 'start_time' => $template->start_time, 'end_time' => $template->end_time,
                    'is_off' => false, 'label' => $template->name, 'status' => 'scheduled', 'assigned_by' => $this->name($actor),
                ]);
                $added++;
            }
        }

        return ['added' => $added, 'skipped' => $skipped];
    }

    /** Repeat last week's shifts (not one-off replacements) in this week, without overwriting anything. */
    public function copyWeek(int $ownerId, Carbon $weekStart, $actor): array
    {
        $start = $weekStart->copy()->startOfWeek();
        $previous = StaffShift::where('owner_id', $ownerId)
            ->onDays($start->copy()->subWeek()->toDateString(), $start->copy()->subDay()->toDateString())
            ->whereNull('replaces_shift_id')
            ->get();
        $leave = $this->leaveDays($ownerId, $start, $start->copy()->endOfWeek());
        $copied = 0;
        $skipped = [];
        foreach ($previous as $s) {
            $date = $s->shift_date->copy()->addWeek()->toDateString();
            $employee = EmployeeInfo::forOwner($ownerId)->find($s->employee_id);
            if (! $employee || ! $this->calculator->isActive($employee)) {
                continue;
            }
            if (isset($leave[$employee->id][$date]) && ! $s->is_off) {
                $skipped[] = ['employee' => $employee->full_name, 'date' => $date, 'why' => 'on leave'];
                continue;
            }
            $made = StaffShift::firstOrCreate(
                ['employee_id' => $s->employee_id, 'shift_date' => $date],
                ['owner_id' => $ownerId, 'shift_template_id' => $s->shift_template_id, 'is_off' => $s->is_off, 'start_time' => $s->start_time,
                    'end_time' => $s->end_time, 'label' => $s->label, 'note' => $s->note, 'status' => 'scheduled', 'assigned_by' => $this->name($actor)],
            );
            $copied += $made->wasRecentlyCreated ? 1 : 0;
        }

        return ['copied' => $copied, 'skipped' => $skipped];
    }

    // ── Shift templates ────────────────────────────────────────────────────

    public function saveTemplate(int $ownerId, array $data, ?ShiftTemplate $template, bool $updateFuture): ShiftTemplate
    {
        return DB::transaction(function () use ($ownerId, $data, $template, $updateFuture) {
            $template ??= new ShiftTemplate(['owner_id' => $ownerId]);
            $template->fill([
                'owner_id' => $ownerId,
                'name' => trim($data['name']),
                'start_time' => $data['start_time'] . ':00',
                'end_time' => $data['end_time'] . ':00',
                'color' => $data['color'] ?? '#48bb78',
                'headcount' => $data['headcount'],
                'days' => array_values(array_intersect(AttendanceCalculator::DAYS, $data['days'])),
                'note' => $data['note'] ?? null,
            ])->save();

            if ($updateFuture) {
                StaffShift::where('owner_id', $ownerId)->where('shift_template_id', $template->id)
                    ->whereDate('shift_date', '>=', now()->toDateString())
                    ->update(['start_time' => $template->start_time, 'end_time' => $template->end_time, 'label' => $template->name]);
            }

            return $template;
        });
    }

    /** Retire a shift: upcoming days on it are cleared (people go back to usual hours); past days keep it. */
    public function archiveTemplate(int $ownerId, int $id): int
    {
        return DB::transaction(function () use ($ownerId, $id) {
            $template = ShiftTemplate::where('owner_id', $ownerId)->whereNull('archived_at')->findOrFail($id);
            $cleared = StaffShift::where('owner_id', $ownerId)->where('shift_template_id', $template->id)
                ->whereDate('shift_date', '>=', now()->toDateString())->delete();
            $template->forceFill(['archived_at' => now()])->save();

            return $cleared;
        });
    }

    public function formatTemplate(ShiftTemplate $t): array
    {
        return [
            'id' => $t->id,
            'name' => $t->name,
            'start' => AttendanceCalculator::hm($t->start_time),
            'end' => AttendanceCalculator::hm($t->end_time),
            'hours' => self::hours($t->start_time, $t->end_time),
            'color' => $t->color,
            'headcount' => $t->headcount,
            'days' => $t->days ?? [],
            'note' => $t->note,
        ];
    }

    // ── Rules ──────────────────────────────────────────────────────────────

    private function guardHardStops(int $ownerId, EmployeeInfo $employee, string $date, bool $allowLeave = false): void
    {
        if (! $this->calculator->isActive($employee)) {
            throw ValidationException::withMessages(['employee_id' => "{$employee->full_name} is marked inactive."]);
        }
        if ($employee->date_hired && $date < Carbon::parse($employee->date_hired)->toDateString()) {
            throw ValidationException::withMessages(['employee_id' => "{$employee->full_name} wasn't hired yet on that day."]);
        }
        if (! $allowLeave && isset($this->leaveDays($ownerId, Carbon::parse($date), Carbon::parse($date))[$employee->id][$date])) {
            throw ValidationException::withMessages(['employee_id' => "{$employee->full_name} is on approved leave that day."]);
        }
    }

    private function guardWorkDay(int $ownerId, EmployeeInfo $employee, string $date, ?StaffShift $existing, ?ShiftTemplate $template, array $confirm): void
    {
        $this->guardHardStops($ownerId, $employee, $date);
        if ($template && ! $template->runsOn($date) && ! in_array('off_day', $confirm, true)) {
            throw new ScheduleConflict("{$template->name} doesn't normally run on " . Carbon::parse($date)->format('l') . '. Add it anyway?', 'off_day');
        }
        if ($existing && ! $existing->is_off && $existing->status !== 'replaced') {
            if ($template && (int) $existing->shift_template_id === $template->id) {
                throw ValidationException::withMessages(['employee_id' => "{$employee->full_name} is already on {$template->name} that day."]);
            }
            if (! in_array('move', $confirm, true)) {
                throw new ScheduleConflict("{$employee->full_name} is already on {$this->shiftName($existing)} that day. Move them?", 'move', ['from' => $this->shiftName($existing)]);
            }
        }
        $resting = ($existing && $existing->is_off) || (! $existing && in_array(Carbon::parse($date)->format('l'), $this->calculator->restDays($employee), true));
        if ($resting && ! in_array('allow_rest_day', $confirm, true)) {
            throw new ScheduleConflict("It's {$employee->full_name}'s rest day. Schedule them anyway?", 'allow_rest_day');
        }
    }

    private function resolveWhat(int $ownerId, array $what): array
    {
        if (! empty($what['day_off'])) {
            return [null, null, null, true, null];
        }
        if (! empty($what['template_id'])) {
            $t = ShiftTemplate::where('owner_id', $ownerId)->whereNull('archived_at')->find($what['template_id']);
            if (! $t) {
                throw ValidationException::withMessages(['template_id' => 'That shift no longer exists.']);
            }

            return [$t, $t->start_time, $t->end_time, false, null];
        }
        if (empty($what['start']) || empty($what['end'])) {
            throw ValidationException::withMessages(['start' => 'Pick a shift or enter the hours.']);
        }
        if ($what['end'] <= $what['start']) {
            throw ValidationException::withMessages(['end' => 'The shift must end after it starts.']);
        }

        return [null, $what['start'] . ':00', $what['end'] . ':00', false, $what['label'] ?? null];
    }

    private function guardRange(Carbon $from, Carbon $to): void
    {
        if ($to->lt($from) || $from->diffInDays($to) > self::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['to' => 'Choose a range of up to two months.']);
        }
    }

    private function employee(int $ownerId, int $id): EmployeeInfo
    {
        $employee = EmployeeInfo::forOwner($ownerId)->find($id);
        if (! $employee) {
            throw ValidationException::withMessages(['employee_id' => 'That person isn\'t at this shop.']);
        }

        return $employee;
    }

    /** Approved leave, as [employeeId][Y-m-d] => true */
    public function leaveDays(int $ownerId, Carbon $from, Carbon $to): array
    {
        if (! ($this->hasLeaves ??= Schema::hasTable('employee_leaves'))) {
            return [];
        }
        $out = [];
        $leaves = EmployeeLeave::where('owner_id', $ownerId)->where('status', 'approved')
            ->whereDate('start_date', '<=', $to->toDateString())->whereDate('end_date', '>=', $from->toDateString())
            ->get(['employee_id', 'start_date', 'end_date']);
        foreach ($leaves as $leave) {
            $start = Carbon::parse($leave->start_date)->max($from->copy()->startOfDay());
            $end = Carbon::parse($leave->end_date)->min($to->copy()->startOfDay());
            foreach (CarbonPeriod::create($start->startOfDay(), $end->startOfDay()) as $d) {
                $out[$leave->employee_id][$d->toDateString()] = true;
            }
        }

        return $out;
    }

    private function shiftName(StaffShift $s): string
    {
        if ($s->shift_template_id) {
            return $s->template?->name ?? $s->label ?? 'another shift';
        }

        return 'custom hours (' . AttendanceCalculator::hm($s->start_time) . '–' . AttendanceCalculator::hm($s->end_time) . ')';
    }

    private function coveredName(Collection $shifts, int $id, $names): ?string
    {
        $covered = $shifts->firstWhere('id', $id) ?? StaffShift::find($id);

        return $covered ? ($names[$covered->employee_id] ?? null) : null;
    }

    public static function hours($start, $end): float
    {
        [$sh, $sm] = array_map('intval', explode(':', (string) $start));
        [$eh, $em] = array_map('intval', explode(':', (string) $end));

        return round(max(0, ($eh * 60 + $em) - ($sh * 60 + $sm)) / 60, 1);
    }

    private function name($actor): string
    {
        return $actor instanceof Employee ? ($actor->name ?: 'HR') : 'Shop owner';
    }
}
