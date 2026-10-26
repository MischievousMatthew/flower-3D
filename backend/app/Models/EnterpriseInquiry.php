<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Someone asking about the Enterprise plan ("Talk to us") */
class EnterpriseInquiry extends Model
{
    public const STATUSES = [
        'new' => 'New',
        'contacted' => 'Contacted',
        'won' => 'Now on Enterprise',
        'closed' => 'Closed',
    ];

    protected $fillable = ['vendor_id', 'name', 'shop_name', 'email', 'phone', 'branches', 'message', 'status', 'admin_note', 'handled_by', 'handled_at'];

    protected $casts = ['branches' => 'integer', 'handled_at' => 'datetime'];

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
