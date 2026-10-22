<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAttendance;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceReviewService;
use App\Services\AttendanceService;
use App\Traits\ScopesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * ERP attendance: logs, reports, correction requests and the review queue. Time in and out
 * happen only through the shop scanner (AttendanceKioskController).
 */
class AttendanceController extends Controller
{
    use ScopesOwner;

    private const TIME = 'regex:/^([01]\d|2[0-3]):[0-5]\d$/';

    public function __construct(
        private AttendanceService $attendanceService,
        private AttendanceReviewService $reviews,
        private AttendanceCalculator $calculator,
    ) {}

    /**
     * The old endpoints trusted the browser's word that a face was checked.
     * POST /attendance/scan, /attendance/time-in, /attendance/time-out
     */
    public function retired(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Attendance is now taken only with the shop scanner on an approved device. Reload the page to get the new scanner.',
        ], 410);
    }

    /** GET /attendance */
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $records = $this->attendanceService->getAttendanceRecords($ownerId, [
            'employee_id' => $request->employee_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date' => $request->date,
            'month' => $request->month,
            'year' => $request->year,
            'status' => $request->status,
            'search' => $request->search,
            'per_page' => min((int) ($request->per_page ?? 15), 500),
        ]);

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'pagination' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem(),
            ],
        ]);
    }

    /** GET /attendance/summary */
    public function summary(Request $request): JsonResponse
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->attendanceService->getAttendanceSummary($this->getOwnerId(), $data['start_date'], $data['end_date']),
        ]);
    }

    /** GET /attendance/report?from&to&employee_id — daily log with late/undertime/absent, and per-person totals. */
    public function report(Request $request): JsonResponse
    {
        $data = $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
            'employee_id' => 'nullable|integer',
        ]);
        $from = Carbon::parse($data['from'])->startOfDay();
        $to = Carbon::parse($data['to'])->endOfDay();
        if ($from->diffInDays($to) > AttendanceCalculator::MAX_RANGE_DAYS) {
            throw ValidationException::withMessages(['from' => 'Choose a range of three months or less.']);
        }

        return response()->json(['success' => true, 'data' => $this->calculator->report($this->getOwnerId(), $from, $to, $data['employee_id'] ?? null)]);
    }

    /** POST /attendance — ask to add a missing day (someone else approves it). */
    public function store(Request $request): JsonResponse
    {
        return $this->correction($request, null);
    }

    /** PUT /attendance/{id} — ask to fix a day's times (someone else approves it). */
    public function update(Request $request, int $id): JsonResponse
    {
        return $this->correction($request, EmployeeAttendance::forOwner($this->getOwnerId())->findOrFail($id));
    }

    /** POST /attendance/corrections */
    public function requestCorrection(Request $request): JsonResponse
    {
        return $this->correction($request, null);
    }

    /** GET /attendance/review — flagged scans, corrections waiting, and blocked attempts. */
    public function reviewQueue(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->reviews->queue($this->getOwnerId())]);
    }

    /** POST /attendance/{id}/review {decision, note} */
    public function review(Request $request, int $id): JsonResponse
    {
        $data = $this->validateDecision($request);
        $record = $this->reviews->decideRecord($this->getOwnerId(), $id, $data['decision'], $data['note'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'message' => $data['decision'] === 'approve' ? 'Approved. It now counts for payroll.' : 'Rejected. It won\'t count for payroll.',
            'data' => $this->reviews->formatRecord($record),
        ]);
    }

    /** POST /attendance/corrections/{id}/review {decision, note} */
    public function reviewCorrection(Request $request, int $id): JsonResponse
    {
        $data = $this->validateDecision($request);
        $this->reviews->decideCorrection($this->getOwnerId(), $id, $data['decision'], $data['note'] ?? null, $request->user());

        return response()->json([
            'success' => true,
            'message' => $data['decision'] === 'approve' ? 'Correction approved and applied.' : 'Correction rejected.',
        ]);
    }

    /** POST /attendance/{id}/overtime {decision, minutes?, note} */
    public function overtime(Request $request, int $id): JsonResponse
    {
        $data = $this->validateOvertime($request);
        $record = $this->reviews->decideOvertime($this->getOwnerId(), $id, $data['decision'], $data['minutes'] ?? null, $data['note'] ?? null, $request->user());

        return response()->json(['success' => true, 'message' => $this->overtimeMessage($record)]);
    }

    public static function overtimeMessage($record): string
    {
        return $record->overtime_status === 'approved'
            ? "Approved {$record->overtime_approved_minutes} minutes of overtime. It's paid at the overtime rate."
            : 'Overtime rejected. It won\'t be paid.';
    }

    private function validateOvertime(Request $request): array
    {
        return $request->validate([
            'decision' => 'required|in:approve,reject',
            'minutes' => 'nullable|integer|min:1|max:1440',
            'note' => 'nullable|string|max:500',
        ]);
    }

    /** GET /attendance/{id}/evidence/{which}/{index} — a link to an evidence photo, valid for 5 minutes. */
    public function evidence(int $id, string $which, int $index): JsonResponse
    {
        return response()->json(['success' => true, 'data' => ['url' => $this->reviews->evidenceLink($this->getOwnerId(), $id, $which, $index)]]);
    }

    /** DELETE /attendance/{id} */
    public function destroy(int $id): JsonResponse
    {
        EmployeeAttendance::forOwner($this->getOwnerId())->findOrFail($id)->delete();

        return response()->json(['success' => true, 'message' => 'Attendance record deleted.']);
    }

    private function correction(Request $request, ?EmployeeAttendance $record): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $data = $request->validate([
            'employee_id' => [$record ? 'nullable' : 'required', Rule::exists('employees_info', 'id')->where('owner_id', $ownerId)->whereNull('deleted_at')],
            'date' => [$record ? 'nullable' : 'required', 'date', 'before_or_equal:today'],
            'time_in' => ['required', self::TIME],
            'time_out' => ['nullable', self::TIME],
            'reason' => 'required|string|min:5|max:500',
        ], ['reason.required' => 'Say why the times need fixing.', 'reason.min' => 'Say a bit more about why.']);
        if ($record) {
            $data['employee_id'] = $record->employee_id;
            $data['date'] = Carbon::parse($record->attendance_date)->toDateString();
        }
        if (! empty($data['time_out']) && $data['time_out'] <= $data['time_in']) {
            throw ValidationException::withMessages(['time_out' => 'Time out must be after time in.']);
        }

        $correction = $this->reviews->requestCorrection($ownerId, $data, $request->user());

        return response()->json([
            'success' => true,
            'message' => 'Sent for approval. The shop owner or someone with approval access will review it.',
            'data' => $this->reviews->formatCorrection($correction->load('employee')),
        ], 201);
    }

    private function validateDecision(Request $request): array
    {
        return $request->validate([
            'decision' => 'required|in:approve,reject',
            'note' => 'nullable|string|max:500',
        ]);
    }
}
