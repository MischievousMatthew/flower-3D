<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A slide in the shop's homepage hero */
class SiteBanner extends Model
{
    protected $fillable = [
        'eyebrow', 'title', 'subtitle', 'image_url', 'image_path', 'button_label',
        'link_type', 'link_value', 'starts_at', 'ends_at', 'is_active', 'sort_order',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    protected $hidden = ['image_path'];

    /** Switched on and inside its date window (if it has one) */
    public function scopeShowing(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
