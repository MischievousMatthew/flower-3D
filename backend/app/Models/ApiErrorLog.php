<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An API request that ended in a server error (5xx), or a programmer error a person was shielded from */
class ApiErrorLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['method', 'path', 'action', 'status_code', 'exception', 'message', 'user_id', 'ip_address', 'duration_ms', 'reference', 'account_type', 'account_name', 'employee_id', 'location', 'shown_message'];

    protected $casts = ['created_at' => 'datetime'];
}
