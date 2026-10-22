<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One step in a leave or overtime request's history: filed, approved, rejected, cancelled… */
class RequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['owner_id', 'subject_type', 'subject_id', 'action', 'actor_name', 'note'];

    public static function log(int $ownerId, string $type, int $id, string $action, ?string $actor, ?string $note = null): void
    {
        static::create(['owner_id' => $ownerId, 'subject_type' => $type, 'subject_id' => $id, 'action' => $action, 'actor_name' => $actor, 'note' => $note]);
    }
}
