<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One line on a payslip besides the base pay: an allowance, a bonus or a deduction. */
class PayrollItem extends Model
{
    public const KINDS = ['allowance', 'bonus', 'deduction'];

    protected $fillable = ['payroll_id', 'kind', 'label', 'amount', 'source'];

    protected $casts = ['amount' => 'float'];
}
