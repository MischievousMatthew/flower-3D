<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EmployeeAttendance extends Model
{
    use BelongsToOwner, HasFactory;

    protected $table = 'employee_attendance';

    protected $fillable = [
        'owner_id',
        'employee_id',
        'attendance_date',
        'day',
        'month',
        'year',
        'time_in',
        'time_out',
        'total_hours',
        'status',
        'notes',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'total_hours'     => 'decimal:2',
    ];

    protected $appends = ['employee_name', 'formatted_date', 'formatted_time_in', 'formatted_time_out'];

    /**
     * Get the owner that owns the attendance record.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the employee that owns the attendance record.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeInfo::class, 'employee_id');
    }

    /**
     * Get employee name accessor
     */
    public function getEmployeeNameAttribute(): ?string
    {
        return $this->employee ? $this->employee->full_name : null;
    }

    /**
     * Get formatted date
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->attendance_date ? $this->attendance_date->format('M d, Y') : '';
    }

    /**
     * Get formatted time in
     */
    public function getFormattedTimeInAttribute(): string
    {
        return $this->time_in
            ? Carbon::createFromFormat('H:i:s', $this->time_in)->format('h:i A')
            : '';
    }

    /**
     * Get formatted time out
     */
    public function getFormattedTimeOutAttribute(): ?string
    {
        return $this->time_out
            ? Carbon::createFromFormat('H:i:s', $this->time_out)->format('h:i A')
            : null;
    }

    /**
     * Calculate total hours worked
     */
    public function calculateTotalHours(): void
    {
        if ($this->time_in && $this->time_out) {
            $date = $this->attendance_date
                ? $this->attendance_date->toDateString()
                : now()->toDateString();

            $timeIn  = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' ' . $this->time_in);
            $timeOut = Carbon::createFromFormat('Y-m-d H:i:s', $date . ' ' . $this->time_out);

            $this->total_hours = round(abs($timeOut->timestamp - $timeIn->timestamp) / 3600, 2);
            $this->status = 'complete';
        }
    }

    /**
     * Scope to filter by owner
     */
    public function scopeForOwner($query, $ownerId)
    {
        return $query->where('owner_id', $ownerId);
    }

    /**
     * Scope to filter by employee
     */
    public function scopeForEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('attendance_date', [$startDate, $endDate]);
    }

    /**
     * Scope to filter by month and year
     */
    public function scopeMonthYear($query, $month, $year)
    {
        return $query->where('month', $month)->where('year', $year);
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }
}
