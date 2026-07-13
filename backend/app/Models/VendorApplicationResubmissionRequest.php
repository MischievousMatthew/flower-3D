<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorApplicationResubmissionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_application_id', 'token_hash', 'status', 'expires_at', 'submitted_at', 'requested_by',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(VendorApplication::class, 'vendor_application_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(VendorApplicationResubmission::class, 'resubmission_request_id');
    }
}
