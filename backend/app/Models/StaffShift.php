<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone's hours on one day: a shift they're linked to, custom hours, or a day off.
 * One per person per day, so nobody can be booked twice. A "replaced" shift is one someone
 * else covered; after a no-show the person still counts as absent, otherwise they're excused.
 */
class StaffShift extends Model
{
    public const REPLACE_REASONS = ['no_show', 'sick', 'swap', 'other'];

    protected $fillable = [
        'owner_id', 'employee_id', 'shift_date', 'start_time', 'end_time', 'is_off', 'label', 'note',
        'shift_template_id', 'status', 'replaced_reason', 'replaced_note', 'replaced_by_employee_id', 'replaces_shift_id', 'assigned_by',
    ];

    protected $casts = ['shift_date' => 'date', 'is_off' => 'boolean'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeInfo::class, 'employee_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    /** Shifts from one day to another, inclusive, whatever time part the database keeps on dates */
    public function scopeOnDays($query, string $from, string $to)
    {
        return $query->whereDate('shift_date', '>=', $from)->whereDate('shift_date', '<=', $to);
    }

    public function isReplaced(): bool
    {
        return $this->status === 'replaced';
    }
}
