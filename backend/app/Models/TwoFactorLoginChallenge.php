<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorLoginChallenge extends Model
{
    protected $fillable = [
        'user_id', 'challenge_token_hash', 'passkey_attempts', 'is_locked', 'expires_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
