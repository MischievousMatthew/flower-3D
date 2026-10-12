<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A message across the top of the shop, or a promotion card */
class Announcement extends Model
{
    public const ANNOUNCEMENT = 'announcement';
    public const PROMOTION = 'promotion';

    protected $fillable = [
        'kind', 'title', 'message', 'promo_code', 'image_url', 'image_path', 'link_label',
        'link_type', 'link_value', 'tone', 'starts_at', 'ends_at', 'is_active', 'sort_order',
    ];

    protected $casts = ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'is_active' => 'boolean', 'sort_order' => 'integer'];

    protected $hidden = ['image_path'];

    public function scopeShowing(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }
}
