<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Days HR added to or took from someone's leave for a year (carry-over, corrections). */
class LeaveBalanceAdjustment extends Model
{
    protected $fillable = ['owner_id', 'employee_info_id', 'leave_type', 'year', 'days', 'note', 'adjusted_by'];

    protected $casts = ['days' => 'float', 'year' => 'integer'];
}
