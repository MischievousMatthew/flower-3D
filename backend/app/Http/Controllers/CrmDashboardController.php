<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\Conversation;
use App\Models\CustomerInquiry;
use App\Models\Message;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * GET /crm/dashboard?from&to — the customer overview: who buys, who comes
 * back, what customers asked and are still waiting on, who spends the most,
 * and how happy they are (reviews) and how fast the shop answers (chat).
 *
 * A customer is anyone with a paid order from the shop that wasn't cancelled.
 */
class CrmDashboardController extends Controller
{
    use ResolvesOwner;

    private const NOT_A_SALE = ['cancelled', 'failed', 'refunded'];

    /** Inquiries and chats left this long count as overdue */
    private const OVERDUE_HOURS = 24;

    public function show(Request $request): JsonResponse
    {
        $request->validate(['from' => 'nullable|date', 'to' => 'nullable|date|after_or_equal:from']);
        $from = $request->filled('from') ? Carbon::parse($request->input('from'))->startOfDay() : now()->startOfMonth();
        $to = $request->filled('to') ? Carbon::parse($request->input('to'))->endOfDay() : now()->endOfDay();
        $days = max(1, (int) ceil($from->diffInDays($to)));
        $pTo = $from->copy()->subDay()->endOfDay();
        $pFrom = $pTo->copy()->subDays($days - 1)->startOfDay();

        $vendorId = $this->resolveOwnerId($request);

        // Every sale the shop has made, small enough to work through in memory
        $sales = Order::query()
            ->where('vendor_id', $vendorId)
            ->whereNotNull('user_id')
            ->whereNotNull('paid_at')
            ->whereNotIn('status', self::NOT_A_SALE)
            ->get(['id', 'user_id', 'total_amount', 'paid_at']);

        return response()->json(['success' => true, 'data' => [
            'range' => ['from' => $from->toDateString(), 'to' => $to->toDateString(), 'label' => $from->format('M j, Y') . ' – ' . $to->format('M j, Y')],
            'customers' => $this->customers($sales, $from, $to, $pFrom, $pTo),
            'top_customers' => $this->topCustomers($sales),
            'inquiries' => $this->inquiries($vendorId, $from, $to),
            'issues' => $this->issues($vendorId),
            'satisfaction' => $this->satisfaction($vendorId, $from, $to, $pFrom, $pTo),
            'response' => $this->chatResponse($vendorId, $from, $to),
        ]]);
    }

    private function customers(Collection $sales, Carbon $from, Carbon $to, Carbon $pFrom, Carbon $pTo): array
    {
        $byCustomer = $sales->groupBy('user_id');
        $firstBuy = $byCustomer->map(fn ($orders) => $orders->min('paid_at'));
        $in = fn ($at, $a, $b) => $at >= $a && $at <= $b;

        $buyersIn = fn ($a, $b) => $sales->filter(fn ($o) => $in($o->paid_at, $a, $b))->pluck('user_id')->unique();
        $buyers = $buyersIn($from, $to);
        $previousBuyers = $buyersIn($pFrom, $pTo);
        $newIn = fn ($a, $b) => $firstBuy->filter(fn ($at) => $in($at, $a, $b))->count();

        $repeat = $byCustomer->filter(fn ($orders) => $orders->count() > 1)->count();
        $total = $byCustomer->count();
        // Of the people who bought in the period before, how many bought again in this one
        $kept = $previousBuyers->intersect($buyers)->count();

        return [
            'total' => $total,
            'new' => $newIn($from, $to),
            'previous_new' => $newIn($pFrom, $pTo),
            'active' => $buyers->count(),
            'returning' => $buyers->filter(fn ($id) => $firstBuy[$id] < $from)->count(),
            'repeat' => $repeat,
            'repeat_rate' => $total ? round($repeat / $total * 100, 1) : null,
            'retention_rate' => $previousBuyers->count() ? round($kept / $previousBuyers->count() * 100, 1) : null,
            'retained' => $kept,
            'previous_buyers' => $previousBuyers->count(),
        ];
    }

    private function topCustomers(Collection $sales): array
    {
        $top = $sales->groupBy('user_id')
            ->map(fn ($orders, $id) => [
                'id' => (int) $id,
                'orders' => $orders->count(),
                'spent' => round((float) $orders->sum('total_amount'), 2),
                'last_order_at' => $orders->max('paid_at')?->toIso8601String(),
            ])
            ->sortByDesc('spent')->take(5)->values();

        $users = User::whereIn('id', $top->pluck('id'))->get(['id', 'name', 'surname', 'email', 'profile_picture'])->keyBy('id');

        return $top->map(fn ($row) => $row + [
            'name' => $this->nameOf($users->get($row['id'])),
            'email' => $users->get($row['id'])?->email,
            'avatar' => $this->photoOf($users->get($row['id'])),
        ])->all();
    }

    private function inquiries(int $vendorId, Carbon $from, Carbon $to): array
    {
        $base = CustomerInquiry::query()->where('vendor_id', $vendorId);
        $answered = (clone $base)->whereNotNull('answered_at')->whereBetween('answered_at', [$from, $to])->get(['created_at', 'answered_at']);

        $recent = (clone $base)->with(['customer:id,name,surname,profile_picture', 'product' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'product_name')])
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")->orderByDesc('created_at')->limit(6)
            ->get(['id', 'customer_id', 'product_id', 'question', 'status', 'created_at', 'answered_at']);

        return [
            'open' => (clone $base)->where('status', 'open')->count(),
            'received' => (clone $base)->whereBetween('created_at', [$from, $to])->count(),
            'answered' => $answered->count(),
            'avg_answer_hours' => $this->averageHours($answered->map(fn ($i) => [$i->created_at, $i->answered_at])),
            'recent' => $recent->map(fn ($i) => [
                'id' => $i->id,
                'customer' => $this->nameOf($i->customer),
                'avatar' => $this->photoOf($i->customer),
                'product' => $i->product?->product_name,
                'question' => $i->question,
                'status' => $i->status,
                'asked_at' => $i->created_at?->toIso8601String(),
                'overdue' => $i->status === 'open' && $i->created_at?->lt(now()->subHours(self::OVERDUE_HOURS)),
            ])->all(),
        ];
    }

    /** What is still waiting on the shop, right now */
    private function issues(int $vendorId): array
    {
        $openInquiries = CustomerInquiry::query()->where('vendor_id', $vendorId)->where('status', 'open');
        $requests = OrderRequest::query()
            ->whereHas('order', fn ($q) => $q->where('vendor_id', $vendorId))
            ->whereIn('status', OrderRequest::OPEN_STATUSES);
        $unread = Conversation::query()->where('vendor_id', $vendorId)->where('unread_count_vendor', '>', 0)->whereNull('vendor_archived_at');

        $items = [
            'unanswered_inquiries' => (clone $openInquiries)->count(),
            'overdue_inquiries' => (clone $openInquiries)->where('created_at', '<', now()->subHours(self::OVERDUE_HOURS))->count(),
            'open_requests' => (clone $requests)->count(),
            'oldest_request_at' => (clone $requests)->min('created_at'),
            'unread_chats' => (clone $unread)->count(),
            'unread_messages' => (int) (clone $unread)->sum('unread_count_vendor'),
        ];
        $items['total'] = $items['unanswered_inquiries'] + $items['open_requests'] + $items['unread_chats'];
        if ($items['oldest_request_at']) {
            $items['oldest_request_at'] = Carbon::parse($items['oldest_request_at'])->toIso8601String();
        }

        return $items;
    }

    private function satisfaction(int $vendorId, Carbon $from, Carbon $to, Carbon $pFrom, Carbon $pTo): array
    {
        // The shop's products, including removed ones (their reviews still count)
        $products = Product::withoutGlobalScopes()->where(fn ($q) => $q->where('vendor_id', $vendorId)->orWhere('owner_id', $vendorId))->select('id');
        $reviews = fn () => ProductReview::query()->whereNull('hidden_at')->whereIn('product_id', $products);
        $period = $reviews()->whereBetween('created_at', [$from, $to])->get(['rating']);
        $all = $reviews()->selectRaw('COUNT(*) as n, AVG(rating) as avg')->first();
        $before = $reviews()->whereBetween('created_at', [$pFrom, $pTo])->avg('rating');

        $latest = $reviews()->with(['user:id,name,surname,profile_picture', 'product' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'product_name')])->latest()->limit(3)
            ->get(['id', 'product_id', 'user_id', 'rating', 'comment', 'is_anonymous', 'created_at']);

        return [
            'average' => $period->count() ? round((float) $period->avg('rating'), 2) : null,
            'previous_average' => $before !== null ? round((float) $before, 2) : null,
            'count' => $period->count(),
            'all_time_average' => $all->n ? round((float) $all->avg, 2) : null,
            'all_time_count' => (int) $all->n,
            // 5 stars down to 1
            'distribution' => collect([5, 4, 3, 2, 1])->map(fn ($s) => ['stars' => $s, 'count' => $period->where('rating', $s)->count()])->all(),
            'happy_rate' => $period->count() ? round($period->where('rating', '>=', 4)->count() / $period->count() * 100, 1) : null,
            'latest' => $latest->map(fn ($r) => [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'comment' => $r->comment,
                'product' => $r->product?->product_name,
                'customer' => $r->is_anonymous ? 'Anonymous' : $this->nameOf($r->user),
                'at' => $r->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /** How fast customers hear back in chat: each customer message to the shop's next reply */
    private function chatResponse(int $vendorId, Carbon $from, Carbon $to): array
    {
        $conversations = Conversation::query()->where('vendor_id', $vendorId)->pluck('customer_id', 'id');
        if ($conversations->isEmpty()) {
            return ['avg_reply_minutes' => null, 'replied' => 0, 'waiting' => 0, 'within_hour_rate' => null, 'conversations' => 0];
        }

        $messages = Message::query()
            ->whereIn('conversation_id', $conversations->keys())
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('conversation_id')->orderBy('created_at')
            ->get(['conversation_id', 'sender_id', 'created_at']);

        $waits = [];
        $waiting = 0;
        foreach ($messages->groupBy('conversation_id') as $conversationId => $thread) {
            $askedAt = null;
            foreach ($thread as $m) {
                $fromCustomer = (int) $m->sender_id === (int) $conversations[$conversationId];
                if ($fromCustomer) {
                    $askedAt ??= $m->created_at; // a run of messages waits from the first one
                } elseif ($askedAt) {
                    $waits[] = $askedAt->diffInMinutes($m->created_at);
                    $askedAt = null;
                }
            }
            if ($askedAt) {
                $waiting++;
            }
        }

        return [
            'avg_reply_minutes' => $waits ? (int) round(array_sum($waits) / count($waits)) : null,
            'replied' => count($waits),
            'waiting' => $waiting,
            'within_hour_rate' => $waits ? round(count(array_filter($waits, fn ($w) => $w <= 60)) / count($waits) * 100, 1) : null,
            'conversations' => $messages->pluck('conversation_id')->unique()->count(),
        ];
    }

    private function averageHours(Collection $pairs): ?float
    {
        if ($pairs->isEmpty()) {
            return null;
        }

        return round($pairs->avg(fn ($p) => $p[0]->diffInMinutes($p[1])) / 60, 1);
    }

    private function nameOf(?User $user): string
    {
        return $user ? trim("{$user->name} {$user->surname}") ?: 'Customer' : 'Former customer';
    }

    private function photoOf(?User $user): ?string
    {
        $pic = $user?->profile_picture;
        if (! $pic) {
            return null;
        }

        return str_starts_with($pic, 'http') ? $pic : CloudinaryHelper::getUrl($pic);
    }
}
