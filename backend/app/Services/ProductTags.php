<?php

namespace App\Services;

use App\Models\FeaturedProduct;
use App\Models\Product;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Which products carry which tag, worked out from the shop's own activity
 * and the admin's rules (SiteSetting::productTags):
 *
 *   sale         discounted by at least the set percent
 *   best_seller  the top N by units sold in the last D days (at least M sold)
 *   trending     the top N by recent interest: orders x3, cart adds x2, hearts x1
 *   new_arrival  added in the last D days, newest first, at most N
 *   top_rated    at least the set rating from enough reviews, at most N
 *   recommended  the admin's Featured products
 *   seasonal     made for a season rather than all year
 *
 * The lists are worked out at most every few minutes and cached, so showing
 * tags costs nothing per product. Saving the rules clears the cache.
 */
class ProductTags
{
    public const ORDER = ['sale', 'best_seller', 'trending', 'new_arrival', 'top_rated', 'recommended', 'seasonal'];
    private const CACHE_KEY = 'product_tags:sets:v1';
    private const CACHE_SECONDS = 600;

    private ?array $sets = null;
    private ?array $rules = null;

    public function rules(): array
    {
        return $this->rules ??= SiteSetting::productTags();
    }

    /** @return array<string, array<int, true>> tag => [product id => true] */
    public function sets(): array
    {
        return $this->sets ??= Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->compute($this->rules()));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** The tags on one product, most important first */
    public function tagsFor(Product $product): array
    {
        $rules = $this->rules();
        $sets = $this->sets();
        $tags = [];
        foreach (self::ORDER as $tag) {
            $on = match ($tag) {
                'sale' => $rules['sale_enabled'] && $this->discountPercent($product) >= (float) $rules['sale_min_percent'],
                'seasonal' => $rules['seasonal_enabled'] && in_array($product->season, ['spring', 'summer', 'autumn', 'winter'], true),
                default => isset($sets[$tag][$product->id]),
            };
            if ($on) {
                $tags[] = $tag;
            }
        }
        return $tags;
    }

    /** How many products each tag is on right now (for the admin page) */
    public function counts(array $rules): array
    {
        $sets = $this->compute($rules);
        $listed = $this->listed();
        return [
            'sale' => $rules['sale_enabled'] ? (clone $listed)->whereNotNull('discount_price')->get(['selling_price', 'discount_price'])
                ->filter(fn ($p) => $this->discountPercent($p) >= (float) $rules['sale_min_percent'])->count() : 0,
            'best_seller' => count($sets['best_seller']),
            'trending' => count($sets['trending']),
            'new_arrival' => count($sets['new_arrival']),
            'top_rated' => count($sets['top_rated']),
            'recommended' => count($sets['recommended']),
            'seasonal' => $rules['seasonal_enabled'] ? (clone $listed)->whereIn('season', ['spring', 'summer', 'autumn', 'winter'])->count() : 0,
        ];
    }

    // ── Working out the lists ──────────────────────────────────────────

    private function listed()
    {
        return Product::listedForCustomers()->where('status', 'active')->whereIn('selling_type', ['per_piece', 'bouquet']);
    }

    private function discountPercent($product): float
    {
        $price = (float) $product->getRawOriginal('selling_price');
        $sale = $product->discount_price !== null ? (float) $product->discount_price : null;
        if ($price <= 0 || $sale === null || $sale <= 0 || $sale >= $price) {
            return 0;
        }
        return (1 - $sale / $price) * 100;
    }

    private function compute(array $r): array
    {
        $ids = $this->listed()->pluck('id');
        $flip = fn ($list) => collect($list)->mapWithKeys(fn ($id) => [(int) $id => true])->all();

        $best = [];
        if ($r['best_enabled'] && $ids->isNotEmpty()) {
            $best = DB::table('order_items as oi')
                ->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->whereIn('oi.product_id', $ids)
                ->where('o.created_at', '>=', now()->subDays((int) $r['best_days']))
                ->where(fn ($q) => $q->where('o.status', 'completed')
                    ->orWhereIn('o.id', fn ($s) => $s->select('order_id')->from('deliveries')->where('status', 'completed')))
                ->groupBy('oi.product_id')
                ->havingRaw('SUM(oi.quantity) >= ?', [(int) $r['best_min_sold']])
                ->orderByRaw('SUM(oi.quantity) DESC')
                ->limit((int) $r['best_top'])
                ->pluck('oi.product_id')->all();
        }

        $trending = [];
        if ($r['trend_enabled'] && $ids->isNotEmpty()) {
            $since = now()->subDays((int) $r['trend_days']);
            $score = [];
            $add = function ($rows, int $weight) use (&$score) {
                foreach ($rows as $id => $n) {
                    $score[$id] = ($score[$id] ?? 0) + $n * $weight;
                }
            };
            $add(DB::table('order_items as oi')->join('orders as o', 'o.id', '=', 'oi.order_id')
                ->whereIn('oi.product_id', $ids)->where('o.created_at', '>=', $since)
                ->whereNotIn('o.status', ['cancelled', 'failed'])
                ->groupBy('oi.product_id')->select('oi.product_id', DB::raw('SUM(oi.quantity) as n'))->get()->pluck('n', 'product_id'), 3);
            $add(DB::table('carts')->whereIn('product_id', $ids)->where('created_at', '>=', $since)
                ->groupBy('product_id')->select('product_id', DB::raw('COUNT(*) as n'))->get()->pluck('n', 'product_id'), 2);
            $add(DB::table('favorite_products')->whereIn('product_id', $ids)->where('created_at', '>=', $since)
                ->groupBy('product_id')->select('product_id', DB::raw('COUNT(*) as n'))->get()->pluck('n', 'product_id'), 1);
            arsort($score);
            $trending = array_slice(array_keys(array_filter($score, fn ($s) => $s >= (int) $r['trend_min_score'])), 0, (int) $r['trend_top']);
        }

        $new = $r['new_enabled']
            ? $this->listed()->where('created_at', '>=', now()->subDays((int) $r['new_days']))
                ->orderByDesc('created_at')->limit((int) $r['new_max'])->pluck('id')->all()
            : [];

        $rated = [];
        if ($r['rated_enabled'] && $ids->isNotEmpty()) {
            $rated = DB::table('product_reviews')->whereIn('product_id', $ids)->whereNull('hidden_at')
                ->groupBy('product_id')
                ->havingRaw('COUNT(*) >= ?', [(int) $r['rated_min_reviews']])
                ->havingRaw('AVG(rating) >= ?', [(float) $r['rated_min_rating']])
                ->orderByRaw('AVG(rating) DESC, COUNT(*) DESC')
                ->limit((int) $r['rated_max'])
                ->pluck('product_id')->all();
        }

        $recommended = $r['recommended_enabled']
            ? FeaturedProduct::query()->whereIn('product_id', $ids)->pluck('product_id')->all()
            : [];

        return [
            'best_seller' => $flip($best),
            'trending' => $flip($trending),
            'new_arrival' => $flip($new),
            'top_rated' => $flip($rated),
            'recommended' => $flip($recommended),
        ];
    }
}
