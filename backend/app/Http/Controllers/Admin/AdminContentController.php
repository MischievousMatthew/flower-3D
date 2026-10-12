<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\CloudinaryHelper;
use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Article;
use App\Models\FeaturedProduct;
use App\Models\Product;
use App\Models\ProductTaxonomy;
use App\Models\SiteBanner;
use App\Models\SiteSetting;
use App\Models\VendorApplication;
use App\Support\VendorDirectory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Website content: homepage banners, featured products, journal articles,
 * announcements and promotions, and site information. Categories and
 * occasions live with the product lists (AdminProductController).
 */
class AdminContentController extends Controller
{
    private const LINK_TYPES = ['products', 'category', 'occasion', 'store', 'article', 'url'];

    /**
     * GET /api/admin/content/overview
     */
    public function overview(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'banners' => ['total' => SiteBanner::count(), 'showing' => SiteBanner::showing()->count()],
                'featured' => FeaturedProduct::count(),
                'articles' => ['published' => Article::published()->count(), 'drafts' => Article::where('status', 'draft')->count()],
                'announcements' => [
                    'showing' => Announcement::showing()->where('kind', Announcement::ANNOUNCEMENT)->count(),
                    'promotions' => Announcement::showing()->where('kind', Announcement::PROMOTION)->count(),
                ],
                'taxonomies' => ProductTaxonomy::query()->where('is_active', true)->selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            ],
        ]);
    }

    /**
     * GET /api/admin/content/link-options
     * Choices for "where does this button go?"
     */
    public function linkOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'categories' => ProductTaxonomy::ofType(ProductTaxonomy::CATEGORY)->where('is_active', true)->get(['value', 'label']),
                'occasions' => ProductTaxonomy::ofType(ProductTaxonomy::OCCASION)->where('is_active', true)->get(['value', 'label']),
                'stores' => VendorApplication::notSuspended()->where('status', 'approved')->orderBy('store_name')->get(['id', 'store_name'])
                    ->map(fn ($s) => ['value' => (string) $s->id, 'label' => $s->store_name]),
                'articles' => Article::published()->latest('published_at')->get(['slug', 'title'])
                    ->map(fn ($a) => ['value' => $a->slug, 'label' => $a->title]),
            ],
        ]);
    }

    // ── Banners ──────────────────────────────────────────────────────────

    public function banners(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => SiteBanner::query()->orderBy('sort_order')->orderByDesc('id')->get()->map(fn ($b) => $this->withSchedule($b))]);
    }

    public function saveBanner(Request $request, ?int $id = null): JsonResponse
    {
        $banner = $id ? SiteBanner::find($id) : new SiteBanner(['sort_order' => (int) SiteBanner::max('sort_order') + 1]);
        if (!$banner) {
            return $this->notFound();
        }

        $data = $request->validate([
            'eyebrow' => 'nullable|string|max:60',
            'title' => 'required|string|max:140',
            'subtitle' => 'nullable|string|max:300',
            'button_label' => 'nullable|string|max:40',
            'is_active' => 'nullable|boolean',
            'image' => [$banner->exists ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ] + $this->linkAndScheduleRules());
        $this->checkLink($data);

        $banner->fill($this->clean($data, ['eyebrow', 'title', 'subtitle', 'button_label', 'link_type', 'link_value', 'starts_at', 'ends_at']));
        $banner->is_active = $request->boolean('is_active', $banner->is_active ?? true);

        return $this->saveWithImage($banner, $request->file('image'), 'image', 'site_banners', 'Banner saved.');
    }

    public function deleteBanner(int $id): JsonResponse
    {
        return $this->deleteWithImage(SiteBanner::find($id), 'image_path');
    }

    // ── Featured products ────────────────────────────────────────────────

    public function featured(): JsonResponse
    {
        $rows = FeaturedProduct::query()->with(['product.images' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('display_order')])
            ->orderBy('sort_order')->get();
        $vendors = VendorDirectory::details($rows->pluck('product.owner_id'));
        $suspended = DB::table('users')->whereIn('id', $rows->pluck('product.owner_id')->filter())->where('is_suspended', true)->pluck('id')->flip();

        return response()->json(['success' => true, 'data' => $rows->map(fn ($f) => $this->productCard($f->product, $vendors, $suspended) + ['featured_id' => $f->id])->values()]);
    }

    /**
     * GET /api/admin/content/featured/search?q=
     * Live listings that aren't featured yet.
     */
    public function searchProducts(Request $request): JsonResponse
    {
        $q = trim((string) $request->input('q'));
        $products = Product::listedForCustomers()->where('status', 'active')
            ->whereIn('selling_type', ['per_piece', 'bouquet'])
            ->whereNotIn('id', FeaturedProduct::query()->select('product_id'))
            ->when($q !== '', function ($query) use ($q) {
                $like = '%' . mb_strtolower($q) . '%';
                $query->where(fn ($w) => $w->whereRaw('lower(product_name) like ?', [$like])->orWhereIn('owner_id', VendorDirectory::idsMatching($q)));
            })
            ->with(['images' => fn ($w) => $w->orderByDesc('is_primary')->orderBy('display_order')])
            ->latest()->limit(12)->get();
        $vendors = VendorDirectory::details($products->pluck('owner_id'));

        return response()->json(['success' => true, 'data' => $products->map(fn ($p) => $this->productCard($p, $vendors, collect()))->values()]);
    }

    public function addFeatured(Request $request): JsonResponse
    {
        $data = $request->validate(['product_id' => 'required|integer|exists:products,id']);
        if (FeaturedProduct::where('product_id', $data['product_id'])->exists()) {
            return response()->json(['success' => false, 'message' => 'That product is already featured.'], 422);
        }
        if (FeaturedProduct::count() >= 12) {
            return response()->json(['success' => false, 'message' => 'Up to 12 products can be featured. Remove one first.'], 422);
        }

        FeaturedProduct::create([
            'product_id' => $data['product_id'],
            'sort_order' => (int) FeaturedProduct::max('sort_order') + 1,
            'added_by' => $request->user()?->id,
        ]);

        return response()->json(['success' => true, 'message' => 'Added to Featured.'], 201);
    }

    public function removeFeatured(int $id): JsonResponse
    {
        FeaturedProduct::whereKey($id)->delete();
        return response()->json(['success' => true, 'message' => 'Removed from Featured.']);
    }

    // ── Articles ─────────────────────────────────────────────────────────

    public function articles(Request $request): JsonResponse
    {
        $query = Article::query()->with('author:id,name,surname')->latest('updated_at');
        if (in_array($request->input('status'), ['draft', 'published', 'scheduled'], true)) {
            match ($request->input('status')) {
                'published' => $query->published(),
                'scheduled' => $query->where('status', 'published')->where('published_at', '>', now()),
                'draft' => $query->where('status', 'draft'),
            };
        }
        if ($search = trim((string) $request->input('search'))) {
            $query->whereRaw('lower(title) like ?', ['%' . mb_strtolower($search) . '%']);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get()->map(fn ($a) => $this->formatArticle($a)),
            'categories' => Article::CATEGORIES,
        ]);
    }

    public function article(int $id): JsonResponse
    {
        $article = Article::with('author:id,name,surname')->find($id);
        return $article ? response()->json(['success' => true, 'data' => $this->formatArticle($article, true)]) : $this->notFound();
    }

    public function saveArticle(Request $request, ?int $id = null): JsonResponse
    {
        $article = $id ? Article::find($id) : new Article(['author_id' => $request->user()?->id, 'status' => 'draft']);
        if (!$article) {
            return $this->notFound();
        }

        $data = $request->validate([
            'title' => 'required|string|max:160',
            'category' => ['required', Rule::in(array_keys(Article::CATEGORIES))],
            'excerpt' => 'nullable|string|max:300',
            'body' => 'required|string|min:20',
            'is_featured' => 'nullable|boolean',
            'cover' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'remove_cover' => 'nullable|boolean',
        ]);

        $article->fill([
            'title' => trim($data['title']),
            'category' => $data['category'],
            'excerpt' => isset($data['excerpt']) ? trim($data['excerpt']) ?: null : null,
            'body' => $data['body'],
            'is_featured' => $request->boolean('is_featured'),
        ]);
        if (!$article->exists || $article->isDirty('title')) {
            $article->slug = $this->uniqueSlug($data['title'], $article->id);
        }

        if ($request->boolean('remove_cover') && $article->cover_path) {
            $this->destroyImage($article->cover_path);
            $article->forceFill(['cover_url' => null, 'cover_path' => null]);
        }

        return $this->saveWithImage($article, $request->file('cover'), 'cover', 'site_articles', 'Article saved.', fn ($a) => $this->formatArticle($a, true));
    }

    /**
     * POST /api/admin/content/articles/{id}/publish  { published_at? }
     */
    public function publishArticle(Request $request, int $id): JsonResponse
    {
        $article = Article::find($id);
        if (!$article) {
            return $this->notFound();
        }
        $data = $request->validate(['published_at' => 'nullable|date']);
        $article->forceFill(['status' => 'published', 'published_at' => $data['published_at'] ?? ($article->published_at && $article->published_at->isPast() ? $article->published_at : now())])->save();

        $scheduled = $article->published_at->isFuture();
        return response()->json([
            'success' => true,
            'message' => $scheduled ? 'Scheduled for ' . $article->published_at->format('M j, g:i A') . '.' : 'Published to the Journal.',
            'data' => $this->formatArticle($article, true),
        ]);
    }

    public function unpublishArticle(int $id): JsonResponse
    {
        $article = Article::find($id);
        if (!$article) {
            return $this->notFound();
        }
        $article->forceFill(['status' => 'draft'])->save();

        return response()->json(['success' => true, 'message' => 'Moved back to drafts.', 'data' => $this->formatArticle($article, true)]);
    }

    public function deleteArticle(int $id): JsonResponse
    {
        return $this->deleteWithImage(Article::find($id), 'cover_path');
    }

    // ── Announcements & promotions ───────────────────────────────────────

    public function announcements(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => Announcement::query()->orderBy('kind')->orderBy('sort_order')->orderByDesc('id')->get()->map(fn ($a) => $this->withSchedule($a))]);
    }

    public function saveAnnouncement(Request $request, ?int $id = null): JsonResponse
    {
        $item = $id ? Announcement::find($id) : new Announcement(['sort_order' => (int) Announcement::max('sort_order') + 1]);
        if (!$item) {
            return $this->notFound();
        }

        $data = $request->validate([
            'kind' => ['required', Rule::in([Announcement::ANNOUNCEMENT, Announcement::PROMOTION])],
            'title' => 'required|string|max:120',
            'message' => 'nullable|string|max:300',
            'promo_code' => 'nullable|string|max:40|regex:/^[A-Za-z0-9_-]+$/',
            'link_label' => 'nullable|string|max:40',
            'tone' => ['nullable', Rule::in(['brand', 'info', 'success', 'warning'])],
            'is_active' => 'nullable|boolean',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
        ] + $this->linkAndScheduleRules(required: false));
        $this->checkLink($data);

        $item->fill($this->clean($data, ['kind', 'title', 'message', 'link_label', 'link_type', 'link_value', 'starts_at', 'ends_at']));
        $item->promo_code = isset($data['promo_code']) ? strtoupper(trim($data['promo_code'])) ?: null : null;
        $item->tone = $data['tone'] ?? 'brand';
        $item->is_active = $request->boolean('is_active', $item->is_active ?? true);

        return $this->saveWithImage($item, $request->file('image'), 'image', 'site_promotions', 'Saved.');
    }

    public function deleteAnnouncement(int $id): JsonResponse
    {
        return $this->deleteWithImage(Announcement::find($id), 'image_path');
    }

    /**
     * POST /api/admin/content/{type}/reorder  { ids: [] }  (banners | announcements | featured)
     */
    public function reorder(Request $request, string $type): JsonResponse
    {
        $data = $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $model = ['banners' => SiteBanner::class, 'announcements' => Announcement::class, 'featured' => FeaturedProduct::class][$type];

        DB::transaction(function () use ($data, $model) {
            foreach (array_values($data['ids']) as $index => $id) {
                $model::query()->whereKey($id)->update(['sort_order' => $index + 1]);
            }
        });

        return response()->json(['success' => true]);
    }

    // ── Site information ─────────────────────────────────────────────────

    public function settings(): JsonResponse
    {
        return response()->json(['success' => true, 'data' => SiteSetting::snapshot()]);
    }

    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'site_name' => 'required|string|max:60',
            'tagline' => 'nullable|string|max:140',
            'about' => 'nullable|string|max:600',
            'contact_email' => 'nullable|email|max:120',
            'contact_phone' => 'nullable|string|max:40',
            'address' => 'nullable|string|max:255',
            'business_hours' => 'nullable|string|max:120',
            'footer_note' => 'nullable|string|max:200',
            'socials' => 'nullable|array',
            'socials.facebook' => 'nullable|url|max:255',
            'socials.instagram' => 'nullable|url|max:255',
            'socials.tiktok' => 'nullable|url|max:255',
            'socials.x' => 'nullable|url|max:255',
        ]);

        DB::transaction(function () use ($data) {
            foreach (SiteSetting::KEYS as $key) {
                $value = $data[$key] ?? null;
                if (is_string($value)) {
                    $value = trim($value) ?: null;
                }
                if ($key === 'socials') {
                    $value = collect(['facebook', 'instagram', 'tiktok', 'x'])->mapWithKeys(fn ($s) => [$s => $data['socials'][$s] ?? null])->all();
                }
                SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Website information saved.', 'data' => SiteSetting::snapshot()]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function linkAndScheduleRules(bool $required = true): array
    {
        return [
            'link_type' => [$required ? 'required' : 'nullable', Rule::in(self::LINK_TYPES)],
            'link_value' => 'nullable|string|max:500',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after:starts_at',
        ];
    }

    /** A button must point somewhere real */
    private function checkLink(array $data): void
    {
        $type = $data['link_type'] ?? null;
        $value = trim((string) ($data['link_value'] ?? ''));
        if (!$type || $type === 'products') {
            return;
        }

        $ok = match ($type) {
            'category' => ProductTaxonomy::where('type', ProductTaxonomy::CATEGORY)->where('value', $value)->exists(),
            'occasion' => ProductTaxonomy::where('type', ProductTaxonomy::OCCASION)->where('value', $value)->exists(),
            'store' => ctype_digit($value) && VendorApplication::where('status', 'approved')->whereKey((int) $value)->exists(),
            'article' => Article::where('slug', $value)->exists(),
            'url' => str_starts_with($value, '/') || (bool) filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value),
            default => false,
        };

        if (!$ok) {
            throw ValidationException::withMessages(['link_value' => [match ($type) {
                'url' => 'Enter a web address starting with https:// or a page path starting with /.',
                default => 'Choose where the button should go.',
            }]]);
        }
    }

    private function clean(array $data, array $keys): array
    {
        return collect($keys)->mapWithKeys(function ($key) use ($data) {
            $value = $data[$key] ?? null;
            return [$key => is_string($value) ? (trim($value) === '' ? null : trim($value)) : $value];
        })->all();
    }

    private function saveWithImage(Model $model, ?UploadedFile $file, string $field, string $folder, string $message, ?callable $format = null): JsonResponse
    {
        $previous = $model->{"{$field}_path"};
        try {
            if ($file) {
                $upload = CloudinaryHelper::upload($file, ['folder' => $folder, 'resource_type' => 'image']);
                $model->forceFill(["{$field}_url" => $upload['secure_url'], "{$field}_path" => $upload['public_id']]);
            }
            $model->save();
        } catch (\Throwable $e) {
            Log::error('AdminContentController save failed: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'It couldn\'t be saved. Try again.'], 500);
        }

        if ($file && $previous && $previous !== $model->{"{$field}_path"}) {
            $this->destroyImage($previous);
        }

        $data = $format ? $format($model->fresh()) : $this->withSchedule($model->fresh());

        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $model->wasRecentlyCreated ? 201 : 200);
    }

    private function deleteWithImage(?Model $model, string $pathField): JsonResponse
    {
        if (!$model) {
            return $this->notFound();
        }
        $path = $model->{$pathField};
        $model->delete();
        if ($path) {
            $this->destroyImage($path);
        }

        return response()->json(['success' => true, 'message' => 'Deleted.']);
    }

    private function destroyImage(string $path): void
    {
        try {
            CloudinaryHelper::destroy($path);
        } catch (\Throwable $e) {
            Log::warning('Could not delete content image ' . $path . ': ' . $e->getMessage());
        }
    }

    /** Adds whether it's showing right now, scheduled, or over */
    private function withSchedule(Model $item): array
    {
        $now = now();
        $state = match (true) {
            !$item->is_active => 'off',
            $item->starts_at && $item->starts_at->gt($now) => 'scheduled',
            $item->ends_at && $item->ends_at->lte($now) => 'ended',
            default => 'showing',
        };

        return $item->toArray() + ['state' => $state];
    }

    private function formatArticle(Article $a, bool $withBody = false): array
    {
        $state = $a->status !== 'published' ? 'draft' : ($a->published_at?->isFuture() ? 'scheduled' : 'published');

        return [
            'id' => $a->id,
            'title' => $a->title,
            'slug' => $a->slug,
            'category' => $a->category,
            'category_label' => Article::CATEGORIES[$a->category] ?? Str::headline($a->category),
            'excerpt' => $a->excerpt,
            'cover_url' => $a->cover_url,
            'state' => $state,
            'is_featured' => (bool) $a->is_featured,
            'published_at' => $a->published_at?->toIso8601String(),
            'updated_at' => $a->updated_at?->toIso8601String(),
            'views' => (int) $a->views,
            'reading_minutes' => $a->readingMinutes(),
            'author' => $a->author ? trim($a->author->name . ' ' . ($a->author->surname ?? '')) : null,
        ] + ($withBody ? ['body' => $a->body] : []);
    }

    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'article';
        $slug = $base;
        $n = 2;
        while (Article::where('slug', $slug)->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))->exists()) {
            $slug = "{$base}-{$n}";
            $n++;
        }

        return $slug;
    }

    private function productCard(?Product $p, array $vendors, $suspended): array
    {
        if (!$p) {
            return ['id' => null, 'name' => 'Removed product', 'live' => false];
        }

        return [
            'id' => $p->id,
            'name' => $p->product_name,
            'image' => $p->images->first()?->image_url,
            'price' => (float) ($p->has_discount && $p->discount_price ? $p->discount_price : $p->selling_price),
            'stock' => (int) $p->quantity_in_stock,
            'vendor' => $vendors[$p->owner_id] ?? VendorDirectory::unknown($p->owner_id),
            // A featured product only shows in the shop while customers can buy it
            'live' => $p->status === 'active' && !$p->isTakenDown() && !isset($suspended[$p->owner_id])
                && in_array($p->selling_type, ['per_piece', 'bouquet'], true),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Not found.'], 404);
    }
}
