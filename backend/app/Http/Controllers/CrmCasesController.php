<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\CustomerRecords;
use App\Services\NotificationService;
use App\Support\VendorDirectory;
use App\Traits\ResolvesOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The shop's side of customer support (/crm/cases…). Cases are the existing
 * support tickets with vendor_id set: complaints customers file about the
 * shop's orders, and ones the shop records itself. The customer follows the
 * same ticket from their Help page; admins still see it.
 */
class CrmCasesController extends Controller
{
    use ResolvesOwner;

    /** Shown in this order on the page */
    public const STATUSES = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];

    public function __construct(private CustomerRecords $records, private NotificationService $notifications) {}

    /** GET /crm/cases?status&priority&assigned&search&page */
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in([...self::STATUSES, 'active'])],
            'priority' => ['nullable', Rule::in(SupportTicket::PRIORITIES)],
            'assigned' => 'nullable|string|max:20', // me | none | employee id
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
        ]);
        $vendorId = $this->resolveOwnerId($request);
        $base = SupportTicket::query()->where('vendor_id', $vendorId);

        $query = (clone $base)->with(['requester:id,name,surname,email', 'order:id,order_number', 'assignee:id,name']);
        $status = $data['status'] ?? 'active';
        $status === 'active' ? $query->whereNotIn('status', ['resolved', 'closed']) : $query->where('status', $status);
        if (! empty($data['priority'])) {
            $query->where('priority', $data['priority']);
        }
        $assigned = $data['assigned'] ?? null;
        if ($assigned === 'none') {
            $query->whereNull('assigned_employee_id');
        } elseif ($assigned === 'me' && $request->user() instanceof Employee) {
            $query->where('assigned_employee_id', $request->user()->id);
        } elseif ($assigned && ctype_digit($assigned)) {
            $query->where('assigned_employee_id', (int) $assigned);
        }
        if ($term = trim((string) ($data['search'] ?? ''))) {
            $like = '%' . $term . '%';
            $query->where(fn ($q) => $q->where('reference', 'like', $like)->orWhere('subject', 'like', $like)
                ->orWhereHas('requester', fn ($u) => $u->where('name', 'like', $like)->orWhere('surname', 'like', $like)->orWhere('email', 'like', $like))
                ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', $like)));
        }

        // Urgent first, then whatever has waited longest for the shop
        $page = $query->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('last_activity_at')->paginate(20, ['*'], 'page', $data['page'] ?? 1);

        $counts = (clone $base)->select('status', DB::raw('COUNT(*) as n'))->groupBy('status')->pluck('n', 'status');

        return response()->json(['success' => true, 'data' => [
            'rows' => collect($page->items())->map(fn ($t) => $this->row($t)),
            'meta' => ['total' => $page->total(), 'page' => $page->currentPage(), 'last_page' => $page->lastPage()],
            'counts' => collect(self::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($counts[$s] ?? 0)])->all()
                + ['active' => (int) collect(['open', 'in_progress', 'waiting'])->sum(fn ($s) => $counts[$s] ?? 0)],
            'urgent' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->whereIn('priority', ['urgent', 'high'])->count(),
            'unassigned' => (clone $base)->whereNotIn('status', ['resolved', 'closed'])->whereNull('assigned_employee_id')->count(),
            'staff' => $this->staff($vendorId),
            'categories' => collect(SupportTicket::SHOP_CATEGORIES)->map(fn ($c) => ['value' => $c, 'label' => SupportTicket::CATEGORIES[$c]])->values(),
        ]]);
    }

    /** GET /crm/cases/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $vendorId = $this->resolveOwnerId($request);

        return response()->json(['success' => true, 'data' => $this->detail($this->case($vendorId, $id))]);
    }

    /** GET /crm/cases/links?customer_id — the customer's orders and refund/return cases with this shop */
    public function links(Request $request): JsonResponse
    {
        $data = $request->validate(['customer_id' => 'required|integer']);
        $vendorId = $this->resolveOwnerId($request);
        abort_unless($this->records->isCustomerOf($vendorId, (int) $data['customer_id']), 404, 'This person isn\'t one of your customers.');

        return response()->json(['success' => true, 'data' => $this->linkOptions($vendorId, (int) $data['customer_id'])]);
    }

    /** POST /crm/cases — record a complaint or service issue for a customer */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => 'required|integer',
            'subject' => 'required|string|min:4|max:160',
            'category' => ['required', Rule::in(SupportTicket::SHOP_CATEGORIES)],
            'priority' => ['required', Rule::in(SupportTicket::PRIORITIES)],
            'message' => 'required|string|min:10|max:5000',
            'order_id' => 'nullable|integer',
            'order_request_id' => 'nullable|integer',
            'assigned_employee_id' => 'nullable|integer',
        ]);
        $vendorId = $this->resolveOwnerId($request);
        $customerId = (int) $data['customer_id'];
        abort_unless($this->records->isCustomerOf($vendorId, $customerId), 404, 'This person isn\'t one of your customers.');
        [$orderId, $requestId] = $this->checkLinks($vendorId, $customerId, $data['order_id'] ?? null, $data['order_request_id'] ?? null);
        $assignee = $this->checkAssignee($vendorId, $data['assigned_employee_id'] ?? null);

        $ticket = DB::transaction(function () use ($request, $data, $vendorId, $customerId, $orderId, $requestId, $assignee) {
            $ticket = SupportTicket::create([
                'requester_id' => $customerId,
                'requester_role' => User::ROLE_CUSTOMER,
                'vendor_id' => $vendorId,
                'subject' => trim($data['subject']),
                'category' => $data['category'],
                'priority' => $data['priority'],
                'status' => $assignee ? 'in_progress' : 'open',
                'order_id' => $orderId,
                'order_request_id' => $requestId,
                'assigned_employee_id' => $assignee,
                'recorded_by_shop' => true,
            ]);
            $this->post($request, $ticket, trim($data['message']), false);

            return $ticket;
        });

        $shop = VendorDirectory::nameOf($vendorId);
        $this->notifications->notify([$customerId], 'support', "{$shop} opened a support case for you", "{$ticket->reference}: {$ticket->subject}", '/customer/support');

        return response()->json(['success' => true, 'message' => "Case {$ticket->reference} recorded.", 'data' => $this->detail($ticket->fresh())], 201);
    }

    /** POST /crm/cases/{id}/reply {message, internal} */
    public function reply(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['message' => 'required|string|min:2|max:5000', 'internal' => 'boolean']);
        $vendorId = $this->resolveOwnerId($request);
        $ticket = $this->case($vendorId, $id);
        if ($ticket->status === 'closed') {
            return response()->json(['success' => false, 'message' => 'This case is closed. Reopen it to reply.'], 422);
        }
        $internal = (bool) ($data['internal'] ?? false);

        $this->post($request, $ticket, trim($data['message']), $internal);
        if (! $internal) {
            // Answering moves a new case along; the customer is told there's a reply
            $ticket->update(['status' => $ticket->status === 'open' ? 'in_progress' : $ticket->status, 'last_activity_at' => now()]);
            $shop = VendorDirectory::nameOf($vendorId);
            $this->notifications->notify([$ticket->requester_id], 'support', "{$shop} replied to {$ticket->reference}", $ticket->subject, '/customer/support');
        }

        return response()->json(['success' => true, 'data' => $this->detail($ticket->fresh())]);
    }

    /** PATCH /crm/cases/{id} {status?, priority?, assigned_employee_id?, order_id?, order_request_id?} */
    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(self::STATUSES)],
            'priority' => ['sometimes', Rule::in(SupportTicket::PRIORITIES)],
            'assigned_employee_id' => 'sometimes|nullable|integer',
            'order_id' => 'sometimes|nullable|integer',
            'order_request_id' => 'sometimes|nullable|integer',
        ]);
        $vendorId = $this->resolveOwnerId($request);
        $ticket = $this->case($vendorId, $id);
        $changes = [];
        $log = [];

        if (array_key_exists('priority', $data) && $data['priority'] !== $ticket->priority) {
            $changes['priority'] = $data['priority'];
            $log[] = 'priority set to ' . $data['priority'];
        }
        if (array_key_exists('assigned_employee_id', $data) && $data['assigned_employee_id'] !== $ticket->assigned_employee_id) {
            $changes['assigned_employee_id'] = $this->checkAssignee($vendorId, $data['assigned_employee_id']);
            $log[] = $changes['assigned_employee_id'] ? 'assigned to ' . Employee::find($changes['assigned_employee_id'])->name : 'unassigned';
        }
        if (array_key_exists('order_id', $data) || array_key_exists('order_request_id', $data)) {
            [$orderId, $requestId] = $this->checkLinks($vendorId, $ticket->requester_id,
                array_key_exists('order_id', $data) ? $data['order_id'] : $ticket->order_id,
                array_key_exists('order_request_id', $data) ? $data['order_request_id'] : $ticket->order_request_id);
            $changes += ['order_id' => $orderId, 'order_request_id' => $requestId];
            $log[] = 'linked records updated';
        }
        $statusChanged = array_key_exists('status', $data) && $data['status'] !== $ticket->status;
        if ($statusChanged) {
            $changes['status'] = $data['status'];
            $changes['resolved_at'] = in_array($data['status'], ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null;
            $log[] = 'status set to ' . str_replace('_', ' ', $data['status']);
        }
        if (! $changes) {
            return response()->json(['success' => true, 'data' => $this->detail($ticket)]);
        }

        $ticket->update($changes + ['last_activity_at' => now()]);
        // Kept in the history as a team-only note
        $this->post($request, $ticket, ucfirst(implode(', ', $log)) . '.', true);

        if ($statusChanged && in_array($data['status'], ['resolved', 'closed'], true)) {
            $shop = VendorDirectory::nameOf($vendorId);
            $this->notifications->notify([$ticket->requester_id], 'support', "{$shop} marked {$ticket->reference} as {$data['status']}", 'Reply on your Help page if you still need help.', '/customer/support');
        }

        return response()->json(['success' => true, 'data' => $this->detail($ticket->fresh())]);
    }

    private function post(Request $request, SupportTicket $ticket, string $body, bool $internal): void
    {
        $user = $request->user();
        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $user instanceof User ? $user->id : null,
            'from_admin' => false,
            'from_shop' => true,
            'is_internal' => $internal,
            'author_name' => $user instanceof Employee ? $user->name : trim("{$user->name} {$user->surname}") . ' (owner)',
            'body' => $body,
        ]);
        $ticket->update(['last_activity_at' => now()]);
    }

    private function row(SupportTicket $t): array
    {
        return [
            'id' => $t->id,
            'reference' => $t->reference,
            'subject' => $t->subject,
            'category' => $t->category,
            'category_label' => SupportTicket::CATEGORIES[$t->category] ?? $t->category,
            'status' => $t->status,
            'priority' => $t->priority,
            'customer' => $t->requester ? ['id' => $t->requester->id, 'name' => trim("{$t->requester->name} {$t->requester->surname}"), 'email' => $t->requester->email] : null,
            'order_number' => $t->order?->order_number,
            'assignee' => $t->assignee ? ['id' => $t->assignee->id, 'name' => $t->assignee->name] : null,
            'recorded_by_shop' => (bool) $t->recorded_by_shop,
            'created_at' => $t->created_at?->toIso8601String(),
            'last_activity_at' => $t->last_activity_at?->toIso8601String(),
        ];
    }

    private function detail(SupportTicket $t): array
    {
        $t->load(['requester:id,name,surname,email,contact_number', 'order', 'assignee:id,name', 'orderRequest', 'messages']);
        $refund = $t->orderRequest;

        return $this->row($t) + [
            'contact' => ['email' => $t->requester?->email, 'phone' => $t->requester?->contact_number],
            'order' => $t->order ? [
                'id' => $t->order->id, 'order_number' => $t->order->order_number, 'status' => $t->order->status,
                'payment_status' => $t->order->payment_status, 'total' => round((float) $t->order->total_amount, 2),
                'created_at' => $t->order->created_at?->toIso8601String(),
            ] : null,
            'refund' => $refund ? [
                'id' => $refund->id, 'type' => $refund->type, 'status' => $refund->status,
                'amount' => round((float) ($refund->settled_amount ?? $refund->amount_requested ?? 0), 2),
                'resolution' => $refund->settled_resolution ?? $refund->resolution_requested,
            ] : null,
            'messages' => $t->messages->map(fn ($m) => [
                'id' => $m->id,
                'side' => $m->from_admin ? 'admin' : ($m->from_shop ? 'shop' : 'customer'),
                'internal' => (bool) $m->is_internal,
                'author' => $m->from_admin ? 'BloomCraft Support' : ($m->from_shop ? ($m->author_name ?? 'Shop') : trim(($t->requester?->name ?? 'Customer') . ' ' . ($t->requester?->surname ?? ''))),
                'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
            ])->values(),
            'links' => $t->requester_id ? $this->linkOptions($t->vendor_id, $t->requester_id) : ['orders' => [], 'refunds' => []],
        ];
    }

    private function linkOptions(int $vendorId, int $customerId): array
    {
        $orders = Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('user_id', $customerId)->orderByDesc('created_at')->limit(30)
            ->get(['id', 'order_number', 'status', 'total_amount', 'created_at']);
        $refunds = OrderRequest::withoutGlobalScopes()->whereIn('order_id', $orders->pluck('id'))->orderByDesc('created_at')
            ->get(['id', 'order_id', 'type', 'status', 'amount_requested', 'settled_amount', 'created_at']);

        return [
            'orders' => $orders->map(fn ($o) => ['id' => $o->id, 'order_number' => $o->order_number, 'status' => $o->status, 'total' => round((float) $o->total_amount, 2), 'created_at' => $o->created_at?->toIso8601String()])->values(),
            'refunds' => $refunds->map(fn ($r) => [
                'id' => $r->id, 'order_id' => $r->order_id, 'order_number' => $orders->firstWhere('id', $r->order_id)?->order_number,
                'type' => $r->type, 'status' => $r->status, 'amount' => round((float) ($r->settled_amount ?? $r->amount_requested ?? 0), 2),
            ])->values(),
        ];
    }

    /** Only the customer's own orders and refund cases with this shop can be linked */
    private function checkLinks(int $vendorId, int $customerId, $orderId, $requestId): array
    {
        $orderId = $orderId ? (int) $orderId : null;
        $requestId = $requestId ? (int) $requestId : null;
        if ($orderId && ! Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('user_id', $customerId)->whereKey($orderId)->exists()) {
            abort(response()->json(['success' => false, 'errors' => ['order_id' => ['That isn\'t one of this customer\'s orders.']]], 422));
        }
        if ($requestId) {
            $refund = OrderRequest::withoutGlobalScopes()->find($requestId);
            $ok = $refund && Order::withoutGlobalScopes()->where('vendor_id', $vendorId)->where('user_id', $customerId)->whereKey($refund->order_id)->exists();
            if (! $ok) {
                abort(response()->json(['success' => false, 'errors' => ['order_request_id' => ['That refund isn\'t one of this customer\'s.']]], 422));
            }
            $orderId ??= (int) $refund->order_id; // a refund implies its order
        }

        return [$orderId, $requestId];
    }

    private function checkAssignee(int $vendorId, $employeeId): ?int
    {
        if (! $employeeId) {
            return null;
        }
        if (! Employee::where('owner_id', $vendorId)->whereKey($employeeId)->exists()) {
            abort(response()->json(['success' => false, 'errors' => ['assigned_employee_id' => ['Pick someone from your team.']]], 422));
        }

        return (int) $employeeId;
    }

    private function staff(int $vendorId): array
    {
        return Employee::where('owner_id', $vendorId)->where(fn ($q) => $q->whereNull('status')->orWhere('status', '!=', 'Inactive'))
            ->orderBy('name')->get(['id', 'name'])->map(fn ($e) => ['id' => $e->id, 'name' => $e->name])->all();
    }

    private function case(int $vendorId, int $id): SupportTicket
    {
        return SupportTicket::query()->where('vendor_id', $vendorId)->findOrFail($id);
    }
}
