<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Helpers\CloudinaryHelper;

class ProductReview extends Model
{
    protected $fillable = [
        'product_id',
        'user_id',
        'order_id',
        'rating',
        'comment',
        'image_path',
        'is_anonymous',
    ];

    protected $casts = [
        'rating'       => 'integer',
        'is_anonymous' => 'boolean',
    ];

    // ─── Relationships ────────────────────────────────────────────────────────

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    // ─── Accessors ────────────────────────────────────────────────────────────

    /**
     * Resolved reviewer name — respects anonymous flag.
     */
    public function getReviewerNameAttribute(): string
    {
        if ($this->is_anonymous) {
            return 'Anonymous User';
        }
        return $this->user?->name ?? 'Customer';
    }

    /**
     * Full public URL for the review image.
     */
    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path
            ? CloudinaryHelper::getUrl($this->image_path)
            : null;
    }


    // ─── Scopes ───────────────────────────────────────────────────────────────

    public function scopeForProduct($query, int $productId)
    {
        return $query->where('product_id', $productId);
    }

    public function scopeVisible($query)
    {
        return $query->whereNotNull('rating');
    }
}