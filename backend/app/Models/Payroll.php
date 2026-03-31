<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payroll extends Model
{

    protected $fillable = [
        'owner_id', 'employee_id', 'period_start', 'period_end',
        'salary_type', 'basic_salary', 'standard_work_hours_per_day',
        'working_days_per_week', 'working_days_per_month',
        'total_hours_worked', 'hourly_rate', 'gross_salary',
        'attendance_records_count', 'expected_work_days', 'actual_work_days',
        'paid_leave_days', 'unpaid_leave_days', 'absent_days',
        'deduction_amount', 'net_salary', 'notes', 'status', 'paid_at',
        'finance_notes',
        // Government contributions
        'include_contributions',
        'sss_contribution',
        'philhealth_contribution',
        'pagibig_contribution',
    ];

    protected $casts = [
        'period_start'                => 'date',
        'period_end'                  => 'date',
        'basic_salary'                => 'decimal:2',
        'standard_work_hours_per_day' => 'decimal:2',
        'total_hours_worked'          => 'decimal:2',
        'hourly_rate'                 => 'decimal:2',
        'gross_salary'                => 'decimal:2',
        'deduction_amount'            => 'decimal:2',
        'net_salary'                  => 'decimal:2',
        'sss_contribution'            => 'decimal:2',
        'philhealth_contribution'     => 'decimal:2',
        'pagibig_contribution'        => 'decimal:2',
        'include_contributions'       => 'boolean',
        'paid_at'                     => 'datetime',
        'created_at'                  => 'datetime',
        'updated_at'                  => 'datetime',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function employee()
    {
        return $this->belongsTo(EmployeeInfo::class, 'employee_id');
    }

    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeForPeriod($query, $start, $end)
    {
        return $query->where('period_start', '>=', $start)
                     ->where('period_end', '<=', $end);
    }

    public function scopeForMonth($query, $month, $year)
    {
        return $query->whereMonth('period_start', $month)
                     ->whereYear('period_start', $year);
    }

    public function scopeForYear($query, $year)
    {
        return $query->whereYear('period_start', $year);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFinanceApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'pending'          => 'Pending',
            'approved'         => 'Approved',
            'rejected'         => 'Rejected',
            'paid'             => 'Paid',
            default            => ucfirst($this->status),
        };
    }
}