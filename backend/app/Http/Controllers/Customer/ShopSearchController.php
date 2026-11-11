<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductTaxonomy;
use App\Models\SearchTerm;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * "Popular searches" under the shop's search bar. Searches are counted only
 * when they found live products, so nonsense or abuse never shows up; the
 * list starts from the most stocked flowers until there are enough searches.
 */
class ShopSearchController extends Controller
{
    private const SHOW = 8;
    private const RECENT_DAYS = 60;

    /** GET /customer/search/popular */
    public function popular(): JsonResponse
    {
        try {
            $terms = SearchTerm::query()
                ->where('hits', '>=', 2)
                ->where('last_searched_at', '>=', now()->subDays(self::RECENT_DAYS))
                ->orderByDesc('hits')
                ->limit(self::SHOW)
                ->pluck('term');

            // Not enough searches yet: the flowers with the most products
            if ($terms->count() < self::SHOW) {
                $labels = ProductTaxonomy::query()->where('type', ProductTaxonomy::CATEGORY)->pluck('label', 'value');
                $top = Product::listedForCustomers()->where('status', 'active')
                    ->whereIn('selling_type', ['per_piece', 'bouquet'])
                    ->whereNotNull('category')->where('category', '!=', '')
                    ->select('category', DB::raw('COUNT(*) as n'))
                    ->groupBy('category')->orderByDesc('n')->limit(self::SHOW)
                    ->pluck('category')
                    ->map(fn ($c) => mb_strtolower($labels[$c] ?? str_replace(['-', '_'], ' ', $c)));
                $terms = $terms->merge($top)->unique()->take(self::SHOW)->values();
            }

            return response()->json(['success' => true, 'data' => $terms]);
        } catch (\Throwable $e) {
            Log::error('ShopSearchController@popular: ' . $e->getMessage());
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    /** POST /customer/search/log {term}: counts a search that found products */
    public function log(Request $request): JsonResponse
    {
        $term = $this->clean((string) $request->input('term', ''));
        if ($term === null || ! $this->findsProducts($term)) {
            return response()->json(['success' => true, 'counted' => false]);
        }

        try {
            $row = SearchTerm::firstOrCreate(['term' => $term], ['hits' => 0]);
            $row->increment('hits', 1, ['last_searched_at' => now()]);
        } catch (\Throwable $e) {
            // Two of the same search at once: one of them is enough
            Log::info('ShopSearchController@log: ' . $e->getMessage());
        }

        return response()->json(['success' => true, 'counted' => true]);
    }

    /** Lower case, single spaces, 2 to 40 letters, digits, spaces, ' and - only */
    private function clean(string $term): ?string
    {
        $term = mb_strtolower(trim(preg_replace('/\s+/u', ' ', $term)));
        if (mb_strlen($term) < 2 || mb_strlen($term) > 40) {
            return null;
        }
        return preg_match("/^[\p{L}\p{N} '\-]+$/u", $term) ? $term : null;
    }

    /** The same match the shop's search uses: every word in the name, flower, colour or occasion */
    private function findsProducts(string $term): bool
    {
        $query = Product::listedForCustomers()->where('status', 'active')
            ->whereIn('selling_type', ['per_piece', 'bouquet']);
        foreach (array_slice(explode(' ', $term), 0, 6) as $word) {
            $like = '%' . addcslashes($word, '%_\\') . '%';
            $query->where(fn ($q) => $q->whereRaw('LOWER(product_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(category) LIKE ?', [$like])
                ->orWhereRaw('LOWER(color) LIKE ?', [$like])
                ->orWhereRaw('LOWER(flower_type) LIKE ?', [$like])
                ->orWhereRaw('LOWER(CAST(occasion_tags AS TEXT)) LIKE ?', [$like]));
        }
        return $query->exists();
    }
}
