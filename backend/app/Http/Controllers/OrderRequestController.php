<?php

namespace App\Http\Controllers;

use App\Models\OrderRequest;
use App\Services\ReturnCaseService;
use App\Traits\ScopesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * The shop's side of return/refund cases. Used by the vendor owner (vendor
 * portal) and by staff with the Deliveries module (ERP Supply Chain).
 */
class OrderRequestController extends Controller
{
    use ScopesOwner;

    /** Grouping used by the list filters */
    private const STATUS_FILTERS = [
        'pending' => ['pending'],
        'approved' => ['approved'],
        'open' => ['pending', 'approved'],
        'completed' => ['completed'],
        'rejected' => ['rejected', 'withdrawn'],
    ];

    public function __construct(private ReturnCaseService $cases) {}

    /** GET /api/sc/order-requests */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'type' => ['nullable', 'string', 'in:return,refund'],
            'status' => ['nullable', 'string', 'in:' . implode(',', array_keys(self::STATUS_FILTERS))],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $ownerId = $this->getOwnerId();
            $query = $this->scoped($ownerId)
                ->with(['order:id,order_number,status,payment_status,payment_method,total_amount,vendor_id', 'user:id,name,surname,email'])
                ->when($request->filled('type'), fn ($q) => $q->where('type', $request->type))
                ->when($request->filled('status'), fn ($q) => $q->whereIn('status', self::STATUS_FILTERS[$request->status]))
                ->when($request->filled('search'), function ($q) use ($request) {
                    $like = '%' . mb_strtolower($request->search) . '%';
                    $q->where(fn ($inner) => $inner
                        ->whereHas('order', fn ($oq) => $oq->whereRaw('lower(order_number) like ?', [$like]))
                        ->orWhereHas('user', fn ($uq) => $uq->whereRaw('lower(name) like ?', [$like])));
                })
                ->orderByRaw("case when status in ('pending','approved') then 0 else 1 end")
                ->orderByDesc('last_activity_at')
                ->orderByDesc('id');

            $page = $query->paginate($request->input('per_page', 20));

            return response()->json([
                'success' => true,
                'data' => collect($page->items())->map(fn (OrderRequest $r) => $this->format($r))->all(),
                'meta' => [
                    'current_page' => $page->currentPage(),
                    'last_page' => $page->lastPage(),
                    'total' => $page->total(),
                    'per_page' => $page->perPage(),
                ],
                'counts' => $this->counts($ownerId),
            ]);
        } catch (\Throwable $e) {
            Log::error('[OrderRequestController] index: ' . $e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to load requests.'], 500);
        }
    }

    /** GET /api/sc/order-requests/{id} */
    public function show(int $id): JsonResponse
    {
        $case = $this->find($id);

        return $case
            ? response()->json(['success' => true, 'data' => $this->cases->detail($case) + ['customer' => $this->customer($case)]])
            : response()->json(['success' => false, 'message' => 'Request not found'], 404);
    }

    /** POST /api/sc/order-requests/{id}/{action} */
    public function act(Request $request, int $id, string $action): JsonResponse
    {
        $case = $this->find($id);
        if (!$case) {
            return response()->json(['success' => false, 'message' => 'Request not found'], 404);
        }
        $actor = $this->cases->vendorActor($request->user());

        try {
            [$case, $message] = match ($action) {
                // "approve" is what older screens send: accept what the customer asked
                'accept', 'approve' => [$this->cases->accept($case, $actor, $request->input('note') ?? $request->input('admin_notes')), 'Agreed with the customer.'],
                'offer' => $this->offer($request, $case, $actor),
                'reject' => $this->rejectCase($request, $case, $actor),
                'message' => $this->message($request, $case, $actor),
                'item-received' => [$this->cases->itemReceived($case, $actor, $request->input('note')), 'Marked the returned item as received.'],
                'refund-sent' => $this->refundSent($request, $case, $actor),
                'replacement-sent' => [$this->cases->replacementSent($case, $actor, $request->validate(['note' => ['nullable', 'string', 'max:255']])['note'] ?? null), 'Marked the replacement as sent. The customer will confirm when it arrives.'],
                default => throw new RuntimeException('Unknown action.'),
            };
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['success' => true, 'message' => $message, 'data' => $this->cases->detail($case) + ['customer' => $this->customer($case)]]);
    }

    /** Older screens call these two directly */
    public function approve(Request $request, int $id): JsonResponse
    {
        return $this->act($request, $id, 'accept');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        return $this->act($request, $id, 'reject');
    }

    // ── Actions with input ───────────────────────────────────────────────

    private function offer(Request $request, OrderRequest $case, array $actor): array
    {
        $data = $request->validate([
            'resolution' => ['required', 'in:full_refund,partial_refund,replacement'],
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        return [$this->cases->offer($case, $actor, $data['resolution'], isset($data['amount']) ? (float) $data['amount'] : null, $data['note'] ?? null), 'Offer sent to the customer.'];
    }

    private function rejectCase(Request $request, OrderRequest $case, array $actor): array
    {
        $reason = trim((string) ($request->input('reason') ?? $request->input('admin_notes') ?? ''));

        return [$this->cases->reject($case, $actor, $reason !== '' ? mb_substr($reason, 0, 500) : 'Declined by the shop.'), 'Request declined.'];
    }

    private function message(Request $request, OrderRequest $case, array $actor): array
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,mp4,mov', 'max:20480'],
        ]);

        return [$this->cases->message($case, $actor, $data['body'], $request->file('media')), 'Message sent.'];
    }

    private function refundSent(Request $request, OrderRequest $case, array $actor): array
    {
        $data = $request->validate([
            'method' => ['required', 'in:' . implode(',', array_keys(ReturnCaseService::REFUND_METHODS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        return [
            $this->cases->refundSent($case, $actor, $data['method'], $data['reference'] ?? null, $data['note'] ?? null),
            'Marked the refund as sent. It is recorded once the customer confirms they received it.',
        ];
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    private function scoped(?int $ownerId)
    {
        return OrderRequest::query()->when($ownerId, fn ($q) => $q->whereHas('order', fn ($oq) => $oq->where('vendor_id', $ownerId)));
    }

    private function find(int $id): ?OrderRequest
    {
        return $this->scoped($this->getOwnerId())->with(['user:id,name,surname,email'])->find($id);
    }

    private function customer(OrderRequest $case): ?array
    {
        $case->loadMissing('user:id,name,surname,email');

        return $case->user ? [
            'name' => trim($case->user->name . ' ' . ($case->user->surname ?? '')),
            'email' => $case->user->email,
        ] : null;
    }

    private function format(OrderRequest $r): array
    {
        return $this->cases->summary($r) + [
            'customer' => $this->customer($r),
            'order' => $r->order ? [
                'id' => $r->order->id,
                'order_number' => $r->order->order_number,
                'status' => $r->order->status,
                'payment_status' => $r->order->payment_status,
                'payment_method' => $r->order->payment_method,
                'total_amount' => (float) ($r->order->total_amount ?? 0),
            ] : null,
            // Older screens read these
            'admin_notes' => $r->admin_notes,
        ];
    }

    private function counts(?int $ownerId): array
    {
        $byStatus = $this->scoped($ownerId)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $needsShop = $this->scoped($ownerId)->whereIn('status', OrderRequest::OPEN_STATUSES)->get(['id', 'status', 'type', 'offer_by', 'item_received_at', 'settled_resolution', 'refund_sent_at', 'replacement_sent_at'])
            ->filter(fn (OrderRequest $r) => $r->nextActor() === 'vendor')->count();

        return [
            'all' => (int) $byStatus->sum(),
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'approved' => (int) ($byStatus['approved'] ?? 0),
            'open' => (int) (($byStatus['pending'] ?? 0) + ($byStatus['approved'] ?? 0)),
            'completed' => (int) ($byStatus['completed'] ?? 0),
            'rejected' => (int) (($byStatus['rejected'] ?? 0) + ($byStatus['withdrawn'] ?? 0)),
            'needs_shop' => $needsShop,
            'returns' => $this->scoped($ownerId)->where('type', 'return')->count(),
            'refunds' => $this->scoped($ownerId)->where('type', 'refund')->count(),
        ];
    }
}
