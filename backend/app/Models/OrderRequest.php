<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OrderRequest extends Model
{
    protected $fillable = [
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
            ? Storage::url($this->media_path)
            : null;
    }

    public function isPending(): bool  { return $this->status === 'pending';  }
    public function isApproved(): bool { return $this->status === 'approved'; }
    public function isRejected(): bool { return $this->status === 'rejected'; }
}