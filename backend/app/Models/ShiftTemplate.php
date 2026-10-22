<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A named shift people are linked to, e.g. "Morning counter, 8:00–17:00, needs 2, Mon–Sat". */
class ShiftTemplate extends Model
{
    protected $fillable = ['owner_id', 'name', 'start_time', 'end_time', 'color', 'headcount', 'days', 'note', 'archived_at'];

    protected $casts = [
        'days' => 'array',
        'headcount' => 'integer',
        'archived_at' => 'datetime',
    ];

    public function shifts(): HasMany
    {
        return $this->hasMany(StaffShift::class);
    }

    public function runsOn(string $date): bool
    {
        return in_array(\Carbon\Carbon::parse($date)->format('l'), $this->days ?? [], true);
    }
}
