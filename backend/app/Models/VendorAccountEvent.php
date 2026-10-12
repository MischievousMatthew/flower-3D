<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A vendor account being suspended or reactivated */
class VendorAccountEvent extends Model
{
    public const SUSPENDED = 'suspended';
    public const REACTIVATED = 'reactivated';

    protected $fillable = ['vendor_id', 'admin_id', 'action', 'reason'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
