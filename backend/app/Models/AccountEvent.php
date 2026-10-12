<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something an admin did to a vendor or customer account: suspending it,
 * reactivating it, or viewing a customer's personal details.
 */
class AccountEvent extends Model
{
    public const SUSPENDED = 'suspended';
    public const REACTIVATED = 'reactivated';
    public const CONTACT_VIEWED = 'contact_viewed';

    protected $fillable = ['user_id', 'admin_id', 'action', 'reason'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
