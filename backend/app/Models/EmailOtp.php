<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class EmailOtp extends Model
{
    protected $fillable = [
        'email', 'otp_hash', 'expires_at', 'attempts', 'is_locked', 'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'is_locked'  => 'boolean',
    ];

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function isValid(string $plainOtp): bool
    {
        return !$this->isExpired()
            && !$this->is_locked
            && Hash::check($plainOtp, $this->otp_hash);
    }

    public function incrementAttempts(int $maxAttempts = 5): void
    {
        $this->increment('attempts');
        if ($this->attempts >= $maxAttempts) {
            $this->update(['is_locked' => true]);
        }
    }
}