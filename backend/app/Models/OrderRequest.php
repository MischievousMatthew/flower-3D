<?php

namespace App\Models;

use App\Traits\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\CloudinaryHelper;

class OrderRequest extends Model
{
    use BelongsToOwner;

    protected $fillable = [
        'owner_id',
        'order_id',
        'user_id',
        'type',
        'reason',
        'media_path',
        'media_type',
        'status',
        'admin_notes',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getMediaUrlAttribute(): ?string
    {
        return $this->media_path
            ? CloudinaryHelper::getUrl($this->media_path, $this->media_type === 'image' ? 'image' : 'raw')
            : null;
    }


    public function isPending(): bool  { return $this->status === 'pending';  }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
}
