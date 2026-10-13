<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One HR profile's working hours on one day (or a day off), overriding their usual hours. */
class StaffShift extends Model
{
    protected $fillable = ['owner_id', 'employee_id', 'shift_date', 'start_time', 'end_time', 'is_off', 'label', 'note'];

    protected $casts = ['shift_date' => 'date', 'is_off' => 'boolean'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeInfo::class, 'employee_id');
    }
}
