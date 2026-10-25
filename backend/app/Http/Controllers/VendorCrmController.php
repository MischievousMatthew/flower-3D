<?php

namespace App\Http\Controllers;

use App\Helpers\CloudinaryHelper;
use App\Models\CustomerInquiry;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\VendorNotificationService;
use App\Traits\ResolvesOwner;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The vendor portal's Customer relationships pages: inquiries, reviews &
 * ratings, notifications, and what the chat page shows about a customer.
 */
class VendorCrmController extends Controller
{
    use ResolvesOwner;

    public function __construct(private VendorNotificationService $notifications) {}

    // ── Inquiries ──────────────────────────────────────────────────────────

    /** GET /vendor/crm/inquiries?status&about&search&page */
    public function inquiries(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $vendorId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'status' => 'nullable|in:open,answered,closed',
            'about' => 'nullable|in:product,order',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);

        $base = CustomerInquiry::query()->where('vendor_id', $vendorId);
        $page = (clone $base)
            ->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when(($data['about'] ?? null) === 'product', fn ($q) => $q->whereNotNull('product_id'))
            ->when(($data['about'] ?? null) === 'order', fn ($q) => $q->whereNotNull('order_id'))
            ->when($data['search'] ?? null, function ($q, $term) {
                $like = '%' . mb_strtolower($term) . '%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(question) LIKE ?', [$like])
                    ->orWhereHas('customer', fn ($c) => $c->whereRaw("LOWER(name || ' ' || COALESCE(surname, '')) LIKE ?", [$like]))
                    ->orWhereHas('product', fn ($p) => $p->whereRaw('LOWER(product_name) LIKE ?', [$like]))
                    ->orWhereHas('order', fn ($o) => $o->whereRaw('LOWER(order_number) LIKE ?', [$like])));
            })
            // Open questions first, oldest waiting at the top; then the rest newest first
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByRaw("CASE WHEN status = 'open' THEN created_at END ASC")
            ->orderByDesc('created_at')
            ->with(['customer:id,name,surname,profile_picture', 'product' => fn ($q) => $q->with('images'), 'order:id,order_number,status,total_amount,created_at'])
            ->paginate(20);

        $counts = (clone $base)->selectRaw('status, COUNT(*) c')->groupBy('status')->pluck('c', 'status');
        $answered = (clone $base)->whereNotNull('answered_at')->get(['created_at', 'answered_at']);
        $avgHours = $answered->isEmpty() ? null : round($answered->avg(fn ($q) => $q->created_at->diffInMinutes($q->answered_at)) / 60, 1);

        return response()->json([
            'success' => true,
            'data' => [
                'inquiries' => collect($page->items())->map(fn ($q) => $this->formatInquiry($q)),
                'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
                'counts' => [
                    'open' => (int) ($counts['open'] ?? 0),
                    'answered' => (int) ($counts['answered'] ?? 0),
                    'closed' => (int) ($counts['closed'] ?? 0),
                ],
                'avg_reply_hours' => $avgHours,
            ],
        ]);
    }

    /** POST /vendor/crm/inquiries/{id}/answer */
    public function answer(Request $request, int $id): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $inquiry = $this->inquiryFor($request, $id);
        $data = $request->validate([
            'answer' => 'required|string|min:2|max:2000',
            'is_public' => 'nullable|boolean',
        ], ['answer.required' => 'Write your answer first.']);

        $first = $inquiry->answered_at === null;
        $inquiry->update([
            'answer' => trim($data['answer']),
            'answered_by' => $this->actorName($request),
            'answered_at' => now(),
            'status' => 'answered',
            'is_public' => $inquiry->product_id ? (bool) ($data['is_public'] ?? $inquiry->is_public) : false,
            'customer_seen_at' => null,
        ]);

        // Tell the customer
        UserNotification::create([
            'user_id' => $inquiry->customer_id,
            'type' => 'inquiry_answer',
            'title' => ($first ? 'Your question was answered' : 'The shop updated its answer') . ' · ' . $this->storeName($inquiry->vendor_id),
            'body' => mb_strimwidth($inquiry->answer, 0, 200, '…'),
            'link_url' => $inquiry->order_id ? '/customer/orders' : null,
        ]);

        return response()->json(['success' => true, 'message' => $first ? 'Answer sent. The customer has been notified.' : 'Answer updated.', 'data' => $this->formatInquiry($inquiry->fresh(['customer', 'product.images', 'order']))]);
    }

    /** POST /vendor/crm/inquiries/{id}/status {status: closed|open, is_public?} */
    public function inquiryStatus(Request $request, int $id): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $inquiry = $this->inquiryFor($request, $id);
        $data = $request->validate(['status' => 'nullable|in:open,closed', 'is_public' => 'nullable|boolean']);

        if (isset($data['status'])) {
            $inquiry->status = $data['status'] === 'open' ? ($inquiry->answered_at ? 'answered' : 'open') : 'closed';
        }
        if (array_key_exists('is_public', $data) && $inquiry->product_id) {
            $inquiry->is_public = (bool) $data['is_public'];
        }
        $inquiry->save();

        $message = isset($data['status'])
            ? ($data['status'] === 'closed' ? 'Marked as done.' : 'Reopened.')
            : ($inquiry->is_public ? 'Shown on the product page.' : 'Hidden from the product page.');

        return response()->json(['success' => true, 'message' => $message, 'data' => $this->formatInquiry($inquiry->fresh(['customer', 'product.images', 'order']))]);
    }

    // ── Reviews & ratings ──────────────────────────────────────────────────

    /** GET /vendor/crm/reviews?rating&product&replied&search&page */
    public function reviews(Request $request): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $data = $request->validate([
            'rating' => 'nullable|in:1,2,3,4,5,low', // low = 1 or 2 stars
            'product' => 'nullable|integer',
            'replied' => 'nullable|in:yes,no',
            'search' => 'nullable|string|max:100',
            'sort' => 'nullable|in:newest,oldest,lowest,highest',
            'page' => 'nullable|integer|min:1',
        ]);

        $products = Product::withoutGlobalScopes()->where('owner_id', $vendorId)->get(['id', 'product_name']);
        $names = $products->pluck('product_name', 'id');
        $base = ProductReview::query()->whereIn('product_id', $products->pluck('id'));
        $canReply = $this->replyReady();

        $query = (clone $base)
            ->when($data['rating'] ?? null, fn ($q, $r) => $r === 'low' ? $q->where('rating', '<=', 2) : $q->where('rating', (int) $r))
            ->when($data['product'] ?? null, fn ($q, $p) => $q->where('product_id', $p))
            ->when($canReply && ($data['replied'] ?? null) === 'yes', fn ($q) => $q->whereNotNull('vendor_reply'))
            ->when($canReply && ($data['replied'] ?? null) === 'no', fn ($q) => $q->whereNull('vendor_reply'))
            ->when($data['search'] ?? null, fn ($q, $t) => $q->whereRaw('LOWER(comment) LIKE ?', ['%' . mb_strtolower($t) . '%']))
            ->with(['user:id,name', 'product' => fn ($q) => $q->withoutGlobalScopes()->with('images')]);
        match ($data['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at'),
            'lowest' => $query->orderBy('rating')->orderByDesc('created_at'),
            'highest' => $query->orderByDesc('rating')->orderByDesc('created_at'),
            default => $query->orderByDesc('created_at'),
        };
        $page = $query->paginate(15);

        $all = (clone $base)->get(array_merge(['product_id', 'rating', 'created_at', 'hidden_at'], $canReply ? ['vendor_reply'] : []));
        $visible = $all->whereNull('hidden_at');
        $since = now()->subDays(30);
        $recent = $visible->filter(fn ($r) => $r->created_at >= $since);
        $before = $visible->filter(fn ($r) => $r->created_at < $since);

        return response()->json([
            'success' => true,
            'data' => [
                'reviews' => collect($page->items())->map(fn (ProductReview $r) => [
                    'id' => $r->id,
                    'rating' => $r->rating,
                    'comment' => $r->comment,
                    'image_url' => $r->image_path ? CloudinaryHelper::getUrl($r->image_path) : null,
                    'reviewer' => $r->is_anonymous ? 'Anonymous customer' : ($r->user?->name ?? 'Customer'),
                    'anonymous' => (bool) $r->is_anonymous,
                    'product' => ['id' => $r->product_id, 'name' => $names[$r->product_id] ?? 'Removed product', 'image' => $this->productImage($r->product)],
                    'created_at' => $r->created_at?->toIso8601String(),
                    'verified' => $r->order_id !== null,
                    'hidden' => $r->hidden_at !== null,
                    'hidden_reason' => $r->hidden_at ? $r->hidden_reason : null,
                    'reply' => $canReply ? $r->vendor_reply : null,
                    'replied_at' => $canReply ? $r->vendor_replied_at?->toIso8601String() : null,
                ]),
                'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
                'summary' => [
                    'average' => $visible->count() ? round($visible->avg('rating'), 2) : null,
                    'count' => $visible->count(),
                    'distribution' => collect([5, 4, 3, 2, 1])->mapWithKeys(fn ($s) => [$s => $visible->where('rating', $s)->count()]),
                    'recent_average' => $recent->count() ? round($recent->avg('rating'), 2) : null,
                    'recent_count' => $recent->count(),
                    'previous_average' => $before->count() ? round($before->avg('rating'), 2) : null,
                    'unreplied' => $canReply ? $visible->whereNull('vendor_reply')->count() : null,
                    'low_unreplied' => $canReply ? $visible->whereNull('vendor_reply')->where('rating', '<=', 2)->count() : null,
                    'hidden' => $all->whereNotNull('hidden_at')->count(),
                ],
                'products' => $visible->groupBy('product_id')->map(fn ($g, $pid) => [
                    'id' => (int) $pid,
                    'name' => $names[$pid] ?? 'Removed product',
                    'average' => round($g->avg('rating'), 2),
                    'count' => $g->count(),
                ])->sortByDesc('count')->values(),
                'can_reply' => $canReply,
            ],
        ]);
    }

    /** POST /vendor/crm/reviews/{id}/reply {reply} — empty reply removes it */
    public function reply(Request $request, int $id): JsonResponse
    {
        if (! $this->replyReady()) {
            return $this->notReady(true);
        }
        $vendorId = $this->resolveOwnerId($request);
        $review = ProductReview::query()->whereKey($id)
            ->whereIn('product_id', Product::withoutGlobalScopes()->where('owner_id', $vendorId)->select('id'))
            ->firstOrFail();
        $data = $request->validate(['reply' => 'nullable|string|max:1000']);
        $text = trim((string) ($data['reply'] ?? ''));

        $review->forceFill([
            'vendor_reply' => $text !== '' ? $text : null,
            'vendor_replied_at' => $text !== '' ? now() : null,
        ])->save();

        if ($text !== '' && $review->user_id) {
            UserNotification::create([
                'user_id' => $review->user_id,
                'type' => 'review_reply',
                'title' => $this->storeName($vendorId) . ' replied to your review',
                'body' => mb_strimwidth($text, 0, 200, '…'),
            ]);
        }

        return response()->json(['success' => true, 'message' => $text !== '' ? 'Reply posted. Shoppers will see it under the review.' : 'Reply removed.']);
    }

    // ── Notifications ──────────────────────────────────────────────────────

    /** GET /vendor/crm/notifications?type&unread&page */
    public function notifications(Request $request): JsonResponse
    {
        $vendor = $this->vendor($request);
        $this->notifications->sync($vendor, true);
        $data = $request->validate([
            'type' => 'nullable|in:order,payment,stock,message,inquiry,review,return,announcement,support',
            'unread' => 'nullable|boolean',
            'page' => 'nullable|integer|min:1',
        ]);

        $base = UserNotification::query()->where('user_id', $vendor->id);
        $page = (clone $base)
            ->when($data['type'] ?? null, fn ($q, $t) => $t === 'support' ? $q->whereNotIn('type', ['order', 'payment', 'stock', 'message', 'inquiry', 'review', 'return', 'announcement', 'platform']) : $q->where('type', $t))
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            // Important notices stay on top until they're read
            ->orderByRaw('case when important = ? and read_at is null then 0 else 1 end', [true])
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => collect($page->items())->map(fn ($n) => [
                    'id' => $n->id,
                    'type' => in_array($n->type, ['order', 'payment', 'stock', 'message', 'inquiry', 'review', 'return', 'announcement'], true) ? $n->type : 'support',
                    'title' => $n->title,
                    'body' => $n->body,
                    'link_url' => $n->link_url,
                    'important' => (bool) $n->important,
                    'read' => $n->read_at !== null,
                    'created_at' => $n->created_at?->toIso8601String(),
                ]),
                'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
                'unread' => (clone $base)->whereNull('read_at')->count(),
                'unread_by_type' => (clone $base)->whereNull('read_at')->selectRaw('type, COUNT(*) c')->groupBy('type')->pluck('c', 'type'),
                'preferences' => $this->notifications->preferences($vendor),
                'types' => VendorNotificationService::TYPES,
            ],
        ]);
    }

    /** PUT /vendor/crm/notifications/preferences */
    public function savePreferences(Request $request): JsonResponse
    {
        if ($response = $this->notReady()) {
            return $response;
        }
        $data = $request->validate(['settings' => 'required|array', 'settings.*' => 'boolean']);
        $saved = $this->notifications->savePreferences($this->vendor($request), $data['settings']);

        return response()->json(['success' => true, 'message' => 'Notification settings saved.', 'data' => $saved]);
    }

    // ── Customers (chat side panel) ────────────────────────────────────────

    /** GET /vendor/crm/customers/{id} — what this customer has done with your shop */
    public function customer(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);
        $customer = User::findOrFail($id);
        $orders = Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('user_id', $id)
            ->orderByDesc('created_at')->get(['id', 'order_number', 'status', 'payment_status', 'total_amount', 'created_at', 'reservation_date']);
        $completed = $orders->filter(fn ($o) => ! in_array($o->status, ['cancelled', 'refunded'], true));

        $inquiries = $this->inquiriesReady()
            ? CustomerInquiry::query()->where('vendor_id', $vendorId)->where('customer_id', $id)->latest()->limit(5)->with(['product:id,product_name', 'order:id,order_number'])->get()
            : collect();

        return response()->json([
            'success' => true,
            'data' => [
                'name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->contact_number,
                'avatar' => $customer->avatar_url,
                'member_since' => $customer->created_at?->toDateString(),
                'stats' => [
                    'orders' => $orders->count(),
                    'spent' => round((float) $completed->sum('total_amount'), 2),
                    'first_order' => $orders->last()?->created_at?->toDateString(),
                    'last_order' => $orders->first()?->created_at?->toDateString(),
                ],
                'orders' => $orders->take(5)->map(fn ($o) => [
                    'id' => $o->id,
                    'order_number' => $o->order_number,
                    'status' => $o->status,
                    'total' => (float) $o->total_amount,
                    'created_at' => $o->created_at?->toIso8601String(),
                ])->values(),
                'inquiries' => $inquiries->map(fn ($q) => [
                    'id' => $q->id,
                    'about' => $q->product?->product_name ?? ($q->order ? "Order {$q->order->order_number}" : 'General'),
                    'status' => $q->status,
                    'created_at' => $q->created_at?->toIso8601String(),
                ])->values(),
            ],
        ]);
    }

    // ──────────────────────────────────────────────────────────────────────

    private function formatInquiry(CustomerInquiry $q): array
    {
        return [
            'id' => $q->id,
            'customer' => [
                'id' => $q->customer_id,
                'name' => $q->customer?->full_name ?? 'Customer',
                'avatar' => $q->customer?->avatar_url,
            ],
            'about' => $q->product_id ? 'product' : ($q->order_id ? 'order' : 'general'),
            'product' => $q->product ? ['id' => $q->product->id, 'name' => $q->product->product_name, 'image' => $this->productImage($q->product)] : null,
            'order' => $q->order ? [
                'id' => $q->order->id,
                'order_number' => $q->order->order_number,
                'status' => $q->order->status,
                'total' => (float) $q->order->total_amount,
            ] : null,
            'question' => $q->question,
            'answer' => $q->answer,
            'answered_by' => $q->answered_by,
            'answered_at' => $q->answered_at?->toIso8601String(),
            'status' => $q->status,
            'is_public' => (bool) $q->is_public,
            'seen_by_customer' => $q->customer_seen_at !== null,
            'created_at' => $q->created_at?->toIso8601String(),
        ];
    }

    private function inquiryFor(Request $request, int $id): CustomerInquiry
    {
        return CustomerInquiry::query()->where('vendor_id', $this->resolveOwnerId($request))->findOrFail($id);
    }

    private function productImage(?Product $p): ?string
    {
        if (! $p) {
            return null;
        }
        $img = $p->relationLoaded('images') ? $p->images->first() : null;
        if (! $img) {
            return null;
        }

        return $img->image_url ?: ($img->image_path ? CloudinaryHelper::getUrl($img->image_path) : null);
    }

    private function vendor(Request $request): User
    {
        return User::findOrFail($this->resolveOwnerId($request));
    }

    private function storeName(int $vendorId): string
    {
        $vendor = User::find($vendorId);

        return ($vendor ? DB::table('vendor_applications')->where('email', $vendor->email)->where('status', 'approved')->orderByDesc('id')->value('store_name') : null)
            ?? $vendor?->full_name ?? 'The shop';
    }

    private function actorName(Request $request): string
    {
        $user = $request->user();

        return $user instanceof \App\Models\Employee ? $user->name : 'Shop owner';
    }

    private function inquiriesReady(): bool
    {
        return VendorNotificationService::ready();
    }

    private function replyReady(): bool
    {
        return VendorNotificationService::ready() && \Illuminate\Support\Facades\Schema::hasColumn('product_reviews', 'vendor_reply');
    }

    private function notReady(bool $force = false): ?JsonResponse
    {
        return ! $force && $this->inquiriesReady() ? null : response()->json([
            'success' => false,
            'code' => 'crm_setting_up',
            'message' => 'This is still being set up. It will be ready after the next update.',
        ], 503);
    }
}
