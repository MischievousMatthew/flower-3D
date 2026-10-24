<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** A shop's internal note about a customer. Never shown to the customer. */
class CustomerNote extends Model
{
    protected $fillable = ['vendor_id', 'customer_id', 'body', 'pinned', 'author_type', 'author_id', 'author_name'];

    protected $casts = ['pinned' => 'boolean'];
}
