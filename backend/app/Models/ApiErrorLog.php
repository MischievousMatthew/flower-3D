<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** An API request that ended in a server error (5xx) */
class ApiErrorLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['method', 'path', 'status_code', 'exception', 'message', 'user_id', 'ip_address', 'duration_ms'];

    protected $casts = ['created_at' => 'datetime'];
}
