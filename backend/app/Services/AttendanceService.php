<?php

namespace App\Services;

use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    private function buildVerificationNote(array $verificationData, bool $isTimeOut = false): string
    {
        $prefix = $isTimeOut ? 'Time out face verified' : 'Face verified';
        $matchScore = (int) ($verificationData['match_score'] ?? 0);
        $livenessData = $verificationData['liveness_data'] ?? [];
        $challengeList = collect($verificationData['challenges'] ?? [])
            ->filter()
            ->implode(', ');
        $frameCount = (int) ($livenessData['frames_checked'] ?? 0);
        $validMatchFrames = (int) ($livenessData['valid_match_frames'] ?? 0);

        return sprintf(
            '%s (%d%%, challenges: %s, frames: %d, matched frames: %d)',
            $prefix,
            $matchScore,
            $challengeList !== '' ? $challengeList : 'n/a',
            $frameCount,
            $validMatchFrames,
        );
    }

    public function processQRScan(array $qrData, int $currentOwnerId): array
    {
        try {
            DB::beginTransaction();

            // Set timezone to Philippine Time
            Carbon::setLocale('en');
            $timezone = 'Asia/Manila';

            // Validate QR data
            if (!isset($qrData['owner_id'], $qrData['employee_id'], $qrData['type'])) {
                return [
                    'success' => false,
                    'message' => 'Invalid QR code format',
                    'type' => 'error'
                ];
            }

            // Verify ownership
            if ((int)$qrData['owner_id'] !== $currentOwnerId) {
                return [
                    'success' => false,
                    'message' => 'QR code does not belong to your store',
                    'type' => 'error'
                ];
            }

            // Get employee
            $employee = EmployeeInfo::where('id', $qrData['employee_id'])
                ->where('owner_id', $currentOwnerId)
                ->first();

            if (!$employee) {
                return [
                    'success' => false,
                    'message' => 'Employee not found',
                    'type' => 'error'
                ];
            }

            // Get current Philippine Time
            $now = Carbon::now($timezone);
            $today = Carbon::today($timezone);

            // Check existing attendance for today (PH Time)
            $attendance = EmployeeAttendance::where('employee_id', $employee->id)
                ->where('owner_id', $currentOwnerId)
                ->where('attendance_date', $today->toDateString())
                ->first();

            // FIRST SCAN - TIME IN
            if (!$attendance) {
                $attendance = EmployeeAttendance::create([
                    'owner_id' => $currentOwnerId,
                    'employee_id' => $employee->id,
                    'attendance_date' => $today->toDateString(),
                    'day' => $today->format('l'), // Monday, Tuesday, etc.
                    'month' => $today->month,
                    'year' => $today->year,
                    'time_in' => $now->format('H:i:s'),
                    'status' => 'incomplete'
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Time In Recorded',
                    'type' => 'time_in',
                    'data' => [
                        'employee_name' => $employee->full_name,
                        'employee_id' => $employee->employee_id,
                        'time_in' => $now->format('h:i A'),
                        'date' => $today->format('M d, Y'),
                        'day' => $today->format('l'),
                        'timezone' => 'Philippine Time (UTC+8)'
                    ]
                ];
            }

            // SECOND SCAN - TIME OUT
            if ($attendance->time_in && !$attendance->time_out) {
                $dateStr = $attendance->attendance_date->toDateString();
                $timeIn  = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $attendance->time_in, $timezone);
                $timeOut = $now;

                $totalHours = abs($timeOut->timestamp - $timeIn->timestamp) / 3600;
                
                $attendance->update([
                    'time_out' => $now->format('H:i:s'),
                    'total_hours' => round($totalHours, 2),
                    'status' => 'complete'
                ]);

                DB::commit();

                return [
                    'success' => true,
                    'message' => 'Time Out Recorded',
                    'type' => 'time_out',
                    'data' => [
                        'employee_name' => $employee->full_name,
                        'employee_id' => $employee->employee_id,
                        'time_in' => $timeIn->format('h:i A'),
                        'time_out' => $timeOut->format('h:i A'),
                        'total_hours' => number_format($totalHours, 2),
                        'date' => $today->format('M d, Y'),
                        'day' => $today->format('l'),
                        'timezone' => 'Philippine Time (UTC+8)'
                    ]
                ];
            }

            // ALREADY COMPLETED
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Attendance already completed for today',
                'type' => 'already_complete'
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Failed to process attendance: ' . $e->getMessage(),
                'type' => 'error'
            ];
        }
    }

    public function timeIn(int $ownerId, int $employeeId, array $verificationData): array
    {
        try {
            DB::beginTransaction();
            $timezone = 'Asia/Manila';
            $now      = Carbon::now($timezone);
            $today    = $now->toDateString();

            $employee = EmployeeInfo::where('id', $employeeId)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$employee) {
                return ['success' => false, 'message' => 'Employee not found', 'type' => 'error'];
            }

            // Check already timed in today
            $existing = EmployeeAttendance::where('employee_id', $employeeId)
                ->where('owner_id', $ownerId)
                ->where('attendance_date', $today)
                ->first();

            if ($existing) {
                return ['success' => false, 'message' => 'Already timed in today', 'type' => 'already_timed_in'];
            }

            $attendance = EmployeeAttendance::create([
                'owner_id'        => $ownerId,
                'employee_id'     => $employeeId,
                'attendance_date' => $today,
                'day'             => $now->format('l'),
                'month'           => $now->month,
                'year'            => $now->year,
                'time_in'         => $now->format('H:i:s'),
                'status'          => 'incomplete',
                'notes'           => $this->buildVerificationNote($verificationData),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Time In Recorded',
                'type'    => 'time_in',
                'data'    => [
                    'employee_name' => $employee->full_name,
                    'employee_id'   => $employee->employee_id,
                    'time_in'       => $now->format('h:i A'),
                    'date'          => $now->format('M d, Y'),
                    'day'           => $now->format('l'),
                    'timezone'      => 'Philippine Time (UTC+8)',
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to record time in: ' . $e->getMessage(), 'type' => 'error'];
        }
    }

    public function timeOut(int $ownerId, int $employeeId, array $verificationData): array
    {
        try {
            DB::beginTransaction();
            $timezone = 'Asia/Manila';
            $now      = Carbon::now($timezone);
            $today    = $now->toDateString();

            $employee = EmployeeInfo::where('id', $employeeId)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$employee) {
                return ['success' => false, 'message' => 'Employee not found', 'type' => 'error'];
            }

            $attendance = EmployeeAttendance::where('employee_id', $employeeId)
                ->where('owner_id', $ownerId)
                ->where('attendance_date', $today)
                ->first();

            if (!$attendance) {
                return ['success' => false, 'message' => 'No Time In record found for today', 'type' => 'error'];
            }

            if ($attendance->time_out) {
                return ['success' => false, 'message' => 'Already timed out today', 'type' => 'already_complete'];
            }

            $timeIn     = Carbon::createFromFormat('Y-m-d H:i:s', $today . ' ' . $attendance->time_in, $timezone);
            $totalHours = round(abs($now->timestamp - $timeIn->timestamp) / 3600, 2);

            $attendance->update([
                'time_out'    => $now->format('H:i:s'),
                'total_hours' => $totalHours,
                'status'      => 'complete',
                'notes'       => trim(($attendance->notes ? $attendance->notes . ' | ' : '') . $this->buildVerificationNote($verificationData, true)),
            ]);

            DB::commit();

            return [
                'success' => true,
                'message' => 'Time Out Recorded',
                'type'    => 'time_out',
                'data'    => [
                    'employee_name' => $employee->full_name,
                    'employee_id'   => $employee->employee_id,
                    'time_in'       => $timeIn->format('h:i A'),
                    'time_out'      => $now->format('h:i A'),
                    'total_hours'   => number_format($totalHours, 2),
                    'date'          => $now->format('M d, Y'),
                    'day'           => $now->format('l'),
                    'timezone'      => 'Philippine Time (UTC+8)',
                ]
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            return ['success' => false, 'message' => 'Failed to record time out: ' . $e->getMessage(), 'type' => 'error'];
        }
    }

    public function getAttendanceRecords(int $ownerId, array $filters = [])
    {
        $query = EmployeeAttendance::with('employee')
            ->where('owner_id', $ownerId);

        // Filter by employee
        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        // Filter by date range
        if (!empty($filters['start_date']) && !empty($filters['end_date'])) {
            $query->whereBetween('attendance_date', [
                $filters['start_date'],
                $filters['end_date']
            ]);
        }

        // Filter by single date
        if (!empty($filters['date'])) {
            $query->where('attendance_date', $filters['date']);
        }

        // Filter by month and year
        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query->where('month', $filters['month'])
                  ->where('year', $filters['year']);
        }

        // Filter by year only
        if (!empty($filters['year']) && empty($filters['month'])) {
            $query->where('year', $filters['year']);
        }

        // Filter by status
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        // Search by employee name
        if (!empty($filters['search'])) {
            $query->whereHas('employee', function ($q) use ($filters) {
                $q->where('first_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('last_name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('employee_id', 'like', '%' . $filters['search'] . '%');
            });
        }

        // Order by
        $query->orderBy('attendance_date', 'desc')
              ->orderBy('time_in', 'desc');

        // Pagination
        $perPage = $filters['per_page'] ?? 15;
        
        return $query->paginate($perPage);
    }

    public function getAttendanceSummary(int $ownerId, string $startDate, string $endDate): array
    {
        $totalRecords = EmployeeAttendance::where('owner_id', $ownerId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->count();

        $completeRecords = EmployeeAttendance::where('owner_id', $ownerId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->where('status', 'complete')
            ->count();

        $incompleteRecords = EmployeeAttendance::where('owner_id', $ownerId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->where('status', 'incomplete')
            ->count();

        $totalHours = EmployeeAttendance::where('owner_id', $ownerId)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->where('status', 'complete')
            ->sum('total_hours');

        return [
            'total_records' => $totalRecords,
            'complete_records' => $completeRecords,
            'incomplete_records' => $incompleteRecords,
            'total_hours' => number_format($totalHours, 2),
            'average_hours' => $completeRecords > 0 ? number_format($totalHours / $completeRecords, 2) : '0.00'
        ];
    }

    public function manualAttendance(int $ownerId, int $employeeId, array $data): array
    {
        try {
            DB::beginTransaction();

            // Set timezone to Philippine Time
            $timezone = 'Asia/Manila';

            $employee = EmployeeInfo::where('id', $employeeId)
                ->where('owner_id', $ownerId)
                ->first();

            if (!$employee) {
                return [
                    'success' => false,
                    'message' => 'Employee not found'
                ];
            }

            $date = Carbon::parse($data['date'], $timezone);

            $attendance = EmployeeAttendance::updateOrCreate(
                [
                    'owner_id' => $ownerId,
                    'employee_id' => $employeeId,
                    'attendance_date' => $date->format('Y-m-d')
                ],
                [
                    'day' => $date->format('l'),
                    'month' => $date->month,
                    'year' => $date->year,
                    'time_in' => $data['time_in'],
                    'time_out' => $data['time_out'] ?? null,
                    'notes' => $data['notes'] ?? null
                ]
            );

            if ($attendance->time_out) {
                $dateStr = $attendance->attendance_date->toDateString();
                $timeIn  = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $attendance->time_in,  $timezone);
                $timeOut = Carbon::createFromFormat('Y-m-d H:i:s', $dateStr . ' ' . $attendance->time_out, $timezone);

                $totalHours = abs($timeOut->timestamp - $timeIn->timestamp) / 3600;
                
                $attendance->update([
                    'total_hours' => round($totalHours, 2),
                    'status' => 'complete'
                ]);
            }

            DB::commit();

            return [
                'success' => true,
                'message' => 'Attendance record saved successfully',
                'data' => $attendance
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            return [
                'success' => false,
                'message' => 'Failed to save attendance: ' . $e->getMessage()
            ];
        }
    }
}
