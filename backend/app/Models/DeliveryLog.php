<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class DeliveryLog extends Model
{
    use BelongsToOwner;

    // Append-only — no updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'owner_id',
        'delivery_id',
        'status',
        'scanned_by',
        'actor_name',
        'scanned_at',
        'notes',
    ];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Whoever is signed in moved it (owner, staff or customer). Scheduled
        // jobs have nobody signed in, and those stay "System".
        static::creating(function (DeliveryLog $log) {
            // (a step is saved a few times per order, so this check is cheap enough)
            if (! Schema::hasColumn($log->getTable(), 'actor_name')) {
                unset($log->actor_name);

                return;
            }
            if (! $log->actor_name && ($user = Auth::user())) {
                $name = trim(($user->name ?? '') . ' ' . ($user->surname ?? ''));
                $log->actor_name = $name !== '' ? mb_substr($name, 0, 120) : null;
            }
        });
    }

    /** Who did it: the scanning employee, else the signed-in person, else null (automatic) */
    public function actorLabel(): ?string
    {
        return $this->scanner?->name ?: ($this->actor_name ?: null);
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Employee::class, 'scanned_by');
    }
}
