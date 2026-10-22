<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Where the shop is and how close a kiosk must be to take attendance. One row per shop. */
class AttendanceSetting extends Model
{
    public const DEFAULT_RADIUS_M = 100;
    public const DEFAULT_LATE_GRACE = 10;

    public const DEFAULT_OVERTIME_MIN = 15;
    public const DEFAULT_OVERTIME_RATE = 1.25; // 125% of the hourly rate, the Labor Code minimum on a regular day

    protected $fillable = ['owner_id', 'latitude', 'longitude', 'radius_m', 'late_grace_minutes', 'early_clock_in_minutes', 'overtime_min_minutes', 'overtime_rate'];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'radius_m' => 'integer',
        'late_grace_minutes' => 'integer',
        'early_clock_in_minutes' => 'integer',
        'overtime_min_minutes' => 'integer',
        'overtime_rate' => 'float',
    ];

    // Defaults for rows made before these settings existed (and for SQLite test tables without them)
    public function earlyMinutes(): int
    {
        return (int) ($this->early_clock_in_minutes ?? 0);
    }

    public function overtimeMinimum(): int
    {
        return (int) ($this->overtime_min_minutes ?? self::DEFAULT_OVERTIME_MIN);
    }

    public function overtimeRate(): float
    {
        return (float) ($this->overtime_rate ?? self::DEFAULT_OVERTIME_RATE);
    }

    public function hasLocation(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
