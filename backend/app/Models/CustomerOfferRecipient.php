<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One customer's copy of an offer (what they see on their side) */
class CustomerOfferRecipient extends Model
{
    protected $fillable = ['offer_id', 'user_id', 'vendor_id', 'seen_at'];

    protected $casts = ['seen_at' => 'datetime'];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(CustomerOffer::class, 'offer_id');
    }
}
