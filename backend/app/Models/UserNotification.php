<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One item in a person's notification inbox */
class UserNotification extends Model
{
    protected $fillable = ['user_id', 'broadcast_id', 'type', 'event_key', 'title', 'body', 'link_url', 'important', 'read_at'];

    protected $casts = ['important' => 'boolean', 'read_at' => 'datetime'];
}
