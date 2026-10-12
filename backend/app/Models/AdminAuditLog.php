<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One change (or export) an admin made */
class AdminAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'admin_id', 'admin_name', 'action', 'method', 'path', 'target_id', 'target_label',
        'status_code', 'details', 'ip_address', 'user_agent',
    ];

    protected $casts = ['details' => 'array', 'created_at' => 'datetime'];
}
