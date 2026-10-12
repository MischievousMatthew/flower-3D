<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A BloomCraft Journal post: flower care, guides, trends, stories */
class Article extends Model
{
    public const CATEGORIES = [
        'care' => 'Flower care',
        'guide' => 'Seasonal guide',
        'trends' => 'Trends',
        'stories' => 'Vendor stories',
        'news' => 'News',
    ];

    protected $fillable = [
        'title', 'slug', 'category', 'excerpt', 'body', 'cover_url', 'cover_path',
        'status', 'published_at', 'is_featured', 'author_id',
    ];

    protected $casts = ['published_at' => 'datetime', 'is_featured' => 'boolean', 'views' => 'integer'];

    protected $hidden = ['cover_path'];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** Published and not scheduled for later */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at')->where('published_at', '<=', now());
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body)) / 200));
    }
}
