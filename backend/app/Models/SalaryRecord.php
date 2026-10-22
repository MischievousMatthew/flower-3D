<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A salary rate someone had from a date: the history behind their current pay. */
class SalaryRecord extends Model
{
    protected $fillable = ['owner_id', 'employee_info_id', 'basic_salary', 'salary_type', 'effective_from', 'changed_by', 'note'];

    protected $casts = ['basic_salary' => 'float', 'effective_from' => 'date'];
}
