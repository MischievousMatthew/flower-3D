<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PurchaseOrder;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Throwable;

use App\Traits\ScopesOwner;
 
class OrderController extends Controller
{
    use ScopesOwner;
 
    public function __construct(private readonly OrderService $service) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'status'      => ['nullable', 'string', Rule::in(self::STATUSES)],
            'stage'       => ['nullable', 'string', Rule::in(array_keys(PurchaseOrder::STAGES))],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'search'      => ['nullable', 'string', 'max:100'],
            'from'        => ['nullable', 'date'],
            'to'          => ['nullable', 'date'],
            'overdue'     => ['nullable', 'boolean'],
            'per_page'    => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        // The paginator's own fields stay as they were; counts sit beside them
        return response()->json([
            ...$this->service->listOrders($this->getOwnerId(), $filters)->toArray(),
            'counts' => $this->service->stageCounts($this->getOwnerId()),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        return response()->json($this->service->findOrder($id, $this->getOwnerId()));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'supplier_id'          => ['required', 'integer', 'exists:suppliers,id'],
            // draft: save to finish later · submit: ask for approval · approve: approve and send now
            'action'               => ['nullable', Rule::in(['draft', 'submit', 'approve'])],
            'send_email'           => ['nullable', 'boolean'],
            ...$this->detailRules(),
            'items'                => ['required', 'array', 'min:1'],
            ...$this->itemRules(),
        ]);

        // Without an action, orders start with the supplier, as they always did
        $action = $data['action'] ?? null;
        if ($action === 'approve' && ! $this->canApprove($request)) {
            return $this->noApproval();
        }
        $data['status'] = ['draft' => 'draft', 'submit' => 'pending_approval', 'approve' => 'pending'][$action] ?? 'pending';

        try {
            $order = $this->service->createOrder($data, $this->getOwnerId());
        } catch (\RuntimeException $e) {
            return $this->sayWhy($e);
        }

        $emailed = $action === 'approve' && ($data['send_email'] ?? false) ? $this->service->emailSupplier($order) : null;

        return response()->json($order->fresh(['supplier', 'items'])->setAttribute('emailed', $emailed), 201);
    }

    /** PUT /orders/{id}: change a draft or an order waiting for approval */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['sometimes', 'integer', 'exists:suppliers,id'],
            ...$this->detailRules(),
            'items'       => ['sometimes', 'array', 'min:1'],
            ...$this->itemRules(),
        ]);

        try {
            return response()->json($this->service->updateDraft($id, $data, $this->getOwnerId()));
        } catch (\RuntimeException $e) {
            return $this->sayWhy($e);
        }
    }

    /** POST /orders/{id}/submit: draft → waiting for approval */
    public function submit(int $id): JsonResponse
    {
        try {
            return response()->json($this->service->submitForApproval($id, $this->getOwnerId()));
        } catch (\RuntimeException $e) {
            return $this->sayWhy($e);
        }
    }

    /** POST /orders/{id}/approve: → ordered, optionally emailing the supplier */
    public function approve(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'send_email' => ['nullable', 'boolean'],
            'note'       => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $result = $this->service->approve($id, $this->getOwnerId(), (bool) ($data['send_email'] ?? true), $data['note'] ?? null);
        } catch (\RuntimeException $e) {
            return $this->sayWhy($e);
        }

        return response()->json($result['order']->setAttribute('emailed', $result['emailed']));
    }

    /** POST /orders/{id}/send-back: waiting for approval → draft, with the reason */
    public function sendBack(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']], [
            'reason.required' => 'Say what needs to change, so whoever wrote it can fix it.',
        ]);

        try {
            return response()->json($this->service->sendBack($id, $this->getOwnerId(), $data['reason']));
        } catch (\RuntimeException $e) {
            return $this->sayWhy($e);
        }
    }

    /** POST /orders/{id}/email: send the order to the supplier again */
    public function email(int $id): JsonResponse
    {
        $order = $this->service->findOrder($id, $this->getOwnerId());
        if (in_array($order->status, PurchaseOrder::EDITABLE, true)) {
            return response()->json(['message' => 'Approve the order before sending it to the supplier.'], 422);
        }
        $ok = $this->service->emailSupplier($order);
        if ($ok === null) {
            return response()->json(['message' => "{$order->supplier?->company_name} has no email address. Add one on the supplier's details, or print the order instead."], 422);
        }
        if (! $ok) {
            return response()->json(['message' => "The email couldn't be sent right now. Try again in a few minutes, or print the order and send it yourself."], 502);
        }

        return response()->json(['message' => "Sent to {$order->supplier->email}.", 'sent_at' => $order->fresh()->sent_at]);
    }

    /** POST /orders/{id}/receive: record what arrived; stock goes into the warehouse */
    public function receive(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'items'       => ['required', 'array', 'min:1'],
            'items.*.id'  => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:0'],
            'note'        => ['nullable', 'string', 'max:500'],
        ]);

        try {
            return response()->json($this->service->receive($id, $data['items'], $this->getOwnerId(), $data['note'] ?? null));
        } catch (\RuntimeException $e) {
            return $this->sayWhy($e);
        }
    }

    /** DELETE /orders/{id}: only drafts and orders waiting for approval */
    public function destroy(int $id): JsonResponse
    {
        try {
            $this->service->deleteOrder($id, $this->getOwnerId());
        } catch (\RuntimeException $e) {
            return $this->sayWhy($e);
        }

        return response()->json(['message' => 'Order deleted.']);
    }

    private const STATUSES = ['draft', 'pending_approval', 'pending', 'processing', 'shipped', 'partially_received', 'received', 'completed'];

    private function detailRules(): array
    {
        return [
            'order_date'    => ['nullable', 'date'],
            'expected_date' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes'         => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function itemRules(): array
    {
        return [
            'items.*.product_name'        => ['required', 'string', 'max:255'],
            'items.*.quantity'            => ['required', 'integer', 'min:1'],
            'items.*.price'               => ['required', 'numeric', 'min:0'],
            'items.*.unit'                => ['nullable', 'string', 'max:20'],
            'items.*.product_id'          => ['nullable', 'integer'],
            'items.*.supplier_product_id' => ['nullable', 'integer'],
        ];
    }

    /** The shop owner can always approve; staff need the Approve permission on orders */
    private function canApprove(Request $request): bool
    {
        $user = $request->user();

        return ! $user instanceof Employee || $user->hasModulePermission('sc_orders', 'approve');
    }

    private function noApproval(): JsonResponse
    {
        return response()->json([
            'message' => "You can save this as a draft or send it for approval, but approving orders needs the Approve permission on Orders. Ask the shop owner.",
            'code'    => 'forbidden_permission',
        ], 403);
    }

    /** Rule breaks (wrong stage, nothing to receive) come back as plain messages */
    private function sayWhy(\RuntimeException $e): JsonResponse
    {
        return response()->json(['message' => $e->getMessage()], 422);
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(['pending', 'processing', 'shipped', 'received', 'completed'])],
        ]);

        try {
            return response()->json($this->service->updateOrderStatus($id, $data['status'], $this->getOwnerId()));
        } catch (Throwable $e) {
            Log::error('Order status update failed', [
                'error' => $e->getMessage(),
                'order_id' => $id,
                'payload' => $request->all(),
                'user_id' => auth()->id(),
                'owner_id' => $this->getOwnerId(),
            ]);

            throw $e;
        }
    }

    public function attachItems(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'items'                => ['required', 'array', 'min:1'],
            'items.*.product_name' => ['required', 'string', 'max:255'],
            'items.*.quantity'     => ['required', 'integer', 'min:1'],
            'items.*.price'        => ['required', 'numeric', 'min:0'],
        ]);

        return response()->json($this->service->attachItems($id, $data['items'], $this->getOwnerId()));
    }

    public function removeItem(int $id, int $itemId): JsonResponse
    {
        return response()->json($this->service->removeItem($id, $itemId, $this->getOwnerId()));
    }

    public function recalculateTotals(int $id): JsonResponse
    {
        return response()->json($this->service->calculateTotals($id, $this->getOwnerId()));
    }

    public function approvedFundingRequests(Request $request): JsonResponse
    {
        // The shop owner has no owner_id of their own; staff do
        return response()->json($this->service->getApprovedFundingRequests($this->getOwnerId()));
    }

    /** GET /orders/stock-items: the shop's products, so order lines can be tied to stock */
    public function stockItems(Request $request): JsonResponse
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:100']]);
        $term = isset($data['search']) ? '%' . mb_strtolower($data['search']) . '%' : null;

        $items = \Illuminate\Support\Facades\DB::table('products')
            ->where('owner_id', $this->getOwnerId())
            ->whereNull('deleted_at')
            ->when($term, fn ($q) => $q->where(fn ($w) => $w->whereRaw('LOWER(product_name) LIKE ?', [$term])->orWhereRaw('LOWER(sku) LIKE ?', [$term])))
            ->orderBy('product_name')
            ->limit(300)
            ->get(['id', 'product_name', 'sku', 'quantity_in_stock', 'min_stock_level']);

        return response()->json(['data' => $items]);
    }

    public function createFromFunding(Request $request, int $fundingRequestId): JsonResponse
    {
        $data = $request->validate([
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
        ]);

        return response()->json(
            $this->service->createOrderFromFunding($fundingRequestId, $data['supplier_id'], $this->getOwnerId()),
            201
        );
    }

    public function statusCounts(Request $request): JsonResponse
    {
        $ownerId = $this->getOwnerId();

        $counts = PurchaseOrder::where('owner_id', $ownerId)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

            \Log::info('statusCounts user: ' . json_encode([
                'user' => $request->user(),
                'owner_id' => $request->user()?->owner_id,
            ]));

        return response()->json([
            'data' => [
                'pending'    => $counts['pending']    ?? 0,
                'processing' => $counts['processing'] ?? 0,
                'shipped'    => $counts['shipped']    ?? 0,
                'received'   => $counts['received']   ?? 0,
                'completed'  => $counts['completed']  ?? 0,
            ]
        ]);
    }
}
