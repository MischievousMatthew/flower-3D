<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportMessage extends Model
{
    protected $fillable = ['ticket_id', 'author_id', 'from_admin', 'from_shop', 'is_internal', 'is_automated', 'author_name', 'body', 'attachments'];

    // from_shop: written by the shop's team (author_name says who); is_internal: a team-only note
    protected $casts = ['from_admin' => 'boolean', 'from_shop' => 'boolean', 'is_internal' => 'boolean', 'is_automated' => 'boolean', 'attachments' => 'array'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
