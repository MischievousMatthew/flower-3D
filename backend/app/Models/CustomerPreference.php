<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What a customer says about themselves; shops see it only when share_with_shops is on */
class CustomerPreference extends Model
{
    protected $primaryKey = 'user_id';
    public $incrementing = false;

    protected $fillable = ['user_id', 'flowers', 'colors', 'avoid', 'occasions', 'note', 'share_with_shops', 'accept_offers', 'muted_offer_vendor_ids', 'offers_consent_at', 'offers_consent_source', 'offers_asked_at'];

    protected $casts = [
        'flowers' => 'array',
        'colors' => 'array',
        'avoid' => 'array',
        'occasions' => 'array',
        'share_with_shops' => 'boolean',
        'accept_offers' => 'boolean',
        'muted_offer_vendor_ids' => 'array',
        'offers_consent_at' => 'datetime',
        'offers_asked_at' => 'datetime',
    ];
}
