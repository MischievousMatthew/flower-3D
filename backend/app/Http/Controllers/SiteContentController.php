<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Article;
use App\Models\Product;
use App\Models\ProductTaxonomy;
use App\Models\SiteBanner;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Public website content for the shop and landing page (no sign-in needed).
 * Only content that's switched on, inside its dates and published is returned.
 */
class SiteContentController extends Controller
{
    /**
     * GET /api/site/home
     */
    public function home(): JsonResponse
    {
        $publicFields = ['id', 'title', 'message', 'promo_code', 'image_url', 'link_label', 'link_type', 'link_value', 'tone', 'ends_at'];
        $showing = Announcement::showing()->orderBy('sort_order')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'banners' => SiteBanner::showing()->orderBy('sort_order')->get([
                    'id', 'eyebrow', 'title', 'subtitle', 'image_url', 'button_label', 'link_type', 'link_value',
                ]),
                'announcements' => $showing->where('kind', Announcement::ANNOUNCEMENT)->map->only($publicFields)->values(),
                'promotions' => $showing->where('kind', Announcement::PROMOTION)->map->only($publicFields)->values(),
                'articles' => Article::published()->orderByDesc('is_featured')->latest('published_at')->limit(3)->get()
                    ->map(fn ($a) => $this->card($a)),
                'categories' => $this->taxonomyWithCounts(ProductTaxonomy::CATEGORY),
                'occasions' => $this->taxonomyWithCounts(ProductTaxonomy::OCCASION),
                'settings' => SiteSetting::snapshot(),
            ],
        ]);
    }

    /**
     * GET /api/site/settings
     */
    public function settings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => SiteSetting::snapshot()]);
    }

    /**
     * GET /api/site/status: whether sign-ups and checkout are open right now
     */
    public function status(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => \App\Support\PlatformSettings::publicStatus()]);
    }

    /**
     * GET /api/site/articles?category=care
     */
    public function articles(Request $request): JsonResponse
    {
        $page = Article::published()
            ->when(array_key_exists((string) $request->input('category'), Article::CATEGORIES), fn ($q) => $q->where('category', $request->input('category')))
            ->orderByDesc('is_featured')->latest('published_at')
            ->paginate(min(max((int) $request->input('per_page', 9), 3), 30));

        return response()->json([
            'success' => true,
            'data' => collect($page->items())->map(fn ($a) => $this->card($a)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'categories' => Article::CATEGORIES,
        ]);
    }

    /**
     * GET /api/site/articles/{slug}
     */
    public function article(string $slug): JsonResponse
    {
        $article = Article::published()->with('author:id,name,surname')->where('slug', $slug)->first();
        if (!$article) {
            return response()->json(['success' => false, 'message' => 'This article isn\'t available.'], 404);
        }

        $article->increment('views');

        return response()->json([
            'success' => true,
            'data' => $this->card($article) + [
                'body' => $article->body,
                'author' => $article->author ? trim($article->author->name . ' ' . ($article->author->surname ?? '')) : 'BloomCraft',
            ],
            'related' => Article::published()->where('category', $article->category)->whereKeyNot($article->id)
                ->latest('published_at')->limit(3)->get()->map(fn ($a) => $this->card($a)),
        ]);
    }

    private function card(Article $a): array
    {
        return [
            'id' => $a->id,
            'slug' => $a->slug,
            'title' => $a->title,
            'excerpt' => $a->excerpt ?: Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags($a->body))), 160),
            'category' => $a->category,
            'category_label' => Article::CATEGORIES[$a->category] ?? Str::headline($a->category),
            'cover_url' => $a->cover_url,
            'published_at' => $a->published_at?->toIso8601String(),
            'reading_minutes' => $a->readingMinutes(),
        ];
    }

    /** Offered values in admin order, with how many live products use each */
    private function taxonomyWithCounts(string $type): array
    {
        $products = Product::listedForCustomers()->where('status', 'active')
            ->whereIn('selling_type', ['per_piece', 'bouquet']);
        $counts = $type === ProductTaxonomy::OCCASION
            ? $products->pluck('occasion_tags')->flatten()->filter()->countBy()
            : $products->pluck($type)->filter()->countBy();

        return ProductTaxonomy::ofType($type)->where('is_active', true)->get(['value', 'label'])
            ->map(fn ($t) => ['value' => $t->value, 'label' => $t->label, 'products' => (int) ($counts[$t->value] ?? 0)])
            ->values()->all();
    }
}
