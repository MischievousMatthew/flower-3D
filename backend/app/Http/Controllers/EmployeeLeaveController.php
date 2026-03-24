<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\EmployeeLeave;
use App\Models\EmployeeInfo;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

use App\Traits\ScopesOwner;

class EmployeeLeaveController extends Controller
{
    use ScopesOwner;
    /**
     * Verify QR token and get employee details for leave request
     */
    public function verifyQRToken(Request $request): JsonResponse
    {
        try {
            $token = $request->input('token');
            
            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'QR token is required'
                ], 400);
            }

            $decoded = base64_decode($token);
            $data = json_decode($decoded, true);

            if (!$data || !isset($data['employee_id']) || !isset($data['owner_id'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid QR token'
                ], 400);
            }

            // Get employee details
            $employee = EmployeeInfo::where('id', $data['employee_id'])
                ->where('owner_id', $data['owner_id'])
                ->first();

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            $ipAddress = $request->ip();
            $recentAttempts = DB::table('leave_request_attempts')
                ->where('employee_id', $employee->id)
                ->where('ip_address', $ipAddress)
                ->where('attempted_at', '>=', now()->subMinutes(15))
                ->count();

            if ($recentAttempts >= 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again in 15 minutes.'
                ], 429);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'employee_id' => $employee->id,
                    'owner_id' => $employee->owner_id,
                    'employee_name' => $employee->full_name,
                    'employee_number' => $employee->employee_id,
                    'position' => $employee->position,
                    'department' => $employee->department
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify QR token',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Submit leave request (PUBLIC - No authentication required)
     */
    public function submitLeaveRequest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'employee_id' => 'required|exists:employees_info,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'leave_type' => 'required|in:sick_leave,vacation_leave,emergency_leave,unpaid_leave,maternity_leave,paternity_leave,bereavement_leave,other',
            'reason' => 'required|string|min:10|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $employeeId = $request->employee_id;
            $ipAddress = $request->ip();

            // Resolve owner_id from EmployeeInfo directly (source of truth)
            $employee = EmployeeInfo::find($employeeId);

            if (!$employee) {
                return response()->json([
                    'success' => false,
                    'message' => 'Employee not found'
                ], 404);
            }

            $ownerId = $employee->owner_id;

            // Rate limiting check
            $recentAttempts = DB::table('leave_request_attempts')
                ->where('employee_id', $employeeId)
                ->where('ip_address', $ipAddress)
                ->where('attempted_at', '>=', now()->subMinutes(15))
                ->count();

            if ($recentAttempts >= 5) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.'
                ], 429);
            }

            // Log attempt
            DB::table('leave_request_attempts')->insert([
                'employee_id' => $employeeId,
                'ip_address' => $ipAddress,
                'attempted_at' => now()
            ]);

            // Check for overlapping leave requests
            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $overlapping = EmployeeLeave::where('employee_id', $employeeId)
                ->where('owner_id', $ownerId)
                ->where('status', '!=', 'rejected')
                ->where(function ($query) use ($startDate, $endDate) {
                    $query->whereBetween('start_date', [$startDate, $endDate])
                        ->orWhereBetween('end_date', [$startDate, $endDate])
                        ->orWhere(function ($q) use ($startDate, $endDate) {
                            $q->where('start_date', '<=', $startDate)
                              ->where('end_date', '>=', $endDate);
                        });
                })
                ->exists();

            if ($overlapping) {
                return response()->json([
                    'success' => false,
                    'message' => 'You already have a leave request for this period'
                ], 422);
            }

            // Calculate total days (excluding rest days)
            $totalDays = $this->calculateLeaveDays(
                $startDate,
                $endDate,
                $employee->rest_days
            );

            // Generate unique submission token
            $submissionToken = Str::random(32);

            // Create leave request
            $leave = EmployeeLeave::create([
                'owner_id' => $ownerId,
                'employee_id' => $employeeId,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'total_days' => $totalDays,
                'leave_type' => $request->leave_type,
                'is_paid' => EmployeeLeave::isPaidLeaveType($request->leave_type),
                'status' => 'pending',
                'reason' => $request->reason,
                'submission_token' => $submissionToken,
                'submission_ip' => $ipAddress,
                'submission_device' => $request->userAgent(),
                'submitted_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leave request submitted successfully',
                'data' => [
                    'reference_number' => $leave->id,
                    'submission_token' => $submissionToken,
                    'total_days' => $totalDays,
                    'status' => 'pending'
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit leave request',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get all leave requests for admin
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $ownerId = $this->getOwnerId();

            $query = EmployeeLeave::forOwner($ownerId)
                ->with(['employee', 'reviewer']);

            // Filters
            if ($request->filled('employee_id')) {
                $query->where('employee_id', $request->employee_id);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('leave_type')) {
                $query->where('leave_type', $request->leave_type);
            }

            if ($request->filled('start_date') && $request->filled('end_date')) {
                $query->inPeriod($request->start_date, $request->end_date);
            }

            // Sort by most recent
            $query->orderBy('submitted_at', 'desc');

            // Paginate
            $perPage = $request->input('per_page', 15);
            $leaves = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $leaves->items(),
                'pagination' => [
                    'total' => $leaves->total(),
                    'per_page' => $leaves->perPage(),
                    'current_page' => $leaves->currentPage(),
                    'last_page' => $leaves->lastPage(),
                    'from' => $leaves->firstItem(),
                    'to' => $leaves->lastItem()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch leave requests',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Approve or reject leave request
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        // reviewer_id is NOT required from client — it comes from the authenticated session.
        $validator = Validator::make($request->all(), [
            'status'      => 'required|in:approved,rejected',
            'admin_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $validator->errors()
            ], 422);
        }

        try {
            // auth()->id() is the authenticated reviewer's ID.
            $ownerId    = $this->getOwnerId();
            $reviewerId = auth()->id();

            if (!$reviewerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Authenticated HR employee not found. Please log in again.'
                ], 401);
            }

            // Find the leave request using the correct owner scope
            $leave = EmployeeLeave::where('id', $id)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$leave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave request not found'
                ], 404);
            }

            if ($leave->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'This leave request has already been reviewed'
                ], 400);
            }

            // reviewer_id comes from the auth session — never from the request body
            $leave->update([
                'status'      => $request->status,
                'admin_notes' => $request->admin_notes,
                'reviewed_by' => $reviewerId,
                'reviewed_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Leave request ' . $request->status . ' successfully',
                'data'    => $leave->load(['employee', 'reviewer'])
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update leave status',
                'error'   => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Get leave statistics for dashboard
     */
    public function getStatistics(Request $request): JsonResponse
    {
        try {
            $ownerId = $this->getOwnerId();

            $stats = [
                'pending' => EmployeeLeave::forOwner($ownerId)->pending()->count(),
                'approved_this_month' => EmployeeLeave::forOwner($ownerId)
                    ->approved()
                    ->whereMonth('start_date', now()->month)
                    ->whereYear('start_date', now()->year)
                    ->count(),
                'total_days_this_month' => EmployeeLeave::forOwner($ownerId)
                    ->approved()
                    ->whereMonth('start_date', now()->month)
                    ->whereYear('start_date', now()->year)
                    ->sum('total_days'),
                'rejected' => EmployeeLeave::forOwner($ownerId)->rejected()->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Delete leave request
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $ownerId = $this->getOwnerId();

            $leave = EmployeeLeave::where('id', $id)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$leave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave request not found'
                ], 404);
            }

            $leave->delete();

            return response()->json([
                'success' => true,
                'message' => 'Leave request deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete leave request',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Calculate leave days excluding rest days
     */
    private function calculateLeaveDays(Carbon $startDate, Carbon $endDate, string $restDays): int
    {
        // Parse rest days
        $restDaysArray = array_map('trim', array_map('strtolower', explode(',', $restDays)));

        $totalDays = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $dayName = strtolower($current->format('l'));
            
            // Count if not a rest day
            if (!in_array($dayName, $restDaysArray)) {
                $totalDays++;
            }
            
            $current->addDay();
        }

        return $totalDays;
    }
}