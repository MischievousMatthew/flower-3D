<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A failed sign-in */
class LoginAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['identifier', 'user_id', 'actor_type', 'reason', 'ip_address', 'user_agent'];

    protected $casts = ['created_at' => 'datetime'];
}
