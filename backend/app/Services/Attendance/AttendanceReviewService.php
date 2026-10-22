<?php

namespace App\Services\Attendance;

use App\Helpers\CloudinaryHelper;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceSession;
use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Flagged scans and correction requests, and who may decide them: the shop owner, or HR with
 * attendance "approve" — but never on their own attendance or their own request.
 */
class AttendanceReviewService
{
    public function queue(int $ownerId): array
    {
        $records = EmployeeAttendance::forOwner($ownerId)->with('employee')
            ->where('review_status', 'pending')
            ->orderByDesc('attendance_date')->limit(200)->get();
        $corrections = AttendanceCorrection::where('owner_id', $ownerId)->with('employee')
            ->where('status', 'pending')->orderBy('created_at')->limit(200)->get();
        $overtime = EmployeeAttendance::forOwner($ownerId)->with('employee')
            ->where('overtime_status', 'pending')
            ->orderByDesc('attendance_date')->limit(200)->get();
        $blocked = AttendanceSession::where('owner_id', $ownerId)->with(['employee', 'device'])
            ->where('created_at', '>=', now()->subDays(14))
            ->whereNotNull('used_at')
            ->latest()->limit(100)->get()
            ->filter(fn ($s) => ($s->flags['outcome'] ?? null) === 'blocked')
            ->take(30);

        return [
            'records' => $records->map(fn ($a) => $this->formatRecord($a))->values(),
            'corrections' => $corrections->map(fn ($c) => $this->formatCorrection($c))->values(),
            'overtime' => $overtime->map(fn ($a) => $this->formatOvertime($a))->values(),
            'blocked' => $blocked->map(fn ($s) => [
                'id' => $s->id,
                'employee' => $s->employee?->full_name ?? 'Unknown',
                'purpose' => $s->purpose,
                'device' => $s->device?->name,
                'operator' => $s->operator_name,
                'reason' => $s->flags['reason'] ?? null,
                'at' => $s->created_at->toIso8601String(),
            ])->values(),
        ];
    }

    public function formatRecord(EmployeeAttendance $a): array
    {
        $photos = fn ($e) => count($e['photos'] ?? []);

        return [
            'id' => $a->id,
            'employee_id' => $a->employee_id,
            'employee' => $a->employee?->full_name ?? 'Former employee',
            'date' => Carbon::parse($a->attendance_date)->toDateString(),
            'time_in' => AttendanceCalculator::hm($a->time_in),
            'time_out' => AttendanceCalculator::hm($a->time_out),
            'hours' => $a->total_hours !== null ? (float) $a->total_hours : null,
            'source' => $a->source,
            'review_status' => $a->review_status,
            'reasons' => $a->review_reasons ?? [],
            'reviewed_by' => $a->reviewed_by,
            'review_note' => $a->review_note,
            'evidence' => [
                'time_in' => $this->evidenceSummary($a->time_in_evidence),
                'time_out' => $this->evidenceSummary($a->time_out_evidence),
            ],
            'photo_count' => ['time_in' => $photos($a->time_in_evidence ?? []), 'time_out' => $photos($a->time_out_evidence ?? [])],
        ];
    }

    public function formatCorrection(AttendanceCorrection $c): array
    {
        return [
            'id' => $c->id,
            'employee_id' => $c->employee_info_id,
            'employee' => $c->employee?->full_name ?? 'Former employee',
            'date' => $c->date->toDateString(),
            'time_in' => AttendanceCalculator::hm($c->time_in),
            'time_out' => AttendanceCalculator::hm($c->time_out),
            'reason' => $c->reason,
            'requested_by' => $c->requested_by,
            'requested_at' => $c->created_at->toIso8601String(),
            'status' => $c->status,
            'current' => $this->currentTimes($c),
        ];
    }

    /** Approve or reject a flagged scan. */
    public function decideRecord(int $ownerId, int $id, string $decision, ?string $note, $reviewer): EmployeeAttendance
    {
        $record = EmployeeAttendance::forOwner($ownerId)->with('employee')->findOrFail($id);
        if ($record->review_status !== 'pending') {
            throw ValidationException::withMessages(['decision' => 'Someone already reviewed this.']);
        }
        $this->guardOwnAttendance($reviewer, $record->employee);
        if ($decision === 'reject' && ! trim((string) $note)) {
            throw ValidationException::withMessages(['note' => 'Say why you\'re rejecting it, so the person knows.']);
        }

        $record->fill([
            'review_status' => $decision === 'approve' ? 'approved' : 'rejected',
            'reviewed_by' => $this->name($reviewer),
            'reviewed_at' => now(),
            'review_note' => $note ? trim($note) : null,
        ])->save();

        return $record;
    }

    public function formatOvertime(EmployeeAttendance $a): array
    {
        $calculator = app(AttendanceCalculator::class);
        $date = Carbon::parse($a->attendance_date)->toDateString();
        $expected = $a->employee ? $calculator->expectedOn($a->owner_id, $a->employee, $date) : null;

        return [
            'id' => $a->id,
            'employee_id' => $a->employee_id,
            'employee' => $a->employee?->full_name ?? 'Former employee',
            'date' => $date,
            'scheduled' => $expected ? "{$expected['start']}–{$expected['end']}" : null,
            'time_in' => AttendanceCalculator::hm($a->time_in),
            'time_out' => AttendanceCalculator::hm($a->time_out),
            'minutes' => (int) $a->overtime_minutes,
            'status' => $a->overtime_status,
            'scan_needs_review' => $a->review_status === 'pending',
        ];
    }

    /**
     * Approve overtime (all of it, or fewer minutes than worked) or reject it.
     * Approved minutes are paid at the shop's overtime rate.
     */
    public function decideOvertime(int $ownerId, int $id, string $decision, ?int $minutes, ?string $note, $reviewer): EmployeeAttendance
    {
        $record = EmployeeAttendance::forOwner($ownerId)->with('employee')->findOrFail($id);
        if ($record->overtime_status !== 'pending') {
            throw ValidationException::withMessages(['decision' => 'Someone already decided this overtime.']);
        }
        $this->guardOwnAttendance($reviewer, $record->employee);
        if ($decision === 'reject' && ! trim((string) $note)) {
            throw ValidationException::withMessages(['note' => 'Say why you\'re rejecting it, so the person knows.']);
        }
        $worked = (int) $record->overtime_minutes;
        $approved = $decision === 'approve' ? ($minutes === null ? $worked : $minutes) : 0;
        if ($decision === 'approve' && ($approved < 1 || $approved > $worked)) {
            throw ValidationException::withMessages(['minutes' => "Approve between 1 and {$worked} minutes."]);
        }

        $record->fill([
            'overtime_status' => $decision === 'approve' ? 'approved' : 'rejected',
            'overtime_approved_minutes' => $decision === 'approve' ? $approved : 0,
            'overtime_reviewed_by' => $this->name($reviewer),
            'overtime_reviewed_at' => now(),
            'overtime_note' => $note ? trim($note) : null,
        ])->save();

        return $record;
    }

    /** Ask for a day's times to be fixed or added. */
    public function requestCorrection(int $ownerId, array $data, $requester): AttendanceCorrection
    {
        $employee = EmployeeInfo::forOwner($ownerId)->findOrFail($data['employee_id']);
        $date = Carbon::parse($data['date'])->toDateString();
        if (AttendanceCorrection::where('owner_id', $ownerId)->where('employee_info_id', $employee->id)
            ->whereDate('date', $date)->where('status', 'pending')->exists()) {
            throw ValidationException::withMessages(['date' => 'There\'s already a correction waiting for that day.']);
        }
        $record = EmployeeAttendance::forOwner($ownerId)->where('employee_id', $employee->id)->whereDate('attendance_date', $date)->first();

        return AttendanceCorrection::create([
            'owner_id' => $ownerId,
            'employee_info_id' => $employee->id,
            'attendance_id' => $record?->id,
            'date' => $date,
            'time_in' => $data['time_in'] . ':00',
            'time_out' => ! empty($data['time_out']) ? $data['time_out'] . ':00' : null,
            'reason' => trim($data['reason']),
            'requested_by' => $this->name($requester),
            'requested_by_employee_id' => $requester instanceof Employee ? $requester->id : null,
        ]);
    }

    /** Approve (and apply) or reject a correction. */
    public function decideCorrection(int $ownerId, int $id, string $decision, ?string $note, $reviewer): AttendanceCorrection
    {
        $correction = AttendanceCorrection::where('owner_id', $ownerId)->with('employee')->findOrFail($id);
        if ($correction->status !== 'pending') {
            throw ValidationException::withMessages(['decision' => 'Someone already decided this.']);
        }
        if ($reviewer instanceof Employee && $correction->requested_by_employee_id === $reviewer->id) {
            throw ValidationException::withMessages(['decision' => 'Someone else has to approve your own request.']);
        }
        $this->guardOwnAttendance($reviewer, $correction->employee);
        if ($decision === 'reject' && ! trim((string) $note)) {
            throw ValidationException::withMessages(['note' => 'Say why you\'re rejecting it, so the person knows.']);
        }

        DB::transaction(function () use ($ownerId, $correction, $decision, $note, $reviewer) {
            $correction->fill([
                'status' => $decision === 'approve' ? 'approved' : 'rejected',
                'reviewed_by' => $this->name($reviewer),
                'reviewed_at' => now(),
                'review_note' => $note ? trim($note) : null,
            ])->save();
            if ($decision === 'approve') {
                $this->applyCorrection($ownerId, $correction, $this->name($reviewer));
            }
        });

        return $correction;
    }

    /** A short-lived link to one evidence photo. */
    public function evidenceLink(int $ownerId, int $recordId, string $which, int $index): string
    {
        $record = EmployeeAttendance::forOwner($ownerId)->findOrFail($recordId);
        $evidence = $which === 'time_out' ? $record->time_out_evidence : ($which === 'enroll' ? null : $record->time_in_evidence);
        $publicId = $evidence['photos'][$index] ?? null;
        if (! $publicId) {
            throw ValidationException::withMessages(['photo' => 'That photo isn\'t available.']);
        }

        return CloudinaryHelper::privateDownloadUrl($publicId, 'jpg', 'image', 300);
    }

    private function applyCorrection(int $ownerId, AttendanceCorrection $c, string $approver): void
    {
        $date = Carbon::parse($c->date);
        $record = EmployeeAttendance::forOwner($ownerId)->where('employee_id', $c->employee_info_id)
            ->whereDate('attendance_date', $date->toDateString())->lockForUpdate()->first()
            ?? new EmployeeAttendance([
                'owner_id' => $ownerId,
                'employee_id' => $c->employee_info_id,
                'attendance_date' => $date->toDateString(),
                'day' => $date->format('l'),
                'month' => (int) $date->format('n'),
                'year' => (int) $date->format('Y'),
            ]);
        $hours = null;
        if ($c->time_out) {
            $hours = round(abs(Carbon::parse("{$date->toDateString()} {$c->time_out}")->timestamp - Carbon::parse("{$date->toDateString()} {$c->time_in}")->timestamp) / 3600, 2);
        }
        $record->fill([
            'time_in' => $c->time_in,
            'time_out' => $c->time_out,
            'total_hours' => $hours,
            'status' => $c->time_out ? 'complete' : 'incomplete',
            'source' => 'correction',
            'review_status' => 'approved',
            'reviewed_by' => $approver,
            'reviewed_at' => now(),
            'notes' => trim(($record->notes ? $record->notes . ' | ' : '') . "Corrected: {$c->reason} (asked by {$c->requested_by}, approved by {$approver})"),
        ]);
        $record->owner_id = $ownerId;
        // Corrected times past the shift's end still need their own overtime approval
        if ($c->employee) {
            app(AttendanceCalculator::class)->applyOvertime($ownerId, $record, $c->employee);
        }
        $record->save();
        $c->forceFill(['attendance_id' => $record->id])->save();
    }

    private function currentTimes(AttendanceCorrection $c): ?array
    {
        $record = EmployeeAttendance::forOwner($c->owner_id)->where('employee_id', $c->employee_info_id)
            ->whereDate('attendance_date', $c->date->toDateString())->first();

        return $record ? ['time_in' => AttendanceCalculator::hm($record->time_in), 'time_out' => AttendanceCalculator::hm($record->time_out)] : null;
    }

    /** People can't approve their own attendance. HR accounts and profiles are linked by email. */
    private function guardOwnAttendance($reviewer, ?EmployeeInfo $profile): void
    {
        if (! $reviewer instanceof Employee || ! $profile) {
            return;
        }
        $emails = array_filter([strtolower((string) $profile->work_email), strtolower((string) $profile->personal_email)]);
        if (in_array(strtolower((string) $reviewer->email), $emails, true)) {
            throw ValidationException::withMessages(['decision' => 'You can\'t review your own attendance. Ask someone else.']);
        }
    }

    private function evidenceSummary(?array $e): ?array
    {
        if (! $e) {
            return null;
        }

        return array_intersect_key($e, array_flip(['face_distance', 'antispoof', 'liveness', 'flash', 'device', 'operator', 'distance_m', 'at', 'reasons']));
    }

    private function name($person): string
    {
        return $person instanceof Employee ? ($person->name ?: 'HR') : 'Shop owner';
    }
}
