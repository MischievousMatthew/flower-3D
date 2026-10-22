<?php

namespace App\Services\Leave;

use App\Helpers\CloudinaryHelper;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use App\Models\EmployeeLeave;
use App\Models\LeaveBalanceAdjustment;
use App\Models\LeavePolicy;
use App\Models\OvertimeRequest;
use App\Models\RequestEvent;
use App\Services\Attendance\AttendanceCalculator;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Leave and overtime requests for the ERP, the vendor portal and the public leave page.
 * Leave counts working days from each person's schedule, is limited by the shop's yearly
 * allowance, and every step is kept in request_events.
 */
class LeaveService
{
    /** How far back employees themselves may file (e.g. sick leave after the fact) */
    public const EMPLOYEE_BACKDATE_DAYS = 14;
    public const OVERTIME_MIN = 15;
    public const OVERTIME_MAX = 720;

    public function __construct(private AttendanceCalculator $calculator) {}

    // ── Rules and balances ─────────────────────────────────────────────────

    /** The shop's rules, one per leave type, created with defaults the first time. */
    public function policies(int $ownerId): Collection
    {
        $existing = LeavePolicy::where('owner_id', $ownerId)->get()->keyBy('leave_type');
        foreach (LeavePolicy::TYPES as $type => [, $days, $paid, $document]) {
            if (! $existing->has($type)) {
                $existing[$type] = LeavePolicy::create([
                    'owner_id' => $ownerId, 'leave_type' => $type, 'annual_days' => $days,
                    'is_paid' => $paid, 'enabled' => true, 'requires_document' => $document,
                ]);
            }
        }

        return collect(array_keys(LeavePolicy::TYPES))->mapWithKeys(fn ($t) => [$t => $existing[$t]]);
    }

    public function formatPolicy(LeavePolicy $p): array
    {
        return [
            'leave_type' => $p->leave_type,
            'label' => LeavePolicy::label($p->leave_type),
            'annual_days' => $p->annual_days,
            'is_paid' => $p->is_paid,
            'enabled' => $p->enabled,
            'requires_document' => $p->requires_document,
        ];
    }

    /** Allowance, adjustments, used and waiting days per leave type for one person and year. */
    public function balances(int $ownerId, EmployeeInfo $employee, ?int $year = null): array
    {
        $year ??= (int) now()->year;
        $policies = $this->policies($ownerId);
        $leaves = $this->leaves($ownerId)->where('employee_id', $employee->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereYear('start_date', $year)->get();
        $adjustments = LeaveBalanceAdjustment::where('owner_id', $ownerId)->where('employee_info_id', $employee->id)
            ->where('year', $year)->get()->groupBy('leave_type');

        return $policies->filter(fn ($p) => $p->enabled)->map(function (LeavePolicy $p) use ($leaves, $adjustments) {
            $used = (float) $leaves->where('leave_type', $p->leave_type)->where('status', 'approved')->sum('total_days');
            $pending = (float) $leaves->where('leave_type', $p->leave_type)->where('status', 'pending')->sum('total_days');
            $adjusted = (float) ($adjustments->get($p->leave_type)?->sum('days') ?? 0);
            $allowance = $p->annual_days === null ? null : $p->annual_days + $adjusted;

            return [
                'leave_type' => $p->leave_type,
                'label' => LeavePolicy::label($p->leave_type),
                'is_paid' => $p->is_paid,
                'requires_document' => $p->requires_document,
                'allowance' => $allowance,
                'adjusted' => $adjusted,
                'used' => $used,
                'pending' => $pending,
                // What's left to ask for: approved and waiting requests both count
                'available' => $allowance === null ? null : round($allowance - $used - $pending, 1),
            ];
        })->values()->all();
    }

    public function adjustBalance(int $ownerId, EmployeeInfo $employee, string $type, int $year, float $days, string $note, $actor): void
    {
        $this->policy($ownerId, $type);
        LeaveBalanceAdjustment::create([
            'owner_id' => $ownerId, 'employee_info_id' => $employee->id, 'leave_type' => $type,
            'year' => $year, 'days' => $days, 'note' => $note, 'adjusted_by' => $this->name($actor),
        ]);
    }

    /** Working days in a range for this person: days their schedule (or usual hours) has them working. */
    public function workingDays(int $ownerId, EmployeeInfo $employee, Carbon $from, Carbon $to): int
    {
        $shifts = $this->calculator->shiftsBetween($ownerId, $from, $to);
        $restDays = $this->calculator->restDays($employee);
        $count = 0;
        foreach (CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay()) as $day) {
            $date = $day->toDateString();
            $shift = $shifts->get($employee->id . '|' . $date);
            if ($shift || ($employee->shift_start && $employee->shift_end)) {
                $count += $this->calculator->expected($employee, $date, $shift) ? 1 : 0;
            } else {
                // No hours set at all: every day except their rest days
                $count += in_array($day->format('l'), $restDays, true) ? 0 : 1;
            }
        }

        return $count;
    }

    // ── Leave ──────────────────────────────────────────────────────────────

    /**
     * File a leave request. $by: 'employee' (from the leave page), 'hr' or 'owner'.
     */
    public function file(int $ownerId, EmployeeInfo $employee, array $data, ?UploadedFile $document, string $by, ?string $byName, bool $badgeVerified = true): EmployeeLeave
    {
        $policy = $this->policy($ownerId, $data['leave_type']);
        if (! $policy->enabled) {
            throw ValidationException::withMessages(['leave_type' => 'This shop doesn\'t offer that kind of leave.']);
        }
        $start = Carbon::parse($data['start_date'])->startOfDay();
        $end = Carbon::parse($data['end_date'])->startOfDay();
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['end_date' => 'The last day must be on or after the first day.']);
        }
        if ($start->diffInDays($end) > 180) {
            throw ValidationException::withMessages(['end_date' => 'File leave of up to 6 months at a time.']);
        }
        if ($by === 'employee' && $start->lt(now()->startOfDay()->subDays(self::EMPLOYEE_BACKDATE_DAYS))) {
            throw ValidationException::withMessages(['start_date' => 'For leave more than two weeks ago, ask HR to file it for you.']);
        }
        if ($this->overlaps($ownerId, $employee->id, $start, $end)) {
            throw ValidationException::withMessages(['start_date' => 'There\'s already a leave request for some of these days.']);
        }
        $days = $this->workingDays($ownerId, $employee, $start, $end);
        if ($days < 1) {
            throw ValidationException::withMessages(['start_date' => 'Those are all rest days or days off, so no leave is needed.']);
        }
        $this->guardBalance($ownerId, $employee, $policy, $days, (int) $start->year);
        if ($policy->requires_document && ! $document) {
            throw ValidationException::withMessages(['document' => LeavePolicy::label($policy->leave_type) . ' needs a supporting document, like a medical certificate.']);
        }

        $upload = $document ? $this->storeDocument($ownerId, $employee, $document) : null;

        return DB::transaction(function () use ($ownerId, $employee, $data, $policy, $start, $end, $days, $upload, $by, $byName, $badgeVerified) {
            $leave = EmployeeLeave::withoutGlobalScopes()->create(array_merge([
                'owner_id' => $ownerId,
                'employee_id' => $employee->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'total_days' => $days,
                'leave_type' => $policy->leave_type,
                'is_paid' => $policy->is_paid,
                'status' => 'pending',
                'reason' => trim($data['reason']),
                'submission_token' => Str::random(32),
                'submitted_at' => now(),
                'filed_by' => $by,
                'filed_by_name' => $byName,
                'badge_verified' => $badgeVerified,
            ], $upload ?? []));
            RequestEvent::log($ownerId, 'leave', $leave->id, 'filed', $byName ?? $employee->full_name,
                ($by === 'employee' ? 'Filed from the leave page' : 'Filed by ' . ($by === 'owner' ? 'the shop owner' : 'HR'))
                . ($badgeVerified ? '' : ' with an old badge — identity not verified') . ($upload ? ', with a document' : ''));

            return $leave;
        });
    }

    /** Approve or reject. Approving re-checks the balance; nobody decides their own leave. */
    public function decide(int $ownerId, EmployeeLeave $leave, string $status, ?string $note, $reviewer): EmployeeLeave
    {
        if ($leave->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This request was already ' . $leave->status . '.']);
        }
        $employee = EmployeeInfo::forOwner($ownerId)->withTrashed()->find($leave->employee_id);
        $this->guardOwn($reviewer, $employee, 'leave');
        if ($status === 'rejected' && ! trim((string) $note)) {
            throw ValidationException::withMessages(['admin_notes' => 'Say why you\'re rejecting it, so they know.']);
        }
        if ($status === 'approved' && $employee) {
            $this->guardBalance($ownerId, $employee, $this->policy($ownerId, $leave->leave_type), 0, (int) Carbon::parse($leave->start_date)->year, includePending: false, extra: (float) $leave->total_days);
        }

        $leave->fill([
            'status' => $status,
            'admin_notes' => $note ? trim($note) : null,
            'reviewed_by' => $reviewer instanceof Employee ? $reviewer->id : null,
            'reviewer_name' => $this->name($reviewer),
            'reviewed_at' => now(),
        ])->save();
        RequestEvent::log($ownerId, 'leave', $leave->id, $status, $this->name($reviewer), $note ? trim($note) : null);

        return $leave;
    }

    /** Cancel: the person themselves while it's waiting; HR or the owner also before an approved leave starts. */
    public function cancel(int $ownerId, EmployeeLeave $leave, string $actorName, bool $byEmployee, ?string $note = null): void
    {
        $allowed = $leave->status === 'pending'
            || (! $byEmployee && $leave->status === 'approved' && Carbon::parse($leave->start_date)->isFuture());
        if (! $allowed) {
            throw ValidationException::withMessages(['status' => $byEmployee
                ? 'Only requests that are still waiting can be cancelled. Ask HR about approved leave.'
                : 'Only waiting requests, or approved leave that hasn\'t started, can be cancelled.']);
        }
        $leave->fill(['status' => 'cancelled', 'cancelled_at' => now()])->save();
        RequestEvent::log($ownerId, 'leave', $leave->id, 'cancelled', $actorName, $note);
    }

    public function documentLink(EmployeeLeave $leave): string
    {
        if (! $leave->document_public_id) {
            throw ValidationException::withMessages(['document' => 'No document was attached.']);
        }

        return CloudinaryHelper::privateDownloadUrl($leave->document_public_id, $leave->document_format, $leave->document_resource_type ?? 'image', 300);
    }

    public function formatLeave(EmployeeLeave $l, bool $withHistory = false): array
    {
        $out = [
            'id' => $l->id,
            'employee_id' => $l->employee_id,
            'employee' => $l->employee?->full_name ?? 'Former employee',
            'position' => $l->employee?->position,
            'leave_type' => $l->leave_type,
            'label' => LeavePolicy::label($l->leave_type),
            'start_date' => Carbon::parse($l->start_date)->toDateString(),
            'end_date' => Carbon::parse($l->end_date)->toDateString(),
            'days' => (float) $l->total_days,
            'is_paid' => (bool) $l->is_paid,
            'status' => $l->status,
            'reason' => $l->reason,
            'review_note' => $l->admin_notes,
            'reviewer' => $l->reviewer_name,
            'reviewed_at' => $l->reviewed_at?->toIso8601String(),
            'filed_by' => $l->filed_by ?? 'employee',
            'filed_by_name' => $l->filed_by_name,
            'badge_verified' => $l->badge_verified ?? true,
            'filed_at' => ($l->submitted_at ?? $l->created_at)?->toIso8601String(),
            'document' => $l->document_name,
        ];
        if ($withHistory) {
            $out['history'] = $this->history('leave', $l->id);
        }

        return $out;
    }

    // ── Overtime requests ──────────────────────────────────────────────────

    public function requestOvertime(int $ownerId, EmployeeInfo $employee, array $data, string $by, ?string $byName): OvertimeRequest
    {
        $date = Carbon::parse($data['date'])->startOfDay();
        if ($by === 'employee' && $date->lt(now()->startOfDay())) {
            throw ValidationException::withMessages(['date' => 'Ask for overtime on today or a later day. For past days, HR approves it in Attendance → Review.']);
        }
        $minutes = (int) $data['minutes'];
        if ($minutes < self::OVERTIME_MIN || $minutes > self::OVERTIME_MAX) {
            throw ValidationException::withMessages(['minutes' => 'Ask for between 15 minutes and 12 hours.']);
        }
        if (! $this->calculator->expectedOn($ownerId, $employee, $date->toDateString())) {
            throw ValidationException::withMessages(['date' => "{$employee->full_name} isn't scheduled to work that day, so there's no shift to extend."]);
        }
        $exists = OvertimeRequest::where('owner_id', $ownerId)->where('employee_info_id', $employee->id)
            ->whereDate('date', $date->toDateString())->whereIn('status', ['pending', 'approved'])->exists();
        if ($exists) {
            throw ValidationException::withMessages(['date' => 'There\'s already an overtime request for that day.']);
        }

        return DB::transaction(function () use ($ownerId, $employee, $data, $date, $minutes, $by, $byName) {
            $request = OvertimeRequest::create([
                'owner_id' => $ownerId, 'employee_info_id' => $employee->id, 'date' => $date->toDateString(),
                'minutes' => $minutes, 'reason' => trim($data['reason']), 'status' => 'pending',
                'filed_by' => $by, 'filed_by_name' => $byName,
            ]);
            RequestEvent::log($ownerId, 'overtime_request', $request->id, 'filed', $byName ?? $employee->full_name, null);

            return $request;
        });
    }

    public function decideOvertimeRequest(int $ownerId, OvertimeRequest $request, string $status, ?string $note, $reviewer): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'This request was already ' . $request->status . '.']);
        }
        $this->guardOwn($reviewer, $request->employee, 'overtime');
        if ($status === 'rejected' && ! trim((string) $note)) {
            throw ValidationException::withMessages(['note' => 'Say why you\'re rejecting it, so they know.']);
        }
        DB::transaction(function () use ($ownerId, $request, $status, $note, $reviewer) {
            $request->fill(['status' => $status, 'reviewer_name' => $this->name($reviewer), 'reviewed_at' => now(), 'review_note' => $note ? trim($note) : null])->save();
            RequestEvent::log($ownerId, 'overtime_request', $request->id, $status, $this->name($reviewer), $note ? trim($note) : null);

            // Approved after they already stayed late: settle that day's overtime now
            if ($status === 'approved') {
                $record = EmployeeAttendance::forOwner($ownerId)->where('employee_id', $request->employee_info_id)
                    ->whereDate('attendance_date', $request->date->toDateString())->where('overtime_status', 'pending')->first();
                if ($record && $request->employee) {
                    $this->calculator->applyOvertime($ownerId, $record, $request->employee);
                    $record->save();
                }
            }
        });
    }

    public function cancelOvertimeRequest(int $ownerId, OvertimeRequest $request, string $actorName): void
    {
        if ($request->status !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Only requests that are still waiting can be cancelled.']);
        }
        $request->fill(['status' => 'cancelled'])->save();
        RequestEvent::log($ownerId, 'overtime_request', $request->id, 'cancelled', $actorName, null);
    }

    public function formatOvertimeRequest(OvertimeRequest $r, bool $withHistory = false): array
    {
        $out = [
            'id' => $r->id,
            'employee_id' => $r->employee_info_id,
            'employee' => $r->employee?->full_name ?? 'Former employee',
            'date' => $r->date->toDateString(),
            'minutes' => $r->minutes,
            'reason' => $r->reason,
            'status' => $r->status,
            'reviewer' => $r->reviewer_name,
            'review_note' => $r->review_note,
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'filed_by' => $r->filed_by,
            'filed_by_name' => $r->filed_by_name,
            'filed_at' => $r->created_at?->toIso8601String(),
        ];
        if ($withHistory) {
            $out['history'] = $this->history('overtime_request', $r->id);
        }

        return $out;
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function leaves(int $ownerId)
    {
        return EmployeeLeave::withoutGlobalScopes()->where('owner_id', $ownerId);
    }

    public function history(string $type, int $id): array
    {
        return RequestEvent::where('subject_type', $type)->where('subject_id', $id)->orderBy('id')->get()
            ->map(fn ($e) => ['action' => $e->action, 'by' => $e->actor_name, 'note' => $e->note, 'at' => $e->created_at?->toIso8601String()])
            ->all();
    }

    public function policy(int $ownerId, string $type): LeavePolicy
    {
        $policy = $this->policies($ownerId)->get($type);
        if (! $policy) {
            throw ValidationException::withMessages(['leave_type' => 'Choose a kind of leave.']);
        }

        return $policy;
    }

    private function overlaps(int $ownerId, int $employeeId, Carbon $start, Carbon $end): bool
    {
        return $this->leaves($ownerId)->where('employee_id', $employeeId)->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())->exists();
    }

    /** Throws when the request needs more days than are left this year. */
    private function guardBalance(int $ownerId, EmployeeInfo $employee, LeavePolicy $policy, float $days, int $year, bool $includePending = true, float $extra = 0): void
    {
        if ($policy->annual_days === null) {
            return;
        }
        $balance = collect($this->balances($ownerId, $employee, $year))->firstWhere('leave_type', $policy->leave_type);
        if (! $balance) {
            return;
        }
        // When approving, the request itself is among the "waiting" days, so count only approved ones plus it
        $left = $includePending ? $balance['available'] : round($balance['allowance'] - $balance['used'], 1);
        $need = $days + $extra;
        if ($need > $left) {
            throw ValidationException::withMessages(['leave_type' => sprintf(
                '%s has %s %s of %s left for %d, but this needs %s.',
                $employee->full_name, self::days($left), $left == 1 ? 'day' : 'days', strtolower(LeavePolicy::label($policy->leave_type)), $year, self::days($need),
            )]);
        }
    }

    private function storeDocument(int $ownerId, EmployeeInfo $employee, UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        try {
            $upload = CloudinaryHelper::upload($file, [
                'folder' => "leave-documents/{$ownerId}",
                'type' => 'authenticated',
                'resource_type' => in_array($extension, ['doc', 'docx'], true) ? 'raw' : 'image',
            ]);
        } catch (\Throwable $e) {
            Log::error('Leave document upload failed', ['error' => $e->getMessage(), 'employee' => $employee->id]);
            throw ValidationException::withMessages(['document' => 'Couldn\'t upload the document. Try again.']);
        }

        return [
            'document_public_id' => $upload['public_id'],
            'document_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'document_format' => $upload['format'] ?? $extension,
            'document_resource_type' => $upload['resource_type'] ?? 'image',
        ];
    }

    /** People can't approve their own requests. HR accounts and profiles are linked by email. */
    private function guardOwn($reviewer, ?EmployeeInfo $profile, string $what): void
    {
        if (! $reviewer instanceof Employee || ! $profile) {
            return;
        }
        $emails = array_filter([strtolower((string) $profile->work_email), strtolower((string) $profile->personal_email)]);
        if (in_array(strtolower((string) $reviewer->email), $emails, true)) {
            throw ValidationException::withMessages(['status' => "You can't decide your own {$what} request. Ask someone else."]);
        }
    }

    private static function days(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1), '0'), '.');
    }

    private function name($actor): string
    {
        return $actor instanceof Employee ? ($actor->name ?: 'HR') : 'Shop owner';
    }
}
