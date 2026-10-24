<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** What one shop has noted a customer likes. The customer can see it and remove it. */
class VendorCustomerProfile extends Model
{
    protected $fillable = ['vendor_id', 'customer_id', 'flowers', 'colors', 'avoid', 'occasions', 'favorite_product_ids', 'updated_by'];

    protected $casts = [
        'flowers' => 'array',
        'colors' => 'array',
        'avoid' => 'array',
        'occasions' => 'array',
        'favorite_product_ids' => 'array',
    ];
}
