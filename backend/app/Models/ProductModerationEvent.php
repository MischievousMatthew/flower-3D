<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An admin approving, hiding, removing or restoring a listing (or a vendor resubmitting it) */
class ProductModerationEvent extends Model
{
    protected $fillable = ['product_id', 'admin_id', 'action', 'reason'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
