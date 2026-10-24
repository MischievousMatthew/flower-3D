<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductReview;
use App\Models\SupportTicket;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * GET /crm/feedback?months=6|12 — what customers keep saying, from product
 * reviews and support cases: rating and satisfaction month by month, and the
 * complaints that come up again and again (themes found by keywords in
 * reviews of 3 stars or less and in the shop's support cases).
 */
class CrmFeedbackController extends Controller
{
    use ResolvesOwner;

    /** Complaint themes and the words that point to them (English and common Filipino) */
    public const THEMES = [
        'freshness' => ['Wilted or not fresh', ['wilt', 'wilted', 'droop', 'not fresh', 'dried', 'dead', 'brown', 'lanta', 'lanta na']],
        'late' => ['Late or missed delivery', ['late', 'delay', 'delayed', 'never arrived', 'didn\'t arrive', 'did not arrive', 'missed', 'huli', 'tagal']],
        'damaged' => ['Damaged or poorly packed', ['damage', 'damaged', 'broken', 'crushed', 'torn', 'packaging', 'packed', 'sira', 'basag']],
        'wrong' => ['Wrong or missing items', ['wrong', 'missing', 'incomplete', 'not what i ordered', 'different', 'kulang', 'mali']],
        'looks' => ['Not as pictured', ['not as pictured', 'not like the picture', 'smaller', 'looks different', 'color', 'colour', 'photo', 'picture']],
        'service' => ['Rude or slow service', ['rude', 'unprofessional', 'no reply', 'didn\'t reply', 'slow reply', 'ignored', 'attitude', 'bastos']],
        'price' => ['Too pricey for what it was', ['expensive', 'overpriced', 'pricey', 'not worth', 'mahal']],
    ];

    /** A theme is "recurring" when it comes up at least this often */
    private const RECURRING = 2;

    public function show(Request $request): JsonResponse
    {
        $data = $request->validate(['months' => 'nullable|in:3,6,12']);
        $months = (int) ($data['months'] ?? 6);
        $vendorId = $this->resolveOwnerId($request);
        $from = now()->startOfMonth()->subMonths($months - 1);
        $cut = now()->subDays(30);

        $productIds = Product::withoutGlobalScopes()->where(fn ($q) => $q->where('owner_id', $vendorId)->orWhere('vendor_id', $vendorId))->pluck('product_name', 'id');
        $reviews = ProductReview::query()->whereIn('product_id', $productIds->keys())->whereNull('hidden_at')
            ->where('created_at', '>=', $from)->get(['id', 'product_id', 'rating', 'comment', 'vendor_reply', 'is_anonymous', 'user_id', 'created_at']);
        $cases = SupportTicket::query()->where('vendor_id', $vendorId)->where('created_at', '>=', $from)
            ->with(['messages' => fn ($q) => $q->where('from_shop', false)->where('from_admin', false)->orderBy('id')])
            ->get(['id', 'reference', 'subject', 'category', 'status', 'created_at']);

        return response()->json(['success' => true, 'data' => [
            'months' => $months,
            'trend' => $this->trend($reviews, $cases, $from, $months),
            'satisfaction' => $this->satisfaction($reviews, $cut),
            'themes' => $this->themes($reviews, $cases, $productIds, $cut),
            'products' => $reviews->groupBy('product_id')->map(fn ($g, $id) => [
                'id' => (int) $id,
                'name' => $productIds[$id] ?? 'Removed product',
                'average' => round($g->avg('rating'), 2),
                'count' => $g->count(),
                'low' => $g->where('rating', '<=', 2)->count(),
            ])->filter(fn ($p) => $p['count'] >= 2)->sortBy('average')->take(5)->values(),
        ]]);
    }

    private function trend(Collection $reviews, Collection $cases, Carbon $from, int $months): array
    {
        return collect(range(0, $months - 1))->map(function ($i) use ($reviews, $cases, $from) {
            $start = $from->copy()->addMonths($i);
            $end = $start->copy()->endOfMonth();
            $in = fn ($at) => $at >= $start && $at <= $end;
            $month = $reviews->filter(fn ($r) => $in($r->created_at));

            return [
                'key' => $start->format('Y-m'),
                'label' => $start->format('M Y'),
                'short' => $start->format('M'),
                'reviews' => $month->count(),
                'average' => $month->count() ? round($month->avg('rating'), 2) : null,
                'happy' => $month->where('rating', '>=', 4)->count(),
                'unhappy' => $month->where('rating', '<=', 2)->count(),
                'cases' => $cases->filter(fn ($c) => $in($c->created_at))->count(),
            ];
        })->values()->all();
    }

    private function satisfaction(Collection $reviews, Carbon $cut): array
    {
        $recent = $reviews->filter(fn ($r) => $r->created_at >= $cut);
        $before = $reviews->filter(fn ($r) => $r->created_at < $cut);
        $rate = fn ($set) => $set->count() ? round($set->where('rating', '>=', 4)->count() / $set->count() * 100, 1) : null;
        $replied = $reviews->where('rating', '<=', 3);

        return [
            'average' => $reviews->count() ? round($reviews->avg('rating'), 2) : null,
            'count' => $reviews->count(),
            'happy_rate' => $rate($reviews),
            'recent_happy_rate' => $rate($recent),
            'previous_happy_rate' => $rate($before),
            'recent_average' => $recent->count() ? round($recent->avg('rating'), 2) : null,
            'previous_average' => $before->count() ? round($before->avg('rating'), 2) : null,
            // How many unhappy or mixed reviews the shop answered
            'answered_rate' => $replied->count() ? round($replied->whereNotNull('vendor_reply')->count() / $replied->count() * 100, 1) : null,
            'unanswered_low' => $reviews->where('rating', '<=', 2)->whereNull('vendor_reply')->count(),
        ];
    }

    private function themes(Collection $reviews, Collection $cases, Collection $names, Carbon $cut): array
    {
        $mentions = [];
        foreach ($reviews->where('rating', '<=', 3) as $r) {
            foreach ($this->themesIn((string) $r->comment) as $theme) {
                $mentions[$theme][] = ['source' => 'review', 'at' => $r->created_at, 'quote' => $r->comment, 'rating' => (int) $r->rating, 'product' => $names[$r->product_id] ?? null];
            }
        }
        foreach ($cases as $c) {
            $text = $c->subject . ' ' . ($c->messages->first()?->body ?? '');
            $found = $this->themesIn($text);
            // Complaint topics customers pick count even without the words
            if ($c->category === 'quality') {
                $found[] = 'freshness';
            } elseif ($c->category === 'delivery') {
                $found[] = 'late';
            } elseif ($c->category === 'service') {
                $found[] = 'service';
            }
            foreach (array_unique($found) as $theme) {
                $mentions[$theme][] = ['source' => 'case', 'at' => $c->created_at, 'quote' => $c->subject, 'rating' => null, 'product' => null, 'reference' => $c->reference];
            }
        }

        return collect($mentions)->map(function ($list, $key) use ($cut) {
            $list = collect($list)->sortByDesc('at')->values();
            $recent = $list->filter(fn ($m) => $m['at'] >= $cut)->count();

            return [
                'key' => $key,
                'label' => self::THEMES[$key][0],
                'count' => $list->count(),
                'reviews' => $list->where('source', 'review')->count(),
                'cases' => $list->where('source', 'case')->count(),
                'recent' => $recent,
                'before' => $list->count() - $recent,
                'recurring' => $list->count() >= self::RECURRING,
                'products' => $list->pluck('product')->filter()->countBy()->sortDesc()->take(3)->keys()->values(),
                'examples' => $list->take(3)->map(fn ($m) => [
                    'source' => $m['source'],
                    'quote' => mb_strimwidth((string) $m['quote'], 0, 160, '…'),
                    'rating' => $m['rating'],
                    'product' => $m['product'],
                    'reference' => $m['reference'] ?? null,
                    'at' => $m['at']->toIso8601String(),
                ])->values(),
            ];
        })->sortByDesc('count')->values()->all();
    }

    /** Theme keys whose words appear in the text */
    private function themesIn(string $text): array
    {
        $text = ' ' . mb_strtolower($text) . ' ';
        $found = [];
        foreach (self::THEMES as $key => [, $words]) {
            foreach ($words as $word) {
                if (preg_match('/\b' . preg_quote($word, '/') . '/u', $text)) {
                    $found[] = $key;
                    break;
                }
            }
        }

        return $found;
    }
}
