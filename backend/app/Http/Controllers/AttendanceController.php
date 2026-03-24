<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Services\AttendanceService;
use App\Services\EmployeeQRCodeService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use App\Traits\ScopesOwner;

class AttendanceController extends Controller
{
    use ScopesOwner;
    protected $attendanceService;
    protected $qrCodeService;

    public function __construct(
        AttendanceService $attendanceService,
        EmployeeQRCodeService $qrCodeService
    ) {
        $this->attendanceService = $attendanceService;
        $this->qrCodeService = $qrCodeService;
    }

    /**
     * Process QR code scan for attendance
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function scanQR(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'qr_data' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR data',
                'errors' => $validator->errors()
            ], 422);
        }

        // Decode QR data
        $qrData = $this->qrCodeService->decodeQRData($request->qr_data);

        if (!$qrData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid QR code format',
                'type' => 'error'
            ], 400);
        }

        // Get current owner ID (from authenticated user)
        $currentOwnerId = $this->getOwnerId();

        // Validate QR data
        if (!$this->qrCodeService->validateQRData($qrData, $currentOwnerId)) {
            return response()->json([
                'success' => false,
                'message' => 'QR code does not belong to this store or is invalid',
                'type' => 'error'
            ], 403);
        }

        // Process attendance
        $result = $this->attendanceService->processQRScan($qrData, $currentOwnerId);

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    public function timeIn(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id'     => 'required|integer|exists:employees_info,id',
            'face_verified'   => 'required|boolean',
            'liveness_passed' => 'required|boolean',
            'match_score'     => 'required|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (!$request->boolean('face_verified') || !$request->boolean('liveness_passed')) {
            return response()->json(['success' => false, 'message' => 'Face verification required'], 403);
        }

        $ownerId = $this->getOwnerId();
        $result  = $this->attendanceService->timeIn($ownerId, $request->employee_id, $request->all());

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function timeOut(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id'     => 'required|integer|exists:employees_info,id',
            'face_verified'   => 'required|boolean',
            'liveness_passed' => 'required|boolean',
            'match_score'     => 'required|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        if (!$request->boolean('face_verified') || !$request->boolean('liveness_passed')) {
            return response()->json(['success' => false, 'message' => 'Face verification required'], 403);
        }

        $ownerId = $this->getOwnerId();
        $result  = $this->attendanceService->timeOut($ownerId, $request->employee_id, $request->all());

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get attendance records
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();

        $filters = [
            'employee_id' => $request->employee_id,
            'start_date' => $request->start_date,
            'end_date' => $request->end_date,
            'date' => $request->date,
            'month' => $request->month,
            'year' => $request->year,
            'status' => $request->status,
            'search' => $request->search,
            'per_page' => $request->per_page ?? 15
        ];

        $records = $this->attendanceService->getAttendanceRecords($ownerId, $filters);

        return response()->json([
            'success' => true,
            'data' => $records->items(),
            'pagination' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'from' => $records->firstItem(),
                'to' => $records->lastItem()
            ]
        ]);
    }

    /**
     * Get attendance summary
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function summary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid date range',
                'errors' => $validator->errors()
            ], 422);
        }

        $ownerId = $this->getOwnerId();
        $summary = $this->attendanceService->getAttendanceSummary(
            $ownerId,
            $request->start_date,
            $request->end_date
        );

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Manual attendance entry (admin override)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees_info,id',
            'date' => 'required|date',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ownerId = $this->getOwnerId();
        $result = $this->attendanceService->manualAttendance(
            $ownerId,
            $request->employee_id,
            $request->all()
        );

        $statusCode = $result['success'] ? 200 : 400;

        return response()->json($result, $statusCode);
    }

    /**
     * Update attendance record
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'time_in' => 'nullable|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i',
            'notes' => 'nullable|string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $ownerId = $this->getOwnerId();
        $attendance = \App\Models\EmployeeAttendance::where('id', $id)
            ->where('owner_id', $ownerId)
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found'
            ], 404);
        }

        if ($request->has('time_in')) {
            $attendance->time_in = $request->time_in;
        }

        if ($request->has('time_out')) {
            $attendance->time_out = $request->time_out;
        }

        if ($request->has('notes')) {
            $attendance->notes = $request->notes;
        }

        if ($attendance->time_in && $attendance->time_out) {
            $attendance->calculateTotalHours();
        }

        $attendance->save();

        return response()->json([
            'success' => true,
            'message' => 'Attendance updated successfully',
            'data' => $attendance
        ]);
    }

    /**
     * Delete attendance record
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $ownerId = $this->getOwnerId();
        $attendance = \App\Models\EmployeeAttendance::where('id', $id)
            ->where('owner_id', $ownerId)
            ->first();

        if (!$attendance) {
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found'
            ], 404);
        }

        $attendance->delete();

        return response()->json([
            'success' => true,
            'message' => 'Attendance record deleted successfully'
        ]);
    }
}