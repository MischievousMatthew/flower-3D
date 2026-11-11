<?php

namespace Tests\Unit;

use App\Models\Article;
use Illuminate\Database\Eloquent\Collection;
use PHPUnit\Framework\TestCase;

class ArticleTeasersTest extends TestCase
{
    private function posts(array $categories): Collection
    {
        return new Collection(array_map(function ($category, $i) {
            $article = new Article(['category' => $category, 'title' => "Post {$i}"]);
            $article->id = $i + 1;

            return $article;
        }, $categories, array_keys($categories)));
    }

    public function test_one_post_from_each_category(): void
    {
        $picked = Article::pickTeasers($this->posts(['care', 'care', 'guide', 'stories', 'trends']));

        $this->assertSame(['care', 'guide', 'stories'], $picked->pluck('category')->all());
        $this->assertSame([1, 3, 4], $picked->pluck('id')->all());
    }

    public function test_fills_with_newest_when_there_are_fewer_categories(): void
    {
        $picked = Article::pickTeasers($this->posts(['care', 'care', 'guide', 'care']));

        $this->assertSame([1, 3, 2], $picked->pluck('id')->all());
    }

    public function test_shows_only_what_exists(): void
    {
        $this->assertCount(1, Article::pickTeasers($this->posts(['news'])));
        $this->assertCount(0, Article::pickTeasers($this->posts([])));
    }
}
