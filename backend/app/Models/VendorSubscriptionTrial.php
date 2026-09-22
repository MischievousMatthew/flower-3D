<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorSubscriptionTrial extends Model
{
    use HasFactory;

    protected $fillable = ['vendor_id', 'vendor_subscription_id', 'plan_key', 'started_at', 'ends_at'];

    protected $casts = ['started_at' => 'datetime', 'ends_at' => 'datetime'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(VendorSubscription::class, 'vendor_subscription_id');
    }
}
