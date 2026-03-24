<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Otp extends Model
{
    protected $fillable = ['contact_number', 'otp', 'expires_at', 'attempts'];
    
    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return $this->expires_at < now();
    }

    public function isValid(string $otp): bool
    {
        return !$this->isExpired() && $this->otp === $otp;
    }

    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }
}