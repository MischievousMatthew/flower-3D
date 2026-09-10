<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TwoFactorManagementChallenge extends Model
{
    protected $fillable = [
        'user_id',
        'session_token_hash',
        'action',
        'email_otp_verified_at',
        'authorized_at',
        'passkey_attempts',
        'is_locked',
        'expires_at',
    ];

    protected $casts = [
        'email_otp_verified_at' => 'datetime',
        'authorized_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_locked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
