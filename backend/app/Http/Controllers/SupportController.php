<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Support requests raised by vendors (store owners) and customers.
 * People only ever see their own requests.
 */
class SupportController extends Controller
{
    /** GET /api/support/tickets */
    public function index(Request $request): JsonResponse
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }

        $tickets = SupportTicket::query()->where('requester_id', $request->user()->id)
            ->orderByDesc('last_activity_at')->get();

        return response()->json([
            'success' => true,
            'data' => $tickets->map(fn ($t) => $this->row($t)),
            'categories' => SupportTicket::CATEGORIES,
        ]);
    }

    /** POST /api/support/tickets */
    public function store(Request $request): JsonResponse
    {
        if ($denied = $this->deny($request)) {
            return $denied;
        }
        $user = $request->user();

        $data = $request->validate([
            'subject' => 'required|string|min:4|max:160',
            'category' => ['required', Rule::in(array_keys(SupportTicket::CATEGORIES))],
            'message' => 'required|string|min:10|max:5000',
            'order_id' => 'nullable|integer',
        ]);

        // An order can only be attached by someone involved in it
        $orderId = null;
        if (!empty($data['order_id'])) {
            $orderId = Order::query()->whereKey($data['order_id'])
                ->where(fn ($q) => $q->where('user_id', $user->id)->orWhere('vendor_id', $user->id))
                ->value('id');
            if (!$orderId) {
                return response()->json(['success' => false, 'errors' => ['order_id' => ["That order isn't one of yours."]]], 422);
            }
        }

        $ticket = DB::transaction(function () use ($user, $data, $orderId) {
            $ticket = SupportTicket::create([
                'requester_id' => $user->id,
                'requester_role' => $user->role,
                'subject' => trim($data['subject']),
                'category' => $data['category'],
                'priority' => in_array($data['category'], ['payments', 'account'], true) ? 'high' : 'normal',
                'status' => 'open',
                'order_id' => $orderId,
            ]);
            SupportMessage::create([
                'ticket_id' => $ticket->id,
                'author_id' => $user->id,
                'from_admin' => false,
                'body' => trim($data['message']),
            ]);

            return $ticket;
        });

        return response()->json([
            'success' => true,
            'message' => "Request {$ticket->reference} sent. We'll reply here and let you know.",
            'data' => $this->detail($ticket->fresh()),
        ], 201);
    }

    /** GET /api/support/tickets/{id} */
    public function show(Request $request, int $id): JsonResponse
    {
        $ticket = $this->mine($request, $id);

        return $ticket ? response()->json(['success' => true, 'data' => $this->detail($ticket)]) : $this->notFound();
    }

    /** POST /api/support/tickets/{id}/messages */
    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = $this->mine($request, $id);
        if (!$ticket) {
            return $this->notFound();
        }
        if ($ticket->status === 'closed') {
            return response()->json(['success' => false, 'message' => 'This request is closed. Start a new one if you still need help.'], 422);
        }

        $data = $request->validate(['message' => 'required|string|min:2|max:5000']);
        SupportMessage::create([
            'ticket_id' => $ticket->id,
            'author_id' => $request->user()->id,
            'from_admin' => false,
            'body' => trim($data['message']),
        ]);
        // Writing back reopens a resolved request
        $ticket->update(['status' => 'open', 'last_activity_at' => now(), 'resolved_at' => null]);

        return response()->json(['success' => true, 'data' => $this->detail($ticket->fresh())]);
    }

    /** POST /api/support/tickets/{id}/resolve */
    public function resolve(Request $request, int $id): JsonResponse
    {
        $ticket = $this->mine($request, $id);
        if (!$ticket) {
            return $this->notFound();
        }
        $ticket->update(['status' => 'resolved', 'resolved_at' => now(), 'last_activity_at' => now()]);

        return response()->json(['success' => true, 'message' => 'Marked as solved. Thanks!', 'data' => $this->detail($ticket->fresh())]);
    }

    private function deny(Request $request): ?JsonResponse
    {
        $user = $request->user();

        return $user instanceof User && in_array($user->role, [User::ROLE_VENDOR, User::ROLE_CUSTOMER], true)
            ? null
            : response()->json(['success' => false, 'message' => 'Support requests are for vendors and customers.'], 403);
    }

    private function mine(Request $request, int $id): ?SupportTicket
    {
        if ($this->deny($request)) {
            return null;
        }

        return SupportTicket::query()->where('requester_id', $request->user()->id)->find($id);
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
            'last_activity_at' => $t->last_activity_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    private function detail(SupportTicket $t): array
    {
        $t->load(['messages', 'order:id,order_number']);

        return $this->row($t) + [
            'order' => $t->order ? ['id' => $t->order->id, 'order_number' => $t->order->order_number] : null,
            'messages' => $t->messages->map(fn ($m) => [
                'id' => $m->id,
                'from_admin' => $m->from_admin,
                // Individual admin names aren't shown to vendors/customers
                'author' => $m->from_admin ? 'BloomCraft Support' : 'You',
                'body' => $m->body,
                'created_at' => $m->created_at?->toIso8601String(),
            ]),
        ];
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Request not found.'], 404);
    }
}
