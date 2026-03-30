<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeLeave extends Model
{
    protected $fillable = [
        'owner_id',
        'employee_id',
        'start_date',
        'end_date',
        'total_days',
        'leave_type',
        'is_paid',
        'status',
        'reason',
        'admin_notes',
        'submission_token',
        'submission_ip',
        'submission_device',
        'submitted_at',
        'reviewed_by',
        'reviewed_at'
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_paid' => 'boolean',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    // Relationships
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeInfo::class, 'employee_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewed_by');
    }

    // Scopes
    public function scopeForOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopePaid($query)
    {
        return $query->where('is_paid', true);
    }

    public function scopeUnpaid($query)
    {
        return $query->where('is_paid', false);
    }

    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->where(function ($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function ($q2) use ($startDate, $endDate) {
                  $q2->where('start_date', '<=', $startDate)
                     ->where('end_date', '>=', $endDate);
              });
        });
    }

    /**
     * Determine if leave type is paid by default
     */
    public static function isPaidLeaveType(string $leaveType): bool
    {
        $paidTypes = [
            'sick_leave',
            'vacation_leave',
            'maternity_leave',
            'paternity_leave',
            'bereavement_leave'
        ];

        return in_array($leaveType, $paidTypes);
    }

    /**
     * Boot method to auto-set is_paid based on leave_type
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($leave) {
            if ($leave->is_paid === null) {
                $leave->is_paid = self::isPaidLeaveType($leave->leave_type);
            }
        });
    }
}