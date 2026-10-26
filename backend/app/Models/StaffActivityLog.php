<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something an employee changed, in plain words (no IDs or API routes). */
class StaffActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['owner_id', 'employee_id', 'actor_name', 'action', 'details', 'subject', 'area', 'method', 'status', 'ip'];

    protected $casts = ['created_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
