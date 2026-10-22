<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A phone, tablet or computer the shop owner allowed to run the attendance scanner. */
class AttendanceDevice extends Model
{
    public const MAX_APPROVED = 3;

    protected $fillable = [
        'owner_id', 'name', 'token_hash', 'status', 'requested_by', 'requested_by_employee_id',
        'request_latitude', 'request_longitude', 'request_distance_m', 'user_agent', 'approved_at', 'last_used_at',
    ];

    protected $hidden = ['token_hash'];

    protected $casts = [
        'request_latitude' => 'float',
        'request_longitude' => 'float',
        'request_distance_m' => 'integer',
        'approved_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
