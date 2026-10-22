<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Overtime asked for ahead of time. Once approved, staying late that day up to the approved
 * minutes is approved automatically when the person times out.
 */
class OvertimeRequest extends Model
{
    protected $fillable = [
        'owner_id', 'employee_info_id', 'date', 'minutes', 'reason', 'status',
        'filed_by', 'filed_by_name', 'reviewer_name', 'reviewed_at', 'review_note',
    ];

    protected $casts = ['date' => 'date', 'minutes' => 'integer', 'reviewed_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeInfo::class, 'employee_info_id');
    }
}
