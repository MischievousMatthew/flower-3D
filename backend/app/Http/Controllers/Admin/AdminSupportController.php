<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\ProductReport;
use App\Models\SupportMessage;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\VendorApplication;
use App\Models\VendorSubscription;
use App\Models\VendorSubscriptionCheckout;
use App\Services\NotificationService;
use App\Subscriptions\SubscriptionPlans;
use App\Support\PaymentLedger;
use App\Support\VendorDirectory;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Admin notifications & support: announcements to vendors/customers, the
 * support inbox, alerts that need attention, and an activity feed of
 * order and subscription events.
 */
class AdminSupportController extends Controller
{
    public function __construct(private readonly NotificationService $notifications)
    {
    }

    /**
     * GET /api/admin/support/summary
     */
    public function summary(): JsonResponse
    {
        $alerts = $this->buildAlerts();

        return response()->json([
            'success' => true,
            'data' => [
                'open_tickets' => SupportTicket::where('status', 'open')->count(),
                'waiting_tickets' => SupportTicket::where('status', 'waiting')->count(),
                'high_priority' => SupportTicket::where('status', 'open')->where('priority', 'high')->count(),
                'critical_alerts' => collect($alerts)->where('severity', 'critical')->count(),
                'alerts' => count($alerts),
                'open_complaints' => OrderRequest::where('status', 'pending')->count() + ProductReport::where('status', 'pending')->count(),
                'failed_payments_7d' => $this->failedPayments(now()->subDays(7))->count(),
            ],
        ]);
    }

    // ── Support inbox ────────────────────────────────────────────────────

    /** GET /api/admin/support/tickets */
    public function tickets(Request $request): JsonResponse
    {
        $query = SupportTicket::query()->with('requester:id,name,surname,email,role')
            ->withCount('messages');

        match ($request->input('status')) {
            'open', 'waiting', 'resolved', 'closed' => $query->where('status', $request->input('status')),
            'active' => $query->whereIn('status', ['open', 'waiting']),
            default => null,
        };
        if (in_array($request->input('role'), ['vendor', 'customer'], true)) {
            $query->where('requester_role', $request->input('role'));
        }
        if (array_key_exists((string) $request->input('category'), SupportTicket::CATEGORIES)) {
            $query->where('category', $request->input('category'));
        }
        if ($search = trim((string) $request->input('search'))) {
            $like = '%' . mb_strtolower($search) . '%';
            $people = User::query()->whereRaw('lower(name) like ?', [$like])->orWhereRaw('lower(email) like ?', [$like])->pluck('id')
                ->merge(VendorDirectory::idsMatching($search));
            $query->where(fn ($q) => $q->whereRaw('lower(subject) like ?', [$like])
                ->orWhereRaw('lower(reference) like ?', [$like])
                ->orWhereIn('requester_id', $people));
        }

        // Open high-priority first, then whatever moved most recently
        $tickets = $query->orderByRaw("case when status = 'open' and priority = 'high' then 0 when status = 'open' then 1 when status = 'waiting' then 2 else 3 end")
            ->orderByDesc('last_activity_at')->limit(100)->get();
        $stores = VendorDirectory::details($tickets->where('requester_role', 'vendor')->pluck('requester_id'));
        $lastAdminReply = SupportMessage::query()->whereIn('ticket_id', $tickets->pluck('id'))->where('from_admin', true)
            ->selectRaw('ticket_id, max(created_at) as at')->groupBy('ticket_id')->pluck('at', 'ticket_id');

        return response()->json([
            'success' => true,
            'data' => $tickets->map(fn ($t) => $this->ticketRow($t, $stores, $lastAdminReply)),
            'categories' => SupportTicket::CATEGORIES,
        ]);
    }

    /** GET /api/admin/support/tickets/{id} */
    public function ticket(int $id): JsonResponse
    {
        $ticket = SupportTicket::query()->with(['requester:id,name,surname,email,role,is_suspended', 'messages.author:id,name,surname', 'order:id,order_number,status,total_amount'])->find($id);
        if (!$ticket) {
            return $this->notFound();
        }

        $stores = VendorDirectory::details($ticket->requester_role === 'vendor' ? [$ticket->requester_id] : []);
        $history = SupportTicket::query()->where('requester_id', $ticket->requester_id)->whereKeyNot($ticket->id)->latest()->limit(5)
            ->get(['id', 'reference', 'subject', 'status', 'created_at']);

        return response()->json([
            'success' => true,
            'data' => $this->ticketRow($ticket, $stores, collect()) + [
                'requester_suspended' => (bool) $ticket->requester?->is_suspended,
                'order' => $ticket->order ? $ticket->order->only(['id', 'order_number', 'status']) + ['total_amount' => (float) $ticket->order->total_amount] : null,
                'messages' => $ticket->messages->map(fn ($m) => [
                    'id' => $m->id,
                    'from_admin' => $m->from_admin,
                    'author' => $m->from_admin ? (PaymentLedger::personName($m->author) ?? 'Admin') : ($this->requesterName($ticket, $stores)),
                    'body' => $m->body,
                    'created_at' => $m->created_at?->toIso8601String(),
                ]),
                'other_requests' => $history,
            ],
        ]);
    }

    /** POST /api/admin/support/tickets/{id}/reply  { message, status? } */
    public function reply(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);
        if (!$ticket) {
            return $this->notFound();
        }
        $data = $request->validate([
            'message' => 'required|string|min:2|max:5000',
            'status' => ['nullable', Rule::in(['waiting', 'resolved', 'open'])],
        ]);

        $status = $data['status'] ?? 'waiting';
        DB::transaction(function () use ($ticket, $data, $status, $request) {
            SupportMessage::create(['ticket_id' => $ticket->id, 'author_id' => $request->user()->id, 'from_admin' => true, 'body' => trim($data['message'])]);
            $ticket->update([
                'status' => $status,
                'last_activity_at' => now(),
                'resolved_at' => $status === 'resolved' ? now() : null,
            ]);
            $this->notifications->notify(
                [$ticket->requester_id],
                'support_reply',
                "BloomCraft Support replied to {$ticket->reference}",
                mb_strimwidth(trim($data['message']), 0, 160, '…'),
                $this->requesterLink($ticket),
            );
        });

        return response()->json(['success' => true, 'message' => 'Reply sent. They have been notified.']);
    }

    /** POST /api/admin/support/tickets/{id}/status  { status?, priority? } */
    public function setStatus(Request $request, int $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);
        if (!$ticket) {
            return $this->notFound();
        }
        $data = $request->validate([
            'status' => ['nullable', Rule::in(SupportTicket::STATUSES)],
            'priority' => ['nullable', Rule::in(SupportTicket::PRIORITIES)],
        ]);

        $changes = array_filter($data);
        if (isset($changes['status'])) {
            $changes['resolved_at'] = in_array($changes['status'], ['resolved', 'closed'], true) ? now() : null;
            $changes['last_activity_at'] = now();
        }
        $ticket->update($changes);

        if (isset($data['status']) && in_array($data['status'], ['resolved', 'closed'], true)) {
            $this->notifications->notify([$ticket->requester_id], 'support_status',
                "{$ticket->reference} was marked " . ($data['status'] === 'closed' ? 'closed' : 'solved'),
                'Reply on the request if you still need help.', $this->requesterLink($ticket));
        }

        return response()->json(['success' => true, 'message' => 'Updated.']);
    }

    // ── Announcements ────────────────────────────────────────────────────

    /** GET /api/admin/broadcasts */
    public function broadcasts(): JsonResponse
    {
        $items = Broadcast::query()->with('sender:id,name,surname')->latest()->limit(50)->get();
        $reads = DB::table('user_notifications')->whereIn('broadcast_id', $items->pluck('id'))->whereNotNull('read_at')
            ->selectRaw('broadcast_id, count(*) as total')->groupBy('broadcast_id')->pluck('total', 'broadcast_id');

        return response()->json([
            'success' => true,
            'data' => $items->map(fn ($b) => [
                'id' => $b->id,
                'audience' => $b->audience,
                'title' => $b->title,
                'body' => $b->body,
                'link_url' => $b->link_url,
                'priority' => $b->priority,
                'recipients' => $b->recipients,
                'read' => (int) ($reads[$b->id] ?? 0),
                'sent_by' => PaymentLedger::personName($b->sender) ?? 'Admin',
                'created_at' => $b->created_at?->toIso8601String(),
            ]),
            'audience_sizes' => [
                'vendors' => $this->audience('vendors')->count(),
                'customers' => $this->audience('customers')->count(),
                'all' => $this->audience('all')->count(),
            ],
        ]);
    }

    /** POST /api/admin/broadcasts */
    public function sendBroadcast(Request $request): JsonResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(Broadcast::AUDIENCES)],
            'title' => 'required|string|min:4|max:140',
            'body' => 'required|string|min:10|max:2000',
            'link_url' => ['nullable', 'string', 'max:500', 'regex:#^(/|https?://)#i'],
            'priority' => ['nullable', Rule::in(['normal', 'important'])],
        ], ['link_url.regex' => 'Use a page path starting with / or a full https:// address.']);

        $recipients = $this->audience($data['audience'])->pluck('id');
        if ($recipients->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Nobody is in that audience yet.'], 422);
        }

        $broadcast = DB::transaction(function () use ($data, $recipients, $request) {
            $broadcast = Broadcast::create([
                'audience' => $data['audience'],
                'title' => trim($data['title']),
                'body' => trim($data['body']),
                'link_url' => $data['link_url'] ?? null,
                'priority' => $data['priority'] ?? 'normal',
                'sent_by' => $request->user()->id,
            ]);
            $sent = $this->notifications->notify($recipients, 'announcement', $broadcast->title, $broadcast->body,
                $broadcast->link_url, $broadcast->priority === 'important', $broadcast->id);
            $broadcast->update(['recipients' => $sent]);

            return $broadcast;
        });

        return response()->json(['success' => true, 'message' => "Sent to {$broadcast->recipients} " . ($broadcast->recipients === 1 ? 'person' : 'people') . '.'], 201);
    }

    /** DELETE /api/admin/broadcasts/{id}  (removes it from everyone's inbox) */
    public function retractBroadcast(int $id): JsonResponse
    {
        $broadcast = Broadcast::find($id);
        if (!$broadcast) {
            return $this->notFound();
        }
        $broadcast->delete();

        return response()->json(['success' => true, 'message' => "Withdrawn. It's no longer in anyone's notifications."]);
    }

    // ── Alerts ───────────────────────────────────────────────────────────

    /** GET /api/admin/support/alerts */
    public function alerts(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->buildAlerts(),
            'failed_payments' => $this->failedPaymentRows(),
            'system_errors' => $this->recentErrors(),
        ]);
    }

    // ── Activity feed ────────────────────────────────────────────────────

    /** GET /api/admin/support/events?type=all|orders|subscriptions&days=7 */
    public function events(Request $request): JsonResponse
    {
        $days = in_array((int) $request->input('days'), [1, 7, 30, 90], true) ? (int) $request->input('days') : 7;
        $type = in_array($request->input('type'), ['orders', 'subscriptions'], true) ? $request->input('type') : 'all';
        $since = now()->subDays($days);
        $events = collect();

        if ($type !== 'subscriptions') {
            $orders = Order::query()->where(fn ($q) => $q->where('created_at', '>=', $since)->orWhere('updated_at', '>=', $since))
                ->get(['id', 'order_number', 'user_id', 'vendor_id', 'status', 'payment_status', 'payment_method', 'total_amount', 'created_at', 'paid_at', 'cancelled_at', 'delivered_at', 'updated_at']);
            $vendors = VendorDirectory::details($orders->pluck('vendor_id'));
            $customers = User::query()->whereIn('id', $orders->pluck('user_id'))->get(['id', 'name', 'surname'])->keyBy('id');

            foreach ($orders as $o) {
                $who = PaymentLedger::personName($customers[$o->user_id] ?? null) ?? 'A customer';
                $store = $vendors[$o->vendor_id]['store_name'] ?? 'a store';
                $amount = '₱' . number_format((float) $o->total_amount, 2);
                $base = ['subject' => $o->order_number, 'order_id' => $o->id, 'link' => ['path' => '/admin/orders', 'query' => ['order' => $o->id]]];

                $push = fn ($at, $kind, $tone, $title) => $at && $at->gte($since) && $events->push($base + ['at' => $at, 'kind' => $kind, 'tone' => $tone, 'title' => $title]);
                $push($o->created_at, 'order_placed', 'info', "{$who} ordered from {$store} · {$amount}");
                $push($o->paid_at, 'order_paid', 'good', "Payment received for {$o->order_number} · {$amount}");
                $push($o->delivered_at, 'order_delivered', 'good', "{$o->order_number} was delivered");
                $push($o->cancelled_at ?? ($o->status === 'cancelled' ? $o->updated_at : null), 'order_cancelled', 'muted', "{$o->order_number} was cancelled");
                if ($o->payment_status === 'failed' || $o->status === 'failed') {
                    $push($o->updated_at, 'payment_failed', 'bad', "Payment failed for {$o->order_number} · {$amount}");
                }
                if (in_array($o->status, ['refunded', 'returned'], true)) {
                    $push($o->updated_at, 'order_refunded', 'warn', "{$o->order_number} was " . $o->status);
                }
            }

            OrderRequest::query()->with('order:id,order_number')->where('created_at', '>=', $since)->get()
                ->each(fn ($r) => $events->push([
                    'at' => $r->created_at, 'kind' => 'return_requested', 'tone' => 'warn',
                    'title' => ucfirst($r->type) . ' requested for ' . ($r->order?->order_number ?? 'an order'),
                    'subject' => $r->order?->order_number, 'order_id' => $r->order_id,
                    'link' => ['path' => '/admin/orders', 'query' => ['order' => $r->order_id]],
                ]));
        }

        if ($type !== 'orders') {
            $checkouts = VendorSubscriptionCheckout::query()->where('updated_at', '>=', $since)->get();
            $trials = DB::table('vendor_subscription_trials')->where('started_at', '>=', $since)->get();
            $ended = VendorSubscription::query()->where(fn ($q) => $q->where('expired_at', '>=', $since)->orWhere('cancelled_at', '>=', $since))->get();
            $vendors = VendorDirectory::details($checkouts->pluck('vendor_id')->merge($trials->pluck('vendor_id'))->merge($ended->pluck('vendor_id')));
            $plan = fn ($key) => SubscriptionPlans::exists($key) ? SubscriptionPlans::get($key)['name'] : ucfirst((string) $key);
            $store = fn ($id) => $vendors[$id]['store_name'] ?? 'A vendor';
            $link = ['path' => '/admin/subscription-revenue'];

            foreach ($checkouts as $c) {
                if ($c->status === 'paid' && $c->paid_at?->gte($since)) {
                    $events->push(['at' => $c->paid_at, 'kind' => 'subscription_paid', 'tone' => 'good', 'title' => "{$store($c->vendor_id)} paid for {$plan($c->plan_key)} · ₱" . number_format((float) $c->amount, 2), 'subject' => $c->reference_number, 'link' => $link]);
                } elseif ($c->status === 'failed') {
                    $events->push(['at' => $c->updated_at, 'kind' => 'subscription_failed', 'tone' => 'bad', 'title' => "{$store($c->vendor_id)}'s {$plan($c->plan_key)} payment failed", 'subject' => $c->reference_number, 'link' => $link]);
                }
            }
            foreach ($trials as $t) {
                $events->push(['at' => CarbonImmutable::parse($t->started_at), 'kind' => 'trial_started', 'tone' => 'info', 'title' => "{$store($t->vendor_id)} started a {$plan($t->plan_key)} free trial", 'subject' => null, 'link' => $link]);
            }
            foreach ($ended as $s) {
                $at = $s->cancelled_at ?? $s->expired_at;
                $events->push(['at' => $at, 'kind' => 'subscription_ended', 'tone' => 'warn', 'title' => "{$store($s->vendor_id)}'s {$plan($s->plan_key)} plan " . ($s->cancelled_at ? 'was cancelled' : 'expired'), 'subject' => null, 'link' => $link]);
            }
        }

        $sorted = $events->filter(fn ($e) => $e['at'])->sortByDesc(fn ($e) => $e['at']->getTimestamp())->take(200)->values()
            ->map(fn ($e) => [...$e, 'at' => $e['at']->toIso8601String()]);

        return response()->json(['success' => true, 'data' => $sorted, 'counts' => $sorted->countBy('kind')]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    /** Things that need an admin's attention, most serious first */
    private function buildAlerts(): array
    {
        $alerts = [];
        $add = function (string $key, string $severity, string $title, string $detail, int $count, array $link) use (&$alerts) {
            if ($count > 0) {
                $alerts[] = compact('key', 'severity', 'title', 'detail', 'count', 'link');
            }
        };

        $failed = $this->failedPayments(now()->subDays(7))->count();
        $add('failed_payments', 'critical', 'Failed payments this week', 'Customer or subscription payments that didn\'t go through.', $failed, ['path' => '/admin/finance', 'query' => ['tab' => 'transactions']]);

        $issues = collect(app(AdminOrderController::class)->summary()->getData(true)['data']['issue_breakdown'])->keyBy('key');
        $add('completed_unpaid', 'critical', 'Orders completed without payment', 'Online orders marked delivered but never confirmed paid.', (int) ($issues['completed_unpaid']['count'] ?? 0), ['path' => '/admin/orders', 'query' => ['tab' => 'issues']]);
        $add('paid_not_refunded', 'critical', 'Paid orders cancelled without a refund', 'Customers paid, the order was cancelled or returned, and no refund was recorded.', (int) ($issues['paid_not_refunded']['count'] ?? 0), ['path' => '/admin/orders', 'query' => ['tab' => 'issues']]);

        $highTickets = SupportTicket::where('status', 'open')->where('priority', 'high')->count();
        $add('urgent_tickets', 'warning', 'Urgent support requests', 'High-priority requests (payments, account) waiting for a reply.', $highTickets, ['path' => '/admin/support', 'query' => ['tab' => 'inbox']]);
        $unanswered = SupportTicket::where('status', 'open')->where('last_activity_at', '<', now()->subDay())->count();
        $add('unanswered_tickets', 'warning', 'Requests waiting over a day', 'Open support requests with no reply in 24 hours.', $unanswered, ['path' => '/admin/support', 'query' => ['tab' => 'inbox']]);

        $add('stale_orders', 'warning', 'Orders stuck in pending', 'Pending for more than 3 days.', (int) ($issues['stale_pending']['count'] ?? 0), ['path' => '/admin/orders', 'query' => ['tab' => 'issues']]);
        $add('disputes', 'warning', 'Open return & refund requests', 'Waiting on the vendor to decide.', OrderRequest::where('status', 'pending')->count(), ['path' => '/admin/orders', 'query' => ['tab' => 'disputes']]);
        $add('reports', 'warning', 'Product reports to review', 'Customers flagged listings.', ProductReport::where('status', 'pending')->count(), ['path' => '/admin/reports']);

        $suspendedWithOrders = Order::query()->whereIn('vendor_id', User::where('is_suspended', true)->select('id'))->whereIn('status', ['pending', 'processing', 'out_for_delivery'])->count();
        $add('suspended_orders', 'warning', 'Open orders at suspended stores', 'These customers are waiting on stores that can\'t sign in.', $suspendedWithOrders, ['path' => '/admin/vendor-requests', 'query' => ['tab' => 'suspended']]);

        $pastDue = VendorSubscription::where('status', SubscriptionStatus::PastDue)->count();
        $add('past_due', 'warning', 'Subscriptions past due', 'Vendors whose plan payment is overdue.', $pastDue, ['path' => '/admin/subscription-revenue']);

        $now = now();
        $subs = VendorSubscription::query()->get();
        $trialsEnding = $subs->filter(fn ($s) => $s->isTrialActive($now) && $s->trial_ends_at->lte($now->copy()->addDays(3)))->count();
        $add('trials_ending', 'info', 'Free trials ending within 3 days', 'A good moment to nudge them to subscribe.', $trialsEnding, ['path' => '/admin/subscription-revenue']);
        $renewals = $subs->filter(fn ($s) => $s->status === SubscriptionStatus::Active && $s->subscription_ends_at && $s->subscription_ends_at->between($now, $now->copy()->addDays(7)))->count();
        $add('renewals', 'info', 'Plans renewing within 7 days', 'Paid subscriptions coming up for renewal.', $renewals, ['path' => '/admin/subscription-revenue']);

        $add('applications', 'info', 'Vendor applications to review', 'New stores waiting to be approved.', VendorApplication::where('status', 'pending')->count(), ['path' => '/admin/vendor-requests', 'query' => ['tab' => 'pending']]);

        $errors = count($this->recentErrors());
        $add('system_errors', $errors >= 10 ? 'critical' : 'warning', 'Server errors in the last 24 hours', 'Errors the backend logged. See the list below.', $errors, ['path' => '/admin/support', 'query' => ['tab' => 'alerts']]);

        $rank = ['critical' => 0, 'warning' => 1, 'info' => 2];
        usort($alerts, fn ($a, $b) => [$rank[$a['severity']], -$a['count']] <=> [$rank[$b['severity']], -$b['count']]);

        return $alerts;
    }

    private function failedPayments(\DateTimeInterface $since): Collection
    {
        return PaymentLedger::query()->where('state', 'failed')->where('occurred_at', '>=', $since)->get();
    }

    private function failedPaymentRows(): array
    {
        return PaymentLedger::format(
            PaymentLedger::query()->where('state', 'failed')->where('occurred_at', '>=', now()->subDays(30))->orderByDesc('occurred_at')->limit(20)->get()
        );
    }

    /**
     * The last day's ERROR entries from the Laravel log (first line only,
     * shortened), newest first. Reads just the end of the file.
     */
    private function recentErrors(): array
    {
        $path = storage_path('logs/laravel.log');
        if (!is_readable($path)) {
            return [];
        }

        $size = filesize($path);
        $handle = fopen($path, 'r');
        fseek($handle, max(0, $size - 512 * 1024));
        $tail = stream_get_contents($handle);
        fclose($handle);

        $since = now()->subDay();
        preg_match_all('/^\[(\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2})[^\]]*\]\s+\w+\.(ERROR|CRITICAL|ALERT|EMERGENCY):\s*(.+)$/m', $tail, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn ($m) => ['at' => CarbonImmutable::parse($m[1]), 'level' => strtolower($m[2]), 'message' => mb_strimwidth(trim(preg_replace('/\s+\{.*$/', '', $m[3])), 0, 180, '…')])
            ->filter(fn ($e) => $e['at']->gte($since))
            ->sortByDesc(fn ($e) => $e['at']->getTimestamp())
            ->take(25)
            ->map(fn ($e) => [...$e, 'at' => $e['at']->toIso8601String()])
            ->values()->all();
    }

    private function audience(string $audience)
    {
        $roles = match ($audience) {
            'vendors' => [User::ROLE_VENDOR],
            'customers' => [User::ROLE_CUSTOMER],
            default => [User::ROLE_VENDOR, User::ROLE_CUSTOMER],
        };

        // Suspended accounts can't sign in, so they aren't sent anything
        return User::query()->whereIn('role', $roles)->where(fn ($q) => $q->where('is_suspended', false)->orWhereNull('is_suspended'));
    }

    private function ticketRow(SupportTicket $t, array $stores, Collection $lastAdminReply): array
    {
        $lastReply = $lastAdminReply[$t->id] ?? null;

        return [
            'id' => $t->id,
            'reference' => $t->reference,
            'subject' => $t->subject,
            'category' => $t->category,
            'category_label' => SupportTicket::CATEGORIES[$t->category] ?? $t->category,
            'priority' => $t->priority,
            'status' => $t->status,
            'requester' => [
                'id' => $t->requester_id,
                'role' => $t->requester_role,
                'name' => $this->requesterName($t, $stores),
                'person' => PaymentLedger::personName($t->requester),
                'logo_url' => $stores[$t->requester_id]['logo_url'] ?? null,
            ],
            'messages' => $t->messages_count ?? null,
            'awaiting_reply_since' => $t->status === 'open' ? $t->last_activity_at?->toIso8601String() : null,
            'last_admin_reply_at' => $lastReply ? CarbonImmutable::parse($lastReply)->toIso8601String() : null,
            'last_activity_at' => $t->last_activity_at?->toIso8601String(),
            'created_at' => $t->created_at?->toIso8601String(),
        ];
    }

    private function requesterName(SupportTicket $t, array $stores): string
    {
        return $t->requester_role === 'vendor'
            ? ($stores[$t->requester_id]['store_name'] ?? 'Vendor')
            : (PaymentLedger::personName($t->requester) ?? 'Customer');
    }

    private function requesterLink(SupportTicket $t): string
    {
        return ($t->requester_role === 'vendor' ? '/vendor/support' : '/customer/support') . '?ticket=' . $t->id;
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['success' => false, 'message' => 'Not found.'], 404);
    }
}
