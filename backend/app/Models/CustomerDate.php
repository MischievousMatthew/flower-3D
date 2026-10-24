<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/** A birthday, anniversary or other day: the customer's own (vendor_id null) or one a shop recorded */
class CustomerDate extends Model
{
    public const KINDS = ['birthday', 'anniversary', 'other'];

    protected $fillable = ['user_id', 'vendor_id', 'kind', 'label', 'month', 'day', 'year', 'note', 'added_by'];

    protected $casts = ['month' => 'integer', 'day' => 'integer', 'year' => 'integer'];

    /** The next time this day comes round, today included (Feb 29 falls on Feb 28 in other years) */
    public function nextOccurrence(?Carbon $from = null): Carbon
    {
        $from = ($from ?? Carbon::today())->copy()->startOfDay();
        $on = function (int $year) {
            $day = min($this->day, Carbon::create($year, $this->month, 1)->daysInMonth);

            return Carbon::create($year, $this->month, $day)->startOfDay();
        };
        $next = $on($from->year);

        return $next->lt($from) ? $on($from->year + 1) : $next;
    }
}
