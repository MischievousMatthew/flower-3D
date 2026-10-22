<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeAttendance;
use App\Models\EmployeeInfo;
use App\Models\EmployeeLeave;
use App\Models\PayComponent;
use App\Models\Payroll;
use App\Models\PayrollItem;
use App\Models\RequestEvent;
use App\Services\Attendance\AttendanceCalculator;
use App\Services\Attendance\AttendanceLocationService;
use App\Services\Leave\LeaveService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Payroll for HR (ERP), Finance (ERP) and the shop owner (vendor portal).
 *
 * Pay = days worked × daily rate + paid leave + approved overtime + allowances + bonuses
 *       − government contributions (the share of a month the payroll covers) − deductions.
 * Flow: HR creates (pending) → Finance approves or sends back (rejected) → paid.
 * Pending or sent-back payrolls can be revised; approved and paid ones are locked.
 * Nobody approves a payroll they created. Every step is in request_events ('payroll').
 */
class PayrollService
{
    public function __construct(private GovernmentContributionService $contributionService) {}

    // ─────────────────────────────────────────────────────────────────────────
    // Creating and revising
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * @param array $items one-off lines: [{kind: bonus|allowance|deduction, label, amount}]
     */
    public function generatePayroll(
        int $ownerId,
        int $employeeId,
        string $periodStart,
        string $periodEnd,
        ?string $notes = null,
        bool $includeContributions = false,
        array $items = [],
        $actor = null,
    ): array {
        try {
            $employee = EmployeeInfo::forOwner($ownerId)->find($employeeId);
            if (! $employee) {
                return ['success' => false, 'message' => 'Employee not found'];
            }
            if ($message = $this->payConfigProblem($employee)) {
                return ['success' => false, 'message' => $message];
            }
            if ($this->overlappingPayroll($ownerId, $employeeId, $periodStart, $periodEnd)) {
                return ['success' => false, 'message' => "{$employee->full_name} already has a payroll covering part of this period."];
            }

            $c = $this->calculate($ownerId, $employee, $periodStart, $periodEnd, $items, $includeContributions);
            $payroll = DB::transaction(function () use ($ownerId, $employee, $periodStart, $periodEnd, $notes, $includeContributions, $c, $actor) {
                $payroll = Payroll::withoutGlobalScopes()->create($this->attributes($ownerId, $employee, $periodStart, $periodEnd, $c) + [
                    'notes' => $notes,
                    'status' => 'pending',
                    'include_contributions' => $includeContributions,
                    'created_by_name' => $this->name($actor),
                    'created_by_employee_id' => $actor instanceof Employee ? $actor->id : null,
                ]);
                $this->saveItems($payroll, $c['items']);
                RequestEvent::log($ownerId, 'payroll', $payroll->id, 'created', $this->name($actor), $notes);

                return $payroll;
            });

            return ['success' => true, 'message' => 'Payroll created. Finance approves it before it\'s paid.', 'data' => $payroll->load(['employee', 'items'])];
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => collect($e->errors())->flatten()->first()];
        } catch (\Exception $e) {
            Log::error('Payroll generation error', ['error' => $e->getMessage()]);

            return ['success' => false, 'message' => 'Failed to generate payroll: ' . $e->getMessage()];
        }
    }

    /** Recalculate a pending or sent-back payroll with new extra lines or notes; it goes back to Finance. */
    public function revisePayroll(int $ownerId, Payroll $payroll, array $items, ?string $notes, bool $includeContributions, $actor): array
    {
        if (! in_array($payroll->status, ['pending', 'rejected'], true)) {
            return ['success' => false, 'message' => 'Approved and paid payrolls can\'t be changed.'];
        }
        $employee = EmployeeInfo::forOwner($ownerId)->find($payroll->employee_id);
        if (! $employee || ($message = $this->payConfigProblem($employee))) {
            return ['success' => false, 'message' => $message ?? 'Employee not found'];
        }
        $start = $payroll->period_start->toDateString();
        $end = $payroll->period_end->toDateString();
        $c = $this->calculate($ownerId, $employee, $start, $end, $items, $includeContributions);

        DB::transaction(function () use ($ownerId, $payroll, $employee, $start, $end, $c, $notes, $includeContributions, $actor) {
            $wasRejected = $payroll->status === 'rejected';
            $payroll->fill($this->attributes($ownerId, $employee, $start, $end, $c) + [
                'notes' => $notes,
                'status' => 'pending',
                'include_contributions' => $includeContributions,
            ])->save();
            $payroll->items()->delete();
            $this->saveItems($payroll, $c['items']);
            RequestEvent::log($ownerId, 'payroll', $payroll->id, $wasRejected ? 'resubmitted' : 'revised', $this->name($actor), $notes);
        });

        return ['success' => true, 'message' => 'Payroll recalculated and sent to Finance again.', 'data' => $payroll->fresh(['employee', 'items'])];
    }

    public function previewPayroll(int $ownerId, int $employeeId, string $periodStart, string $periodEnd, bool $includeContributions = false, array $items = []): array
    {
        try {
            $employee = EmployeeInfo::forOwner($ownerId)->find($employeeId);
            if (! $employee) {
                return ['success' => false, 'message' => 'Employee not found'];
            }
            if ($message = $this->payConfigProblem($employee)) {
                return ['success' => false, 'message' => $message];
            }
            $c = $this->calculate($ownerId, $employee, $periodStart, $periodEnd, $items, $includeContributions);
            $n = fn ($v) => number_format((float) $v, 2);

            return [
                'success' => true,
                'data' => [
                    'employee_id' => $employee->id,
                    'employee_name' => $employee->full_name,
                    'employee_code' => $employee->employee_id,
                    'position' => $employee->position,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'salary_type' => $employee->salary_type,
                    'basic_salary' => $n($employee->basic_salary),
                    'hourly_rate' => $n($c['hourlyRate']),
                    'daily_rate' => $n($c['dailyRate']),
                    'expected_work_days' => $c['expectedWorkingDays'],
                    'actual_work_days' => $c['actualWorkDays'],
                    'attendance_records_count' => $c['actualWorkDays'],
                    'payable_days' => $c['actualWorkDays'] + $c['paidLeaveDaysCount'],
                    'paid_leave_days' => $c['paidLeaveDaysCount'],
                    'unpaid_leave_days' => $c['unpaidLeaveDaysCount'],
                    'absent_days' => $c['absentDays'],
                    'late_minutes' => $c['lateMinutes'],
                    'undertime_minutes' => $c['undertimeMinutes'],
                    'actual_work_hours' => $n($c['actualWorkHours']),
                    'total_hours_worked' => $n($c['totalHoursWorked']),
                    'actual_work_amount' => $n($c['actualWorkAmount']),
                    'paid_leave_amount' => $n($c['paidLeaveAmount']),
                    'overtime_hours' => $n($c['overtimeHours']),
                    'overtime_rate' => $c['overtimeRate'],
                    'overtime_amount' => $n($c['overtimeAmount']),
                    'allowances_amount' => $n($c['allowances']),
                    'bonuses_amount' => $n($c['bonuses']),
                    'other_deductions_amount' => $n($c['otherDeductions']),
                    'items' => $c['items'],
                    'gross_salary' => $n($c['grossSalary']),
                    'absent_deduction' => $n($c['absentDays'] * $c['dailyRate']),
                    'unpaid_leave_deduction' => $n($c['unpaidLeaveDaysCount'] * $c['dailyRate']),
                    'deduction_amount' => $n($c['totalDeductions']),
                    'net_salary' => $n($c['netSalary']),
                    'include_contributions' => $includeContributions,
                    'contributions_share' => $c['contributionsShare'],
                    'sss_contribution' => $n($c['contributions']['sss']),
                    'philhealth_contribution' => $n($c['contributions']['philhealth']),
                    'pagibig_contribution' => $n($c['contributions']['pagibig']),
                    'total_contributions' => $n($c['contributions']['total']),
                    'overlaps' => $this->overlappingPayroll($ownerId, $employee->id, $periodStart, $periodEnd),
                ],
            ];
        } catch (ValidationException $e) {
            return ['success' => false, 'message' => collect($e->errors())->flatten()->first()];
        } catch (\Exception $e) {
            return ['success' => false, 'message' => 'Failed to preview payroll: ' . $e->getMessage()];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Approval
    // ─────────────────────────────────────────────────────────────────────────

    public function financeApprovePayrolls(int $ownerId, array $ids, ?string $notes = null, $actor = null): array
    {
        return $this->transition($ownerId, $ids, 'pending', 'approved', $notes, $actor, function (Payroll $p) use ($actor) {
            $p->fill(['approved_by_name' => $this->name($actor), 'approved_at' => now()]);
        });
    }

    public function financeRejectPayrolls(int $ownerId, array $ids, ?string $notes = null, $actor = null): array
    {
        return $this->transition($ownerId, $ids, 'pending', 'rejected', $notes, $actor);
    }

    public function markPayrollsAsPaid(int $ownerId, array $ids, $actor = null): array
    {
        return $this->transition($ownerId, $ids, 'approved', 'paid', null, $actor, function (Payroll $p) use ($actor) {
            $p->fill(['paid_at' => now(), 'paid_by_name' => $this->name($actor)]);
        });
    }

    /** Move payrolls between states. Approving or sending back your own payroll is skipped. */
    private function transition(int $ownerId, array $ids, string $from, string $to, ?string $notes, $actor, ?callable $extra = null): array
    {
        $payrolls = Payroll::withoutGlobalScopes()->where('owner_id', $ownerId)->whereIn('id', $ids)->where('status', $from)->get();
        $own = $from === 'pending' && $actor instanceof Employee
            ? $payrolls->where('created_by_employee_id', $actor->id)
            : collect();
        $payrolls = $payrolls->diff($own);
        if ($payrolls->isEmpty()) {
            return ['success' => false, 'message' => $own->isNotEmpty()
                ? 'You created these payrolls, so someone else has to approve or send them back.'
                : "No {$from} payrolls found for the selected IDs."];
        }

        DB::transaction(function () use ($payrolls, $to, $notes, $actor, $extra, $ownerId) {
            foreach ($payrolls as $p) {
                $p->status = $to;
                if ($to !== 'paid') {
                    $p->finance_notes = $notes;
                }
                $extra && $extra($p);
                $p->save();
                RequestEvent::log($ownerId, 'payroll', $p->id, $to, $this->name($actor), $notes);
            }
        });

        $verb = ['approved' => 'approved', 'rejected' => 'sent back', 'paid' => 'marked as paid'][$to];
        $skipped = count($ids) - $payrolls->count();

        return [
            'success' => true,
            'message' => "{$payrolls->count()} payroll(s) {$verb}." . ($skipped ? " {$skipped} skipped" . ($own->isNotEmpty() ? ' (you created them, or they were already decided).' : '.') : ''),
            'updated' => $payrolls->count(),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reading
    // ─────────────────────────────────────────────────────────────────────────

    public function getPayrolls(int $ownerId, array $filters = [])
    {
        return $this->applyPayrollFilters(Payroll::withoutGlobalScopes()->with('employee')->where('owner_id', $ownerId), $filters);
    }

    public function getFinancePayrolls(int $ownerId, array $filters = [])
    {
        return $this->getPayrolls($ownerId, $filters);
    }

    public function getPayrollSummary(int $ownerId, array $filters = []): array
    {
        $payrolls = $this->applyPayrollFiltersBuilder(Payroll::withoutGlobalScopes()->where('owner_id', $ownerId), $filters)->get();

        return [
            'total_payrolls' => $payrolls->count(),
            'total_net_salary' => number_format($payrolls->sum('net_salary'), 2),
            'total_gross_salary' => number_format($payrolls->sum('gross_salary'), 2),
            'total_deductions' => number_format($payrolls->sum('deduction_amount'), 2),
            'total_hours_worked' => number_format($payrolls->sum('total_hours_worked'), 2),
            'total_overtime_amount' => number_format($payrolls->sum('overtime_amount'), 2),
            'unique_employees' => $payrolls->unique('employee_id')->count(),
            'average_salary' => $payrolls->count() > 0 ? number_format($payrolls->sum('net_salary') / $payrolls->count(), 2) : '0.00',
            'pending_count' => $payrolls->where('status', 'pending')->count(),
            'approved_count' => $payrolls->where('status', 'approved')->count(),
            'rejected_count' => $payrolls->where('status', 'rejected')->count(),
            'paid_count' => $payrolls->where('status', 'paid')->count(),
            'total_pending_amount' => number_format($payrolls->where('status', 'pending')->sum('net_salary'), 2),
            'total_approved_amount' => number_format($payrolls->where('status', 'approved')->sum('net_salary'), 2),
            'total_paid_amount' => number_format($payrolls->where('status', 'paid')->sum('net_salary'), 2),
        ];
    }

    public function getFinanceSummary(int $ownerId, array $filters = []): array
    {
        return $this->getPayrollSummary($ownerId, $filters);
    }

    /** Everything a payslip shows, plus year-to-date totals and the history. */
    public function payslip(int $ownerId, Payroll $p): array
    {
        $p->loadMissing(['employee', 'items']);
        $shop = \App\Models\User::find($ownerId);
        $app = $shop?->vendorApplication;
        $year = $p->period_start->year;
        $ytd = Payroll::withoutGlobalScopes()->where('owner_id', $ownerId)->where('employee_id', $p->employee_id)
            ->whereIn('status', ['approved', 'paid'])->whereYear('period_start', $year)
            ->where('period_start', '<=', $p->period_start)->get();
        $f = fn ($v) => round((float) $v, 2);
        $contributions = $f($p->sss_contribution) + $f($p->philhealth_contribution) + $f($p->pagibig_contribution);

        return [
            'id' => $p->id,
            'number' => sprintf('PS-%d-%05d', $year, $p->id),
            'status' => $p->status,
            'shop' => ['name' => $app?->store_name ?: $shop?->name, 'logo' => $app?->store_logo_url, 'address' => $app?->store_address],
            'employee' => [
                'id' => $p->employee_id,
                'name' => $p->employee?->full_name ?? 'Former employee',
                'code' => $p->employee?->employee_id,
                'position' => $p->employee?->position,
                'department' => $p->employee?->department,
                'payment_method' => $p->employee?->payment_method,
                'bank' => $p->employee?->bank_name,
                'account' => $p->employee?->account_number ? '•••• ' . substr((string) $p->employee->account_number, -4) : null,
            ],
            'period_start' => $p->period_start->toDateString(),
            'period_end' => $p->period_end->toDateString(),
            'salary_type' => $p->salary_type,
            'basic_salary' => $f($p->basic_salary),
            'rates' => ['daily' => $f((float) $p->hourly_rate * (float) ($p->standard_work_hours_per_day ?: 8)), 'hourly' => $f($p->hourly_rate)],
            'attendance' => [
                'expected_days' => (int) $p->expected_work_days,
                'days_worked' => (int) $p->actual_work_days,
                'paid_leave_days' => (int) $p->paid_leave_days,
                'unpaid_leave_days' => (int) $p->unpaid_leave_days,
                'absent_days' => (int) $p->absent_days,
                'hours' => $f($p->total_hours_worked),
                'overtime_hours' => $f($p->overtime_hours),
                'late_minutes' => (int) $p->late_minutes,
                'undertime_minutes' => (int) $p->undertime_minutes,
            ],
            'earnings' => array_values(array_filter([
                ['label' => 'Basic pay (' . (int) $p->actual_work_days . ' days)', 'amount' => $f($p->actual_work_amount)],
                $f($p->paid_leave_amount) ? ['label' => 'Paid leave (' . (int) $p->paid_leave_days . ' days)', 'amount' => $f($p->paid_leave_amount)] : null,
                $f($p->overtime_amount) ? ['label' => 'Overtime (' . $f($p->overtime_hours) . ' h)', 'amount' => $f($p->overtime_amount)] : null,
                ...$p->items->whereIn('kind', ['allowance', 'bonus'])->map(fn ($i) => ['label' => $i->label, 'amount' => $f($i->amount)])->all(),
            ])),
            'deductions' => array_values(array_filter([
                $f($p->sss_contribution) ? ['label' => 'SSS', 'amount' => $f($p->sss_contribution)] : null,
                $f($p->philhealth_contribution) ? ['label' => 'PhilHealth', 'amount' => $f($p->philhealth_contribution)] : null,
                $f($p->pagibig_contribution) ? ['label' => 'Pag-IBIG', 'amount' => $f($p->pagibig_contribution)] : null,
                ...$p->items->where('kind', 'deduction')->map(fn ($i) => ['label' => $i->label, 'amount' => $f($i->amount)])->all(),
            ])),
            'items' => $p->items->map(fn ($i) => ['kind' => $i->kind, 'label' => $i->label, 'amount' => $f($i->amount), 'source' => $i->source])->values()->all(),
            'gross' => $f($p->gross_salary),
            'contributions' => $f($contributions),
            'total_deductions' => $f($p->deduction_amount),
            'net' => $f($p->net_salary),
            'include_contributions' => (bool) $p->include_contributions,
            'contributions_share' => $p->contributions_share,
            'ytd' => ['gross' => $f($ytd->sum('gross_salary')), 'deductions' => $f($ytd->sum('deduction_amount')), 'net' => $f($ytd->sum('net_salary'))],
            'notes' => $p->notes,
            'finance_notes' => $p->finance_notes,
            'created_by' => $p->created_by_name,
            'created_by_employee_id' => $p->created_by_employee_id,
            'approved_by' => $p->approved_by_name,
            'approved_at' => $p->approved_at?->toIso8601String(),
            'paid_by' => $p->paid_by_name,
            'paid_at' => $p->paid_at?->toIso8601String(),
            'created_at' => $p->created_at?->toIso8601String(),
            'history' => app(LeaveService::class)->history('payroll', $p->id),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Calculation
    // ─────────────────────────────────────────────────────────────────────────

    /** Why this person's pay can't be worked out yet, or null. */
    public function payConfigProblem(EmployeeInfo $employee): ?string
    {
        return match (true) {
            ! $employee->basic_salary || ! $employee->salary_type => "{$employee->full_name} has no salary set yet.",
            ! $employee->standard_work_hours_per_day => "{$employee->full_name} is missing hours a day.",
            $employee->salary_type === 'weekly' && ! $employee->working_days_per_week => "{$employee->full_name} is missing working days a week.",
            $employee->salary_type === 'monthly' && ! $employee->working_days_per_month => "{$employee->full_name} is missing working days a month.",
            default => null,
        };
    }

    private function calculate(int $ownerId, EmployeeInfo $employee, string $periodStart, string $periodEnd, array $manualItems, bool $includeContributions): array
    {
        $start = Carbon::parse($periodStart)->startOfDay();
        $end = Carbon::parse($periodEnd)->startOfDay();
        if ($end->lt($start)) {
            throw ValidationException::withMessages(['period_end' => 'The period must end on or after its start.']);
        }
        if ($start->diffInDays($end) > 62) {
            throw ValidationException::withMessages(['period_end' => 'A payroll covers up to two months.']);
        }

        $attendanceRecords = EmployeeAttendance::withoutGlobalScopes()->where('owner_id', $ownerId)
            ->where('employee_id', $employee->id)
            ->whereDate('attendance_date', '>=', $periodStart)
            ->whereDate('attendance_date', '<=', $periodEnd)
            ->where('status', 'complete')
            ->countable()
            ->whereNotNull('time_in')->whereNotNull('time_out')->whereNotNull('total_hours')
            ->get();

        $leave = app(LeaveService::class);
        $leaveDays = ['paid' => 0, 'unpaid' => 0];
        if (Schema::hasTable('employee_leaves')) {
            $leaves = EmployeeLeave::withoutGlobalScopes()->where('owner_id', $ownerId)->where('employee_id', $employee->id)
                ->where('status', 'approved')
                ->whereDate('start_date', '<=', $periodEnd)->whereDate('end_date', '>=', $periodStart)->get();
            foreach ($leaves as $l) {
                $from = Carbon::parse($l->start_date)->max($start);
                $to = Carbon::parse($l->end_date)->min($end);
                $leaveDays[$l->is_paid ? 'paid' : 'unpaid'] += $leave->workingDays($ownerId, $employee, $from, $to);
            }
        }

        $expectedWorkingDays = $leave->workingDays($ownerId, $employee, $start, $end);
        $actualWorkDays = $attendanceRecords->count();
        $paidLeaveDaysCount = $leaveDays['paid'];
        $unpaidLeaveDaysCount = $leaveDays['unpaid'];
        $absentDays = max(0, $expectedWorkingDays - $actualWorkDays - $paidLeaveDaysCount - $unpaidLeaveDaysCount);

        $hoursPerDay = (float) ($employee->standard_work_hours_per_day ?: 8);
        $hourlyRate = $this->calculateHourlyRate((float) $employee->basic_salary, $employee->salary_type, $hoursPerDay, $employee->working_days_per_week, $employee->working_days_per_month);
        $dailyRate = $hourlyRate * $hoursPerDay;

        $actualWorkHours = (float) $attendanceRecords->sum('total_hours');
        $paidLeaveHours = $paidLeaveDaysCount * $hoursPerDay;
        $totalHoursWorked = $actualWorkHours + $paidLeaveHours;
        $actualWorkAmount = round($actualWorkDays * $dailyRate, 2);
        $paidLeaveAmount = round($paidLeaveDaysCount * $dailyRate, 2);

        // Overtime: only minutes HR approved, at the shop's overtime rate (125% by default)
        $overtimeRate = app(AttendanceLocationService::class)->settings($ownerId)->overtimeRate();
        $overtimeHours = round($attendanceRecords->where('overtime_status', 'approved')->sum('overtime_approved_minutes') / 60, 2);
        $overtimeAmount = round($overtimeHours * $hourlyRate * $overtimeRate, 2);

        // Shown on the payslip for reference; pay is per day, so lateness isn't deducted automatically
        $facts = app(AttendanceCalculator::class)->report($ownerId, $start, $end, $employee->id)['totals'] ?? [];
        $lateMinutes = (int) ($facts['late_minutes'] ?? 0);
        $undertimeMinutes = (int) ($facts['undertime_minutes'] ?? 0);

        $items = array_merge($this->recurringItems($ownerId, $employee, $actualWorkDays), $this->manualItems($manualItems));
        $allowances = round(collect($items)->where('kind', 'allowance')->sum('amount'), 2);
        $bonuses = round(collect($items)->where('kind', 'bonus')->sum('amount'), 2);
        $otherDeductions = round(collect($items)->where('kind', 'deduction')->sum('amount'), 2);

        $contributionsShare = $includeContributions ? $this->contributionShare($start, $end) : 0.0;
        $contributions = $includeContributions
            ? $this->contributionService->computeAll($this->contributionService->toMonthlySalary((float) $employee->basic_salary, $employee->salary_type), $contributionsShare)
            : ['sss' => 0.0, 'philhealth' => 0.0, 'pagibig' => 0.0, 'total' => 0.0];

        $grossSalary = round($actualWorkAmount + $paidLeaveAmount + $overtimeAmount + $allowances + $bonuses, 2);
        $totalDeductions = round($contributions['total'] + $otherDeductions, 2);
        $netSalary = round(max(0, $grossSalary - $totalDeductions), 2);

        return compact(
            'expectedWorkingDays', 'actualWorkDays', 'paidLeaveDaysCount', 'unpaidLeaveDaysCount', 'absentDays',
            'actualWorkHours', 'paidLeaveHours', 'totalHoursWorked', 'hourlyRate', 'dailyRate', 'actualWorkAmount',
            'paidLeaveAmount', 'overtimeHours', 'overtimeAmount', 'overtimeRate', 'lateMinutes', 'undertimeMinutes',
            'items', 'allowances', 'bonuses', 'otherDeductions', 'contributionsShare', 'contributions',
            'grossSalary', 'totalDeductions', 'netSalary',
        );
    }

    /**
     * The part of a month a payroll covers, for monthly contributions: a 1–15 payroll takes
     * about half, a week about a quarter, a whole month all of it (never more than one month's worth).
     */
    public function contributionShare(Carbon $start, Carbon $end): float
    {
        $days = $start->diffInDays($end) + 1;

        return round(min(1, $days / $start->daysInMonth), 4);
    }

    private function recurringItems(int $ownerId, EmployeeInfo $employee, int $daysWorked): array
    {
        if (! Schema::hasTable('pay_components')) {
            return [];
        }

        return PayComponent::where('owner_id', $ownerId)->where('employee_info_id', $employee->id)->where('active', true)->get()
            ->map(function (PayComponent $c) use ($daysWorked) {
                $amount = $c->basis === 'per_day' ? $c->amount * $daysWorked : $c->amount;
                $label = $c->basis === 'per_day' ? "{$c->label} ({$daysWorked} days)" : $c->label;

                return ['kind' => $c->kind, 'label' => $label, 'amount' => round($amount, 2), 'source' => 'recurring'];
            })
            ->filter(fn ($i) => $i['amount'] > 0)->values()->all();
    }

    private function manualItems(array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            $kind = $i['kind'] ?? null;
            $amount = round((float) ($i['amount'] ?? 0), 2);
            $label = trim((string) ($i['label'] ?? ''));
            if (! in_array($kind, PayrollItem::KINDS, true) || $amount <= 0 || $label === '') {
                throw ValidationException::withMessages(['items' => 'Each extra line needs a kind, a name and an amount above zero.']);
            }
            $out[] = ['kind' => $kind, 'label' => mb_substr($label, 0, 80), 'amount' => $amount, 'source' => 'manual'];
        }

        return $out;
    }

    private function saveItems(Payroll $payroll, array $items): void
    {
        foreach ($items as $i) {
            $payroll->items()->create($i);
        }
    }

    private function attributes(int $ownerId, EmployeeInfo $employee, string $start, string $end, array $c): array
    {
        return [
            'owner_id' => $ownerId,
            'employee_id' => $employee->id,
            'period_start' => $start,
            'period_end' => $end,
            'salary_type' => $employee->salary_type,
            'basic_salary' => $employee->basic_salary,
            'standard_work_hours_per_day' => $employee->standard_work_hours_per_day,
            'working_days_per_week' => $employee->working_days_per_week,
            'working_days_per_month' => $employee->working_days_per_month,
            'total_hours_worked' => round($c['totalHoursWorked'], 2),
            'hourly_rate' => round($c['hourlyRate'], 2),
            'gross_salary' => $c['grossSalary'],
            'attendance_records_count' => $c['actualWorkDays'],
            'expected_work_days' => $c['expectedWorkingDays'],
            'actual_work_days' => $c['actualWorkDays'],
            'actual_work_amount' => $c['actualWorkAmount'],
            'paid_leave_days' => $c['paidLeaveDaysCount'],
            'paid_leave_amount' => $c['paidLeaveAmount'],
            'unpaid_leave_days' => $c['unpaidLeaveDaysCount'],
            'absent_days' => $c['absentDays'],
            'overtime_hours' => $c['overtimeHours'],
            'overtime_amount' => $c['overtimeAmount'],
            'allowances_amount' => $c['allowances'],
            'bonuses_amount' => $c['bonuses'],
            'other_deductions_amount' => $c['otherDeductions'],
            'contributions_share' => $c['contributionsShare'] ?: null,
            'late_minutes' => $c['lateMinutes'],
            'undertime_minutes' => $c['undertimeMinutes'],
            'deduction_amount' => $c['totalDeductions'],
            'net_salary' => $c['netSalary'],
            'sss_contribution' => $c['contributions']['sss'],
            'philhealth_contribution' => $c['contributions']['philhealth'],
            'pagibig_contribution' => $c['contributions']['pagibig'],
        ];
    }

    private function overlappingPayroll(int $ownerId, int $employeeId, string $start, string $end, ?int $except = null): bool
    {
        return Payroll::withoutGlobalScopes()->where('owner_id', $ownerId)->where('employee_id', $employeeId)
            ->where('status', '!=', 'rejected')
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->whereDate('period_start', '<=', $end)->whereDate('period_end', '>=', $start)->exists();
    }

    private function calculateHourlyRate(float $basicSalary, string $salaryType, float $standardHoursPerDay, ?int $workingDaysPerWeek, ?int $workingDaysPerMonth): float
    {
        $stdHours = $standardHoursPerDay > 0 ? $standardHoursPerDay : 8;
        $daysWeek = ($workingDaysPerWeek && $workingDaysPerWeek > 0) ? $workingDaysPerWeek : 5;
        $daysMonth = ($workingDaysPerMonth && $workingDaysPerMonth > 0) ? $workingDaysPerMonth : 22;

        return match ($salaryType) {
            'daily' => $basicSalary / $stdHours,
            'weekly' => $basicSalary / ($stdHours * $daysWeek),
            'monthly' => $basicSalary / ($stdHours * $daysMonth),
            default => throw new \Exception('Invalid salary type'),
        };
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filters
    // ─────────────────────────────────────────────────────────────────────────

    public function applyPayrollFiltersBuilder($query, array $filters)
    {
        if (! empty($filters['search'])) {
            $search = $filters['search'];
            // full_name is worked out in code, so search the name parts and the employee number
            $query->whereHas('employee', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_id', 'like', "%{$search}%");
            });
        }
        if (! empty($filters['employee_id'])) {
            $query->where('employee_id', $filters['employee_id']);
        }
        if (! empty($filters['status']) && $filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if (! empty($filters['month']) && ! empty($filters['year'])) {
            $query->whereMonth('period_start', $filters['month'])->whereYear('period_start', $filters['year']);
        } elseif (! empty($filters['month'])) {
            $query->whereMonth('period_start', $filters['month']);
        } elseif (! empty($filters['year'])) {
            $query->whereYear('period_start', $filters['year']);
        }

        return $query;
    }

    private function applyPayrollFilters($query, array $filters)
    {
        $query = $this->applyPayrollFiltersBuilder($query, $filters);
        $query->orderByRaw("CASE status WHEN 'pending' THEN 0 WHEN 'rejected' THEN 1 WHEN 'approved' THEN 2 ELSE 3 END")
            ->orderBy('period_start', 'desc')->orderBy('created_at', 'desc');

        return $query->paginate((int) ($filters['per_page'] ?? 15), ['*'], 'page', (int) ($filters['page'] ?? 1));
    }

    private function name($actor): string
    {
        return $actor instanceof Employee ? ($actor->name ?: 'HR') : 'Shop owner';
    }
}
