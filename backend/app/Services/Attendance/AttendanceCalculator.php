<?php

namespace App\Services\Attendance;

use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use App\Models\EmployeeLeave;
use App\Models\StaffShift;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * What a day of attendance means: the hours someone should have worked (their schedule shift,
 * else their usual hours unless it's a rest day), and whether they were late, left early
 * (undertime), or didn't come (absent). Used by the vendor portal and the ERP alike.
 */
class AttendanceCalculator
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    /** Longest report range, to keep the work small on the server */
    public const MAX_RANGE_DAYS = 92;

    private ?bool $hasShifts = null; // per instance: one request, no stale answer across tests
    private ?bool $hasLeaves = null; // per instance: one request, no stale answer across tests

    public function __construct(private AttendanceLocationService $location) {}

    /** Schedule shifts in a range, keyed "employeeId|Y-m-d". */
    public function shiftsBetween(int $ownerId, Carbon $from, Carbon $to): Collection
    {
        if (! ($this->hasShifts ??= Schema::hasTable('staff_shifts'))) {
            return collect();
        }

        return StaffShift::where('owner_id', $ownerId)
            ->onDays($from->toDateString(), $to->toDateString())->get()
            ->keyBy(fn ($s) => $s->employee_id . '|' . $s->shift_date->toDateString());
    }

    /**
     * The hours someone should work on a day, or null when they're off. When someone covered
     * their shift, they're excused — unless they simply didn't show up.
     */
    public function expected(EmployeeInfo $p, string $date, ?StaffShift $shift): ?array
    {
        if ($shift) {
            if ($shift->is_off || ($shift->status === 'replaced' && $shift->replaced_reason !== 'no_show')) {
                return null;
            }

            return ['start' => self::hm($shift->start_time), 'end' => self::hm($shift->end_time)];
        }
        if (! $p->shift_start || ! $p->shift_end) {
            return null;
        }
        if (in_array(Carbon::parse($date)->format('l'), $this->restDays($p), true)) {
            return null;
        }

        return ['start' => self::hm($p->shift_start), 'end' => self::hm($p->shift_end)];
    }

    /** "Sunday, Saturday" → ["Saturday", "Sunday"] in week order */
    public function restDays(EmployeeInfo $p): array
    {
        $raw = is_array($p->rest_days) ? $p->rest_days : preg_split('/[\s,;]+/', (string) $p->rest_days, -1, PREG_SPLIT_NO_EMPTY);
        $picked = collect($raw)->map(fn ($d) => ucfirst(strtolower(trim($d))))->all();

        return array_values(array_filter(self::DAYS, fn ($d) => in_array($d, $picked, true) || in_array(substr($d, 0, 3), $picked, true)));
    }

    public function isActive(EmployeeInfo $p): bool
    {
        return ! in_array(strtolower((string) $p->employment_status), ['resigned', 'terminated', 'inactive', 'resign'], true);
    }

    /** The hours someone should work on a given day, looking up their schedule shift. */
    public function expectedOn(int $ownerId, EmployeeInfo $p, string $date): ?array
    {
        $shift = $this->shiftsBetween($ownerId, Carbon::parse($date), Carbon::parse($date))->get($p->id . '|' . $date);

        return $this->expected($p, $date, $shift);
    }

    /** Minutes worked past the scheduled end, once it reaches the shop's minimum; otherwise null. */
    public function overtimeMinutes(EmployeeAttendance $a, ?array $expected, int $minimum): ?int
    {
        if (! $expected || ! $a->time_out) {
            return null;
        }
        $date = Carbon::parse($a->attendance_date)->toDateString();
        $mins = (int) Carbon::parse("{$date} {$expected['end']}")->diffInMinutes(Carbon::parse("{$date} {$a->time_out}"), false);

        return $mins >= max(1, $minimum) ? $mins : null;
    }

    /**
     * Work out a record's overtime after its times change. New or changed overtime waits for
     * approval, unless $approvedBy (the shop owner editing it themselves) settles it now.
     * Doesn't save.
     */
    public function applyOvertime(int $ownerId, EmployeeAttendance $a, EmployeeInfo $p, ?string $approvedBy = null): void
    {
        $settings = $this->location->settings($ownerId);
        $date = Carbon::parse($a->attendance_date)->toDateString();
        $minutes = $this->overtimeMinutes($a, $this->expectedOn($ownerId, $p, $date), $settings->overtimeMinimum());

        if ($minutes === null) {
            $a->fill(['overtime_minutes' => null, 'overtime_status' => null, 'overtime_approved_minutes' => null,
                'overtime_reviewed_by' => null, 'overtime_reviewed_at' => null, 'overtime_note' => null]);

            return;
        }
        if ($a->overtime_minutes === $minutes && $a->overtime_status && $a->overtime_status !== 'pending' && ! $approvedBy) {
            return; // unchanged; keep the decision already made
        }
        // Overtime asked for and approved ahead of time covers the day, up to the approved minutes
        if (! $approvedBy && \Illuminate\Support\Facades\Schema::hasTable('overtime_requests')) {
            $request = \App\Models\OvertimeRequest::where('owner_id', $ownerId)->where('employee_info_id', $p->id)
                ->whereDate('date', $date)->where('status', 'approved')->first();
            if ($request && $minutes <= $request->minutes) {
                $a->fill(['overtime_minutes' => $minutes, 'overtime_status' => 'approved', 'overtime_approved_minutes' => $minutes,
                    'overtime_reviewed_by' => "Approved ahead by {$request->reviewer_name}", 'overtime_reviewed_at' => now(), 'overtime_note' => null]);

                return;
            }
            if ($request) {
                $a->fill(['overtime_minutes' => $minutes, 'overtime_status' => 'pending', 'overtime_approved_minutes' => null,
                    'overtime_reviewed_by' => null, 'overtime_reviewed_at' => null,
                    'overtime_note' => "{$request->minutes} min were approved ahead; they stayed {$minutes} min."]);

                return;
            }
        }
        $a->fill($approvedBy
            ? ['overtime_minutes' => $minutes, 'overtime_status' => 'approved', 'overtime_approved_minutes' => $minutes,
                'overtime_reviewed_by' => $approvedBy, 'overtime_reviewed_at' => now(), 'overtime_note' => null]
            : ['overtime_minutes' => $minutes, 'overtime_status' => 'pending', 'overtime_approved_minutes' => null,
                'overtime_reviewed_by' => null, 'overtime_reviewed_at' => null, 'overtime_note' => null]);
    }

    /** Minutes late (past the grace period) and minutes left early, for one record. */
    public function facts(EmployeeAttendance $a, ?array $expected, int $graceMinutes): array
    {
        $date = Carbon::parse($a->attendance_date)->toDateString();
        $late = null;
        $undertime = null;
        if ($expected && $a->time_in) {
            $mins = (int) Carbon::parse("{$date} {$expected['start']}")->diffInMinutes(Carbon::parse("{$date} {$a->time_in}"), false);
            $late = $mins > $graceMinutes ? $mins : 0;
        }
        if ($expected && $a->time_out) {
            $end = Carbon::parse("{$date} {$expected['end']}");
            $out = Carbon::parse("{$date} {$a->time_out}");
            $undertime = $out->lessThan($end) ? (int) $out->diffInMinutes($end) : 0;
        }

        return ['late_minutes' => $late, 'undertime_minutes' => $undertime];
    }

    /**
     * Every scheduled or recorded day in a range, per person, plus per-person totals.
     *
     * @return array{days: array, people: array, totals: array}
     */
    public function report(int $ownerId, Carbon $from, Carbon $to, ?int $employeeId = null): array
    {
        $grace = $this->location->settings($ownerId)->late_grace_minutes;
        $profiles = EmployeeInfo::forOwner($ownerId)
            ->when($employeeId, fn ($q) => $q->whereKey($employeeId))
            ->orderBy('first_name')->get();
        $records = EmployeeAttendance::forOwner($ownerId)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
            ->when($employeeId, fn ($q) => $q->where('employee_id', $employeeId))
            ->get()
            ->keyBy(fn ($a) => $a->employee_id . '|' . Carbon::parse($a->attendance_date)->toDateString());
        $shifts = $this->shiftsBetween($ownerId, $from, $to);
        $leaveDays = $this->leaveDays($ownerId, $from, $to);
        $now = now();
        $today = $now->toDateString();

        $days = [];
        $people = [];
        foreach ($profiles as $p) {
            $hired = $p->date_hired ? Carbon::parse($p->date_hired)->toDateString() : null;
            $sum = [
                'employee_id' => $p->id, 'name' => $p->full_name, 'position' => $p->position,
                'scheduled' => 0, 'present' => 0, 'absent' => 0, 'on_leave' => 0, 'late' => 0, 'late_minutes' => 0,
                'undertime' => 0, 'undertime_minutes' => 0, 'hours' => 0.0, 'needs_review' => 0, 'missing_out' => 0,
                'overtime_approved_minutes' => 0, 'overtime_pending_minutes' => 0,
            ];

            foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
                $date = $day->toDateString();
                if ($date > $today) {
                    break;
                }
                $expected = $this->expected($p, $date, $shifts->get($p->id . '|' . $date));
                $record = $records->get($p->id . '|' . $date);
                $onLeave = isset($leaveDays[$p->id][$date]);
                if (! $record && ! $expected) {
                    continue; // a day off with nothing recorded
                }
                if (! $record && ($hired && $date < $hired || ! $this->isActive($p))) {
                    continue;
                }

                $row = [
                    'employee_id' => $p->id, 'employee' => $p->full_name, 'date' => $date,
                    'expected' => $expected ? "{$expected['start']}–{$expected['end']}" : null,
                    'record_id' => $record?->id, 'time_in' => self::hm($record?->time_in), 'time_out' => self::hm($record?->time_out),
                    'hours' => $record?->total_hours !== null ? (float) $record->total_hours : null,
                    'late_minutes' => null, 'undertime_minutes' => null,
                    'review_status' => $record?->review_status, 'review_reasons' => $record?->review_reasons ?? [],
                    'source' => $record?->source,
                    'overtime_minutes' => $record?->overtime_minutes,
                    'overtime_status' => $record?->overtime_status,
                    'overtime_approved_minutes' => $record?->overtime_approved_minutes,
                ];
                if ($expected) {
                    $sum['scheduled']++;
                }

                $rejected = $record?->review_status === 'rejected';
                if ($record && ! $rejected) {
                    $facts = $this->facts($record, $expected, $grace);
                    $row = array_merge($row, $facts);
                    $row['status'] = $record->time_out ? 'present' : ($date < $today ? 'missing_out' : 'in');
                    $sum['present']++;
                    $sum['hours'] += (float) ($record->total_hours ?? 0);
                    if ($facts['late_minutes']) {
                        $sum['late']++;
                        $sum['late_minutes'] += $facts['late_minutes'];
                    }
                    if ($facts['undertime_minutes']) {
                        $sum['undertime']++;
                        $sum['undertime_minutes'] += $facts['undertime_minutes'];
                    }
                    if ($row['status'] === 'missing_out') {
                        $sum['missing_out']++;
                    }
                    if ($record->review_status === 'pending') {
                        $sum['needs_review']++;
                    }
                    if ($record->overtime_status === 'approved') {
                        $sum['overtime_approved_minutes'] += (int) $record->overtime_approved_minutes;
                    } elseif ($record->overtime_status === 'pending') {
                        $sum['overtime_pending_minutes'] += (int) $record->overtime_minutes;
                    }
                } elseif ($onLeave) {
                    $row['status'] = 'on_leave';
                    $sum['on_leave']++;
                } elseif ($expected && ($date < $today || $now->format('H:i') > $expected['end'])) {
                    $row['status'] = 'absent';
                    $sum['absent']++;
                } else {
                    $row['status'] = $rejected ? 'rejected' : 'not_in_yet';
                }
                if ($rejected) {
                    $row['status'] = 'rejected';
                }
                $days[] = $row;
            }

            $sum['hours'] = round($sum['hours'], 2);
            $people[] = $sum;
        }

        usort($days, fn ($a, $b) => [$b['date'], $a['employee']] <=> [$a['date'], $b['employee']]);
        $totals = [];
        foreach (['scheduled', 'present', 'absent', 'on_leave', 'late', 'late_minutes', 'undertime', 'undertime_minutes', 'needs_review', 'missing_out', 'overtime_approved_minutes', 'overtime_pending_minutes'] as $k) {
            $totals[$k] = array_sum(array_column($people, $k));
        }
        $totals['hours'] = round(array_sum(array_column($people, 'hours')), 2);

        return ['days' => $days, 'people' => $people, 'totals' => $totals, 'grace_minutes' => $grace];
    }

    /** Approved leave, as [employeeId][Y-m-d] => true */
    private function leaveDays(int $ownerId, Carbon $from, Carbon $to): array
    {
        if (! ($this->hasLeaves ??= Schema::hasTable('employee_leaves'))) {
            return [];
        }
        $out = [];
        $leaves = EmployeeLeave::where('owner_id', $ownerId)->where('status', 'approved')
            ->whereDate('start_date', '<=', $to->toDateString())
            ->whereDate('end_date', '>=', $from->toDateString())
            ->get(['employee_id', 'start_date', 'end_date']);
        foreach ($leaves as $leave) {
            $start = Carbon::parse($leave->start_date)->max($from);
            $end = Carbon::parse($leave->end_date)->min($to);
            foreach (CarbonPeriod::create($start->startOfDay(), $end->startOfDay()) as $d) {
                $out[$leave->employee_id][$d->toDateString()] = true;
            }
        }

        return $out;
    }

    public static function hm($time): ?string
    {
        return $time ? substr((string) $time, 0, 5) : null;
    }
}
