<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** An offer a shop sent to one customer segment */
class CustomerOffer extends Model
{
    protected $fillable = ['vendor_id', 'segment', 'title', 'body', 'promotion_id', 'product_id', 'recipients', 'sent_by'];

    public function deliveries(): HasMany
    {
        return $this->hasMany(CustomerOfferRecipient::class, 'offer_id');
    }
}
