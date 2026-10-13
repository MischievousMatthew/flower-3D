<?php

namespace App\Models;

use App\Helpers\CloudinaryHelper;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in a return/refund case: a message, an offer or a step taken */
class OrderRequestMessage extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'order_request_id', 'author_role', 'author_name', 'author_user_id', 'author_employee_id',
        'kind', 'body', 'resolution', 'amount', 'media_path', 'media_type',
    ];

    protected $casts = ['amount' => 'float', 'created_at' => 'datetime'];

    public function orderRequest(): BelongsTo
    {
        return $this->belongsTo(OrderRequest::class);
    }

    public function getMediaUrlAttribute(): ?string
    {
        return $this->media_path
            ? CloudinaryHelper::getUrl($this->media_path, $this->media_type === 'video' ? 'video' : 'image')
            : null;
    }
}
