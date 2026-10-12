<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An admin's private investigation note on an order (never shown to the customer or vendor) */
class OrderAdminNote extends Model
{
    protected $fillable = ['order_id', 'admin_id', 'body'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
