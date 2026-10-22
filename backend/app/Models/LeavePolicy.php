<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A shop's rule for one kind of leave: yearly allowance, paid or not, document needed. */
class LeavePolicy extends Model
{
    /**
     * Every leave type, with the defaults a shop starts with (editable per shop).
     * [label, days a year (null = no limit), paid, needs a document]
     * Paid flags match what payroll paid before rules existed.
     */
    public const TYPES = [
        'vacation_leave'    => ['Vacation leave', 5, true, false],
        'sick_leave'        => ['Sick leave', 5, true, false],
        'emergency_leave'   => ['Emergency leave', 3, false, false],
        'bereavement_leave' => ['Bereavement leave', 3, true, true],
        'maternity_leave'   => ['Maternity leave', 105, true, true],
        'paternity_leave'   => ['Paternity leave', 7, true, true],
        'unpaid_leave'      => ['Unpaid leave', null, false, false],
        'other'             => ['Other leave', null, false, false],
    ];

    protected $fillable = ['owner_id', 'leave_type', 'annual_days', 'is_paid', 'enabled', 'requires_document'];

    protected $casts = [
        'annual_days' => 'float',
        'is_paid' => 'boolean',
        'enabled' => 'boolean',
        'requires_document' => 'boolean',
    ];

    public static function label(string $type): string
    {
        return self::TYPES[$type][0] ?? ucwords(str_replace('_', ' ', $type));
    }
}
