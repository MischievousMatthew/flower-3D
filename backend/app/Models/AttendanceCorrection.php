<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A request to fix or add a day's times. Someone else approves it before it changes the record. */
class AttendanceCorrection extends Model
{
    protected $fillable = [
        'owner_id', 'employee_info_id', 'attendance_id', 'date', 'time_in', 'time_out', 'reason',
        'requested_by', 'requested_by_employee_id', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected $casts = [
        'date' => 'date',
        'reviewed_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeInfo::class, 'employee_info_id');
    }
}
