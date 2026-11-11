<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * The journal teasers on the landing page and shop: up to $count published
     * posts, each from a different category (the featured or newest one of
     * each). With fewer categories than that, the next newest posts fill in.
     */
    public static function teasers(int $count = 3): Collection
    {
        $latest = static::published()->orderByDesc('is_featured')->latest('published_at')->limit(50)->get();

        return static::pickTeasers($latest, $count);
    }

    /** @param Collection<int, Article> $articles already in order of preference */
    public static function pickTeasers(Collection $articles, int $count = 3): Collection
    {
        $picked = $articles->unique('category')->take($count);
        if ($picked->count() < $count) {
            $ids = $picked->pluck('id')->all();
            $picked = $picked->concat($articles->reject(fn ($article) => in_array($article->id, $ids, true))->take($count - $picked->count()));
        }

        return $picked->values();
    }

    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count(strip_tags((string) $this->body)) / 200));
    }
}
