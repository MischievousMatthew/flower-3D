<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Support\VendorDirectory;
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
        $categories = $this->categoriesFor($request->user());
        $role = $request->user()->role;

        return response()->json([
            'success' => true,
            'data' => $tickets->map(fn ($t) => $this->row($t)),
            'categories' => $categories,
            'shop_categories' => SupportTicket::SHOP_CATEGORIES,
            // Each topic with the exact problems under it, for the "what happened" picker
            'topics' => collect($categories)->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'hint' => SupportTicket::HINTS[$key] ?? '',
                'to_shop' => in_array($key, SupportTicket::SHOP_CATEGORIES, true),
                'issues' => collect(SupportTicket::issuesFor($key, $role))
                    ->map(fn ($issueLabel, $issueKey) => ['key' => $issueKey, 'label' => $issueLabel])->values(),
            ])->values(),
            'outcomes' => collect(SupportTicket::OUTCOMES)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ]);
    }

    /** Complaint topics go to a shop, so only customers get them; listings and plans are vendors' only */
    private function categoriesFor(User $user): array
    {
        $hidden = $user->role === User::ROLE_CUSTOMER ? SupportTicket::VENDOR_CATEGORIES : SupportTicket::SHOP_CATEGORIES;

        return array_diff_key(SupportTicket::CATEGORIES, array_flip($hidden));
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
            'issue' => 'nullable|string|max:40',
            'desired_outcome' => ['nullable', Rule::in(array_keys(SupportTicket::OUTCOMES))],
            'message' => 'required|string|min:10|max:5000',
            'order_id' => 'nullable|integer',
        ]);

        if (! array_key_exists($data['category'], $this->categoriesFor($user))) {
            return response()->json(['success' => false, 'errors' => ['category' => ['Pick another topic.']]], 422);
        }
        $issue = $data['issue'] ?? null;
        if ($issue !== null && ! array_key_exists($issue, SupportTicket::issuesFor($data['category'], $user->role))) {
            return response()->json(['success' => false, 'errors' => ['issue' => ['Pick what happened from the list.']]], 422);
        }
        // "What would you like done" only applies to a complaint to a shop
        $outcome = in_array($data['category'], SupportTicket::SHOP_CATEGORIES, true) ? ($data['desired_outcome'] ?? null) : null;

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

        // A customer's complaint about an order goes to the shop that sold it
        $vendorId = null;
        if (in_array($data['category'], SupportTicket::SHOP_CATEGORIES, true)) {
            if ($user->role !== User::ROLE_CUSTOMER) {
                return response()->json(['success' => false, 'errors' => ['category' => ['Pick another topic.']]], 422);
            }
            if (! $orderId) {
                return response()->json(['success' => false, 'errors' => ['order_id' => ['Pick the order this is about, so the shop can help.']]], 422);
            }
            $vendorId = (int) Order::withoutGlobalScopes()->whereKey($orderId)->value('vendor_id') ?: null;
        }

        $ticket = DB::transaction(function () use ($user, $data, $orderId, $vendorId, $issue, $outcome) {
            $ticket = SupportTicket::create([
                'requester_id' => $user->id,
                'requester_role' => $user->role,
                'subject' => trim($data['subject']),
                'category' => $data['category'],
                'issue' => $issue,
                'desired_outcome' => $outcome,
                'priority' => in_array($data['category'], ['payments', 'account'], true)
                    || in_array($issue, SupportTicket::HIGH_PRIORITY_ISSUES, true) ? 'high' : 'normal',
                'status' => 'open',
                'order_id' => $orderId,
                'vendor_id' => $vendorId,
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
            'message' => $vendorId
                ? "Request {$ticket->reference} sent to " . VendorDirectory::nameOf($vendorId) . ". They'll reply here and you'll be notified."
                : "Request {$ticket->reference} sent. We'll reply here and let you know.",
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
            'issue' => $t->issue,
            'issue_label' => SupportTicket::issueLabel($t->category, $t->issue),
            'desired_outcome_label' => SupportTicket::OUTCOMES[$t->desired_outcome] ?? null,
            'status' => $t->status,
            // Who is handling it: the shop, or BloomCraft support
            'handled_by' => $t->vendor_id ? VendorDirectory::nameOf($t->vendor_id) : 'BloomCraft Support',
            'last_activity_at' => $t->last_activity_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    private function detail(SupportTicket $t): array
    {
        $t->load(['messages', 'order:id,order_number']);
        $shop = $t->vendor_id ? VendorDirectory::nameOf($t->vendor_id) : null;

        return $this->row($t) + [
            'order' => $t->order ? ['id' => $t->order->id, 'order_number' => $t->order->order_number] : null,
            // The shop's team-only notes are never shown
            'messages' => $t->messages->reject(fn ($m) => $m->is_internal)->values()->map(fn ($m) => [
                'id' => $m->id,
                // "Not mine": a reply from support or from the shop
                'from_admin' => $m->from_admin || $m->from_shop,
                // Individual admin and staff names aren't shown to vendors/customers
                'author' => $m->from_admin ? 'BloomCraft Support' : ($m->from_shop ? ($shop ?? 'The shop') : 'You'),
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
