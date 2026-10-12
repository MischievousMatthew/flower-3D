<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An announcement an admin sent to vendors and/or customers */
class Broadcast extends Model
{
    public const AUDIENCES = ['vendors', 'customers', 'all'];

    protected $fillable = ['audience', 'title', 'body', 'link_url', 'priority', 'recipients', 'sent_by'];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
