<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorBalance extends Model
{
    protected $fillable = [
        'vendor_id',
        'balance',
        'total_earned',
        'total_withdrawn',
    ];

    protected $casts = [
        'balance'          => 'decimal:2',
        'total_earned'     => 'decimal:2',
        'total_withdrawn'  => 'decimal:2',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(VendorTransaction::class, 'vendor_id', 'vendor_id');
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    /**
     * Get or create the balance record for a vendor.
     */
    public static function forVendor(int $vendorId): self
    {
        return static::firstOrCreate(
            ['vendor_id' => $vendorId],
            ['balance' => 0, 'total_earned' => 0, 'total_withdrawn' => 0]
        );
    }
}