<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorClosedDate extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'closed_date',
        'reason',
        'type',
    ];

    protected $casts = [
        'closed_date' => 'date',
    ];

    // Relationships
    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    // Scopes
    public function scopeForVendor($query, $vendorId)
    {
        return $query->where('vendor_id', $vendorId);
    }

    public function scopeUpcoming($query)
    {
        return $query->where('closed_date', '>=', now()->toDateString());
    }

    public function scopeInRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('closed_date', [$startDate, $endDate]);
    }

    // Helper methods
    public static function isDateClosed($vendorId, $date): bool
    {
        return static::where('vendor_id', $vendorId)
            ->where('closed_date', $date)
            ->exists();
    }

    public static function getClosedDatesInRange($vendorId, $startDate, $endDate): array
    {
        return static::where('vendor_id', $vendorId)
            ->whereBetween('closed_date', [$startDate, $endDate])
            ->pluck('closed_date')
            ->map(fn($date) => $date->format('Y-m-d'))
            ->toArray();
    }
}