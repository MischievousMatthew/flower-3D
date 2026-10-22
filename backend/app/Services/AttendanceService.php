<?php

namespace App\Services;

use App\Models\EmployeeAttendance;

/**
 * Reading attendance records. Recording time in/out happens in Attendance\AttendanceKioskService,
 * and fixing times in Attendance\AttendanceReviewService (corrections need approval).
 */
class AttendanceService
{
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
}
