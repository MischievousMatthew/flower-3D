<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use App\Models\EmployeeLeave;
use App\Models\OvertimeRequest;
use App\Models\Payroll;
use App\Models\StaffShift;
use App\Traits\ScopesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Schema;

/**
 * GET /hr/dashboard — the HR overview in one small payload: who works here,
 * who is in today, what is waiting for a decision, and the past week.
 * It only needs the HR dashboard permission, not each module's.
 */
class HrDashboardController extends Controller
{
    use ScopesOwner;

    private const GONE = ['inactive', 'resigned', 'terminated', 'resign'];

    public function show(): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $today = Carbon::today();
        $weekStart = $today->copy()->subDays(6);

        $people = EmployeeInfo::forOwner($ownerId)->get(['id', 'first_name', 'middle_name', 'last_name', 'avatar', 'position', 'employment_status']);
        $active = $people->filter(fn ($p) => ! in_array(strtolower((string) $p->employment_status), self::GONE, true))->values();
        $byId = $people->keyBy('id');

        // A rejected scan doesn't count as being there
        $scans = EmployeeAttendance::forOwner($ownerId)
            ->whereDate('attendance_date', '>=', $weekStart->toDateString())
            ->whereNotNull('time_in')
            ->where(fn ($q) => $q->whereNull('review_status')->orWhere('review_status', '!=', 'rejected'))
            ->get(['id', 'employee_id', 'attendance_date', 'time_in', 'time_out', 'review_status']);
        $todayScans = $scans->filter(fn ($s) => $s->attendance_date->isSameDay($today));

        $leaveToday = EmployeeLeave::forOwner($ownerId)->where('status', 'approved')
            ->whereDate('start_date', '<=', $today->toDateString())->whereDate('end_date', '>=', $today->toDateString())
            ->get(['id', 'employee_id', 'leave_type', 'start_date', 'end_date']);
        $upcoming = EmployeeLeave::forOwner($ownerId)->where('status', 'approved')
            ->whereDate('start_date', '>', $today->toDateString())->whereDate('start_date', '<=', $today->copy()->addDays(14)->toDateString())
            ->orderBy('start_date')->limit(5)
            ->get(['id', 'employee_id', 'leave_type', 'start_date', 'end_date']);

        $present = $todayScans->pluck('employee_id')->unique();
        $onLeave = $leaveToday->pluck('employee_id')->unique()->diff($present);
        $headcount = $active->count();

        return response()->json(['success' => true, 'data' => [
            'date' => $today->toDateString(),
            'headcount' => $headcount,
            'present' => $present->count(),
            'clocked_in' => $todayScans->filter(fn ($s) => ! $s->time_out)->pluck('employee_id')->unique()->count(),
            'on_leave' => $onLeave->count(),
            'not_in' => max(0, $headcount - $present->count() - $onLeave->count()),
            'scheduled' => $this->scheduledToday($ownerId, $today),
            'waiting' => [
                'leave' => EmployeeLeave::forOwner($ownerId)->where('status', 'pending')->count(),
                'overtime' => OvertimeRequest::where('owner_id', $ownerId)->where('status', 'pending')->count(),
                'attendance' => EmployeeAttendance::forOwner($ownerId)->where('review_status', 'pending')->count(),
                'payroll' => Payroll::forOwner($ownerId)->where('status', 'pending')->count(),
            ],
            'week' => collect(range(0, 6))->map(function ($i) use ($weekStart, $scans) {
                $day = $weekStart->copy()->addDays($i);

                return [
                    'date' => $day->toDateString(),
                    'present' => $scans->filter(fn ($s) => $s->attendance_date->isSameDay($day))->pluck('employee_id')->unique()->count(),
                ];
            })->values(),
            'activity' => $todayScans->sortByDesc(fn ($s) => $s->time_out ?: $s->time_in)->take(8)->map(fn ($s) => [
                'id' => $s->id,
                'name' => $this->nameOf($byId->get($s->employee_id)),
                'avatar' => $byId->get($s->employee_id)?->avatar_url,
                'time_in' => $s->time_in,
                'time_out' => $s->time_out,
                'flagged' => $s->review_status === 'pending',
            ])->values(),
            'leave_today' => $leaveToday->take(6)->map(fn ($l) => $this->leaveRow($l, $byId))->values(),
            'upcoming_leave' => $upcoming->map(fn ($l) => $this->leaveRow($l, $byId))->values(),
            'payroll' => $this->lastPayroll($ownerId),
        ]]);
    }

    /** People with a working shift today; null when the shop doesn't plan shifts */
    private function scheduledToday(int $ownerId, Carbon $today): ?int
    {
        if (! Schema::hasTable('staff_shifts')) {
            return null;
        }
        $shifts = StaffShift::where('owner_id', $ownerId)->whereDate('shift_date', $today->toDateString());
        if (! (clone $shifts)->exists()) {
            return null;
        }

        return $shifts->where('is_off', false)
            ->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'replaced'))
            ->distinct('employee_id')->count('employee_id');
    }

    /** The most recent pay period: when it ended, how many were paid and the total */
    private function lastPayroll(int $ownerId): ?array
    {
        $last = Payroll::forOwner($ownerId)->whereIn('status', ['approved', 'paid'])->orderByDesc('period_end')->first(['period_start', 'period_end']);
        if (! $last) {
            return null;
        }
        $period = Payroll::forOwner($ownerId)->whereIn('status', ['approved', 'paid'])
            ->whereDate('period_start', $last->period_start->toDateString())->whereDate('period_end', $last->period_end->toDateString());

        return [
            'period_start' => $last->period_start->toDateString(),
            'period_end' => $last->period_end->toDateString(),
            'people' => (clone $period)->count(),
            'total' => round((float) (clone $period)->sum('net_salary'), 2),
        ];
    }

    private function leaveRow(EmployeeLeave $leave, $byId): array
    {
        $person = $byId->get($leave->employee_id);

        return [
            'id' => $leave->id,
            'name' => $this->nameOf($person),
            'avatar' => $person?->avatar_url,
            'type' => $leave->leave_type,
            'start_date' => $leave->start_date->toDateString(),
            'end_date' => $leave->end_date->toDateString(),
        ];
    }

    private function nameOf(?EmployeeInfo $person): string
    {
        return $person ? trim("{$person->first_name} {$person->last_name}") : 'Former employee';
    }
}
