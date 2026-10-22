<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One QR scan at a kiosk: the challenges the server picked, valid for a short time and only once. */
class AttendanceSession extends Model
{
    use HasUuids;

    public const TTL_SECONDS = 90;

    protected $fillable = [
        'owner_id', 'device_id', 'employee_info_id', 'purpose', 'challenges', 'flash', 'flags',
        'operator_employee_id', 'operator_name', 'expires_at', 'used_at',
    ];

    protected $casts = [
        'challenges' => 'array',
        'flash' => 'array',
        'flags' => 'array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeInfo::class, 'employee_info_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'device_id');
    }
}
