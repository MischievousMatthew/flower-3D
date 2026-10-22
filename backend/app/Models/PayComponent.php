<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An allowance or deduction added to every payroll for one person (or per day they worked). */
class PayComponent extends Model
{
    protected $fillable = ['owner_id', 'employee_info_id', 'kind', 'label', 'amount', 'basis', 'active', 'note', 'created_by'];

    protected $casts = ['amount' => 'float', 'active' => 'boolean'];
}
