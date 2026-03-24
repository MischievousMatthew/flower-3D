<?php

namespace App\Services;

use App\Models\Payroll;
use App\Models\EmployeeInfo;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeLeave;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PayrollService
{
    // ─────────────────────────────────────────────────────────────────────────
    // GENERATE / PREVIEW
    // ─────────────────────────────────────────────────────────────────────────

    public function generatePayroll(int $ownerId, int $employeeId, string $periodStart, string $periodEnd, ?string $notes = null): array
    {
        try {
            DB::beginTransaction();

            $employee = EmployeeInfo::where('id', $employeeId)->where('owner_id', $ownerId)->first();

            if (!$employee) return ['success' => false, 'message' => 'Employee not found'];
            if (!$employee->basic_salary || !$employee->salary_type) return ['success' => false, 'message' => "Employee '{$employee->full_name}' does not have salary configuration set."];
            if (!$employee->standard_work_hours_per_day) return ['success' => false, 'message' => "Employee '{$employee->full_name}' is missing standard work hours per day."];
            if ($employee->salary_type === 'weekly' && !$employee->working_days_per_week) return ['success' => false, 'message' => "Employee '{$employee->full_name}' is missing working days per week."];
            if ($employee->salary_type === 'monthly' && !$employee->working_days_per_month) return ['success' => false, 'message' => "Employee '{$employee->full_name}' is missing working days per month."];

            $existing = Payroll::where('owner_id', $ownerId)
                ->where('employee_id', $employeeId)
                ->where('period_start', $periodStart)
                ->where('period_end', $periodEnd)
                ->where('status', '!=', 'rejected')
                ->first();

            if ($existing) return ['success' => false, 'message' => 'Payroll already exists for this period.'];

            $calculation = $this->calculate($ownerId, $employee, $periodStart, $periodEnd);

            $payroll = Payroll::create([
                'owner_id'                    => $ownerId,
                'employee_id'                 => $employeeId,
                'period_start'                => $periodStart,
                'period_end'                  => $periodEnd,
                'salary_type'                 => $employee->salary_type,
                'basic_salary'                => $employee->basic_salary,
                'standard_work_hours_per_day' => $employee->standard_work_hours_per_day,
                'working_days_per_week'       => $employee->working_days_per_week,
                'working_days_per_month'      => $employee->working_days_per_month,
                'total_hours_worked'          => round($calculation['totalHoursWorked'], 2),
                'hourly_rate'                 => round($calculation['hourlyRate'], 2),
                'gross_salary'                => round($calculation['grossSalary'], 2),
                'attendance_records_count'    => $calculation['actualWorkDays'],
                'expected_work_days'          => $calculation['expectedWorkingDays'],
                'actual_work_days'            => $calculation['actualWorkDays'],
                'paid_leave_days'             => $calculation['paidLeaveDaysCount'],
                'unpaid_leave_days'           => $calculation['unpaidLeaveDaysCount'],
                'absent_days'                 => $calculation['absentDays'],
                'deduction_amount'            => round($calculation['totalDeductions'], 2),
                'net_salary'                  => round($calculation['netSalary'], 2),
                'notes'                       => $notes,
                'status'                      => 'pending',
            ]);

            DB::commit();

            return ['success' => true, 'message' => 'Payroll generated successfully', 'data' => $payroll->load('employee')];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payroll generation error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to generate payroll: ' . $e->getMessage()];
        }
    }

    public function previewPayroll(int $ownerId, int $employeeId, string $periodStart, string $periodEnd): array
    {
        try {
            $employee = EmployeeInfo::where('id', $employeeId)->where('owner_id', $ownerId)->first();

            if (!$employee) return ['success' => false, 'message' => 'Employee not found'];
            if (!$employee->basic_salary || !$employee->salary_type) return ['success' => false, 'message' => "Employee '{$employee->full_name}' does not have salary configuration set."];
            if (!$employee->standard_work_hours_per_day) return ['success' => false, 'message' => "Employee '{$employee->full_name}' is missing standard work hours per day."];

            $c = $this->calculate($ownerId, $employee, $periodStart, $periodEnd);

            return [
                'success' => true,
                'data'    => [
                    'employee_name'            => $employee->full_name,
                    'employee_id'              => $employee->employee_id,
                    'period_start'             => $periodStart,
                    'period_end'               => $periodEnd,
                    'salary_type'              => $employee->salary_type,
                    'basic_salary'             => number_format($employee->basic_salary, 2),
                    'hourly_rate'              => number_format($c['hourlyRate'], 2),
                    'expected_work_days'       => $c['expectedWorkingDays'],
                    'actual_work_days'         => $c['actualWorkDays'],
                    'attendance_records_count' => $c['actualWorkDays'],
                    'paid_leave_days'          => $c['paidLeaveDaysCount'],
                    'unpaid_leave_days'        => $c['unpaidLeaveDaysCount'],
                    'absent_days'              => $c['absentDays'],
                    'total_hours_worked'       => number_format($c['totalHoursWorked'], 2),
                    'expected_salary'          => number_format($c['grossSalary'], 2),
                    'absent_deduction'         => number_format($c['absentDeduction'], 2),
                    'unpaid_leave_deduction'   => number_format($c['unpaidLeaveDeduction'], 2),
                    'gross_salary'             => number_format($c['grossSalary'], 2),
                    'deduction_amount'         => number_format($c['totalDeductions'], 2),
                    'net_salary'               => number_format($c['netSalary'], 2),
                ],
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to preview payroll: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HR QUERIES  (scoped by owner_id)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * HR view — only returns payrolls belonging to this HR user (owner_id scoped).
     */
    public function getPayrolls(int $ownerId, array $filters = [])
    {
        $query = Payroll::with('employee')->where('owner_id', $ownerId);

        return $this->applyPayrollFilters($query, $filters);
    }

    public function getPayrollSummary(int $ownerId, array $filters = []): array
    {
        $query = Payroll::where('owner_id', $ownerId);

        // Apply shared filters (month, year, search, status, etc)
        $query = $this->applyPayrollFiltersBuilder($query, $filters);

        $payrolls = $query->get();

        return [
            'total_payrolls'     => $payrolls->count(),
            'total_net_salary'   => number_format($payrolls->sum('net_salary'), 2),
            'total_gross_salary' => number_format($payrolls->sum('gross_salary'), 2),
            'total_deductions'   => number_format($payrolls->sum('deduction_amount'), 2),
            'total_hours_worked' => number_format($payrolls->sum('total_hours_worked'), 2),
            'unique_employees'   => $payrolls->unique('employee_id')->count(),
            'average_salary'     => $payrolls->count() > 0
                ? number_format($payrolls->sum('net_salary') / $payrolls->count(), 2)
                : '0.00',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // FINANCE QUERIES  (NO owner_id scope — cross-account)
    //
    // Finance is a different user account. HR payrolls have owner_id = HR user.
    // Finance must query ALL payrolls regardless of owner_id.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Finance view — queries ALL payrolls with NO owner_id filter.
     */
    public function getFinancePayrolls(array $filters = [])
    {
        $query = Payroll::with('employee');

        return $this->applyPayrollFilters($query, $filters);
    }

    /**
     * Finance summary cards — counts across ALL owner_ids.
     */
    public function getFinanceSummary(array $filters = []): array
    {
        $query = Payroll::query();

        // Apply shared filters (month, year, search, status, etc)
        $query = $this->applyPayrollFiltersBuilder($query, $filters);

        $payrolls = $query->get();

        return [
            'total_payrolls'       => $payrolls->count(),
            'pending_count'        => $payrolls->where('status', 'pending')->count(),
            'approved_count'       => $payrolls->where('status', 'approved')->count(),
            'rejected_count'       => $payrolls->where('status', 'rejected')->count(),
            'paid_count'           => $payrolls->where('status', 'paid')->count(),
            'unique_employees'     => $payrolls->unique('employee_id')->count(),
            'total_gross_salary'   => number_format($payrolls->sum('gross_salary'), 2),
            'total_net_salary'     => number_format($payrolls->sum('net_salary'), 2),
            'total_pending_amount' => number_format($payrolls->where('status', 'pending')->sum('net_salary'), 2),
            'total_paid_amount'    => number_format($payrolls->where('status', 'paid')->sum('net_salary'), 2),
        ];
    }

    public function financeApprovePayrolls(array $ids, ?string $notes = null): array
    {
        $validator = Validator::make($request->all(), [
            'ids'           => 'required|array|min:1',
            'ids.*'         => 'integer|exists:payrolls,id',
            'finance_notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }

        $result = $this->payrollService->financeApprovePayrolls($request->ids, $request->finance_notes);

        // ── Deduct approved payroll amounts from vendor balance ──────────────
        if ($result['success']) {
            $employee = $request->user(); // Employee instance

            $payrolls = \App\Models\Payroll::whereIn('id', $request->ids)->get();

            foreach ($payrolls as $payroll) {
                $vendorBalance = \App\Models\VendorBalance::forVendor($payroll->owner_id);

                $balanceBefore = (float) $vendorBalance->balance;
                $amount        = (float) ($payroll->net_salary ?? $payroll->gross_salary);
                $balanceAfter  = max(0, $balanceBefore - $amount);

                $vendorBalance->update([
                    'balance'         => $balanceAfter,
                    'total_withdrawn' => $vendorBalance->total_withdrawn + $amount,
                ]);

                \App\Models\VendorTransaction::create([
                    'vendor_id'      => $payroll->owner_id,
                    'order_id'       => null,
                    'type'           => 'debit',
                    'category'       => 'payroll',
                    'amount'         => $amount,
                    'balance_before' => $balanceBefore,
                    'balance_after'  => $balanceAfter,
                    'description'    => "Payroll approved for employee #{$payroll->employee_id} ({$payroll->period_start} – {$payroll->period_end})",
                    'status'         => 'completed',
                    'metadata'       => [
                        'payroll_id'  => $payroll->id,
                        'employee_id' => $payroll->employee_id,
                        'period'      => $payroll->period_start . ' to ' . $payroll->period_end,
                        'gross'       => (float) $payroll->gross_salary,
                        'net'         => (float) $payroll->net_salary,
                        'approved_by' => $employee?->name ?? 'Finance',
                        'approved_at' => now()->toIso8601String(),
                    ],
                ]);
            }
        }
        // ─────────────────────────────────────────────────────────────────────

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    public function financeRejectPayrolls(array $ids, ?string $notes = null): array
    {
        try {
            $payrolls = Payroll::whereIn('id', $ids)->where('status', 'pending')->get();
            // *** No owner_id scope ***

            if ($payrolls->isEmpty()) {
                return ['success' => false, 'message' => 'No pending payrolls found for the selected IDs.'];
            }

            $updated = 0;
            foreach ($payrolls as $payroll) {
                $payroll->update([
                    'status'              => 'rejected',
                    'finance_notes'       => $notes,
                ]);
                $updated++;
            }

            $skipped = count($ids) - $updated;
            return [
                'success' => true,
                'message' => "{$updated} payroll(s) rejected." . ($skipped > 0 ? " {$skipped} skipped (not pending)." : ''),
                'updated' => $updated,
            ];
        } catch (\Exception $e) {
            Log::error('Finance reject error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to reject payrolls: ' . $e->getMessage()];
        }
    }

    public function markPayrollsAsPaid(array $ids): array
    {
        try {
            $payrolls = Payroll::whereIn('id', $ids)->where('status', 'approved')->get();
            // *** No owner_id scope — Finance approved these, HR marks paid ***

            if ($payrolls->isEmpty()) {
                return ['success' => false, 'message' => 'No approved payrolls found for the selected IDs.'];
            }

            $updated = 0;
            foreach ($payrolls as $payroll) {
                $payroll->update(['status' => 'paid', 'paid_at' => now()]);
                $updated++;
            }

            $skipped = count($ids) - $updated;
            return [
                'success' => true,
                'message' => "{$updated} payroll(s) marked as paid." . ($skipped > 0 ? " {$skipped} skipped (not approved)." : ''),
                'updated' => $updated,
            ];
        } catch (\Exception $e) {
            Log::error('Mark paid error', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Failed to mark payrolls as paid: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // SHARED FILTER HELPER
    // ─────────────────────────────────────────────────────────────────────────

    public function applyPayrollFiltersBuilder($query, array $filters)
    {
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }

        if (!empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }

        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['month']) && !empty($filters['year'])) {
            $query->whereMonth('period_start', $filters['month'])
                  ->whereYear('period_start', $filters['year']);
        } elseif (!empty($filters['month'])) {
            $query->whereMonth('period_start', $filters['month']);
        } elseif (!empty($filters['year'])) {
            $query->whereYear('period_start', $filters['year']);
        }

        return $query;
    }

    private function applyPayrollFilters($query, array $filters)
    {
        $query = $this->applyPayrollFiltersBuilder($query, $filters);
        $query->orderBy('created_at', 'desc');

        return $query->paginate((int) ($filters['per_page'] ?? 15), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE CALCULATION HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function calculate(int $ownerId, EmployeeInfo $employee, string $periodStart, string $periodEnd): array
    {
        $attendanceRecords = EmployeeAttendance::where('owner_id', $ownerId)
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$periodStart, $periodEnd])
            ->where('status', 'complete')
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->whereNotNull('total_hours')
            ->get();

        $paidLeaveRecords = EmployeeLeave::where('owner_id', $ownerId)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('is_paid', true)
            ->where(fn($q) => $this->leaveOverlapScope($q, $periodStart, $periodEnd))
            ->get();

        $unpaidLeaveRecords = EmployeeLeave::where('owner_id', $ownerId)
            ->where('employee_id', $employee->id)
            ->where('status', 'approved')
            ->where('is_paid', false)
            ->where(fn($q) => $this->leaveOverlapScope($q, $periodStart, $periodEnd))
            ->get();

        $paidLeaveDaysCount   = $this->calculateActualLeaveDays($paidLeaveRecords, $periodStart, $periodEnd, $employee->rest_days);
        $unpaidLeaveDaysCount = $this->calculateActualLeaveDays($unpaidLeaveRecords, $periodStart, $periodEnd, $employee->rest_days);
        $expectedWorkingDays  = $this->calculateExpectedWorkingDays($periodStart, $periodEnd, $employee->rest_days);
        $actualWorkDays       = $attendanceRecords->count();
        $absentDays           = max(0, $expectedWorkingDays - $actualWorkDays - $paidLeaveDaysCount - $unpaidLeaveDaysCount);
        $actualWorkHours      = $attendanceRecords->sum('total_hours');
        $paidLeaveHours       = $paidLeaveDaysCount * $employee->standard_work_hours_per_day;
        $totalHoursWorked     = $actualWorkHours + $paidLeaveHours;

        $hourlyRate = $this->calculateHourlyRate(
            $employee->basic_salary,
            $employee->salary_type,
            $employee->standard_work_hours_per_day,
            $employee->working_days_per_week,
            $employee->working_days_per_month,
        );

        $grossSalary          = $expectedWorkingDays * $hourlyRate * $employee->standard_work_hours_per_day;
        $absentDeduction      = $absentDays * ($hourlyRate * $employee->standard_work_hours_per_day);
        $unpaidLeaveDeduction = $unpaidLeaveDaysCount * ($hourlyRate * $employee->standard_work_hours_per_day);
        $totalDeductions      = $absentDeduction + $unpaidLeaveDeduction;
        $netSalary            = max(0, $grossSalary - $totalDeductions);

        return compact(
            'expectedWorkingDays', 'actualWorkDays', 'paidLeaveDaysCount',
            'unpaidLeaveDaysCount', 'absentDays', 'actualWorkHours',
            'paidLeaveHours', 'totalHoursWorked', 'hourlyRate',
            'grossSalary', 'absentDeduction', 'unpaidLeaveDeduction',
            'totalDeductions', 'netSalary',
        );
    }

    private function leaveOverlapScope($query, string $periodStart, string $periodEnd)
    {
        return $query->where(function ($q) use ($periodStart, $periodEnd) {
            $q->whereBetween('start_date', [$periodStart, $periodEnd])
              ->orWhereBetween('end_date', [$periodStart, $periodEnd])
              ->orWhere(function ($inner) use ($periodStart, $periodEnd) {
                  $inner->where('start_date', '<=', $periodStart)->where('end_date', '>=', $periodEnd);
              });
        });
    }

    private function calculateActualLeaveDays($leaveRecords, string $periodStart, string $periodEnd, string $restDays): int
    {
        $total = 0;
        $ps    = Carbon::parse($periodStart);
        $pe    = Carbon::parse($periodEnd);

        foreach ($leaveRecords as $leave) {
            $start = Carbon::parse($leave->start_date)->max($ps);
            $end   = Carbon::parse($leave->end_date)->min($pe);
            $total += $this->calculateLeaveDays($start, $end, $restDays);
        }

        return $total;
    }

    private function calculateLeaveDays(Carbon $startDate, Carbon $endDate, string $restDays): int
    {
        $restDaysArray = array_map('trim', array_map('strtolower', explode(',', $restDays)));
        $total   = 0;
        $current = $startDate->copy();

        while ($current <= $endDate) {
            if (!in_array(strtolower($current->format('l')), $restDaysArray)) $total++;
            $current->addDay();
        }

        return $total;
    }

    private function calculateHourlyRate(float $basicSalary, string $salaryType, float $standardHoursPerDay, ?int $workingDaysPerWeek, ?int $workingDaysPerMonth): float
    {
        return match ($salaryType) {
            'daily'   => $basicSalary / $standardHoursPerDay,
            'weekly'  => $basicSalary / ($standardHoursPerDay * $workingDaysPerWeek),
            'monthly' => $basicSalary / ($standardHoursPerDay * $workingDaysPerMonth),
            default   => throw new \Exception('Invalid salary type'),
        };
    }

    private function calculateExpectedWorkingDays(string $periodStart, string $periodEnd, string $restDays): int
    {
        $restDaysArray = array_map('trim', array_map('strtolower', explode(',', $restDays)));
        $workingDays   = 0;
        $current       = Carbon::parse($periodStart);
        $end           = Carbon::parse($periodEnd);

        while ($current <= $end) {
            if (!in_array(strtolower($current->format('l')), $restDaysArray)) $workingDays++;
            $current->addDay();
        }

        return $workingDays;
    }
}