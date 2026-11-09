<?php

namespace App\Http\Controllers;

use App\Models\FundingRequestPayment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvent;
use App\Support\SupplierPaymentTerms;
use App\Support\SupplierPayout;
use App\Models\PurchaseOrderSupplierLink;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * The supplier's side of a purchase order, opened from the private link in
 * its email (no account needed): see the order, confirm it (with how many of
 * each they can supply and when), decline it with a reason, or mark it
 * shipped. Every answer goes into the order's history for the shop.
 */
class SupplierOrderController extends Controller
{
    /** GET /supplier-orders/{token} */
    public function show(string $token): JsonResponse
    {
        $link = $this->link($token);
        if ($link instanceof JsonResponse) {
            return $link;
        }
        $link->update(['last_opened_at' => now()]);

        return response()->json(['data' => $this->present($link)]);
    }

    /** POST /supplier-orders/{token}/confirm  { items: [{id, qty}], delivery_date?, note? } */
    public function confirm(Request $request, string $token): JsonResponse
    {
        $link = $this->link($token);
        if ($link instanceof JsonResponse) {
            return $link;
        }

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.qty' => ['required', 'integer', 'min:0'],
            'delivery_date' => ['nullable', 'date', 'after_or_equal:today'],
            'note' => ['nullable', 'string', 'max:1000'],
        ] + $this->payoutRules($request), [
            'delivery_date.after_or_equal' => 'Choose today or a later date.',
        ] + $this->payoutMessages());

        return DB::transaction(function () use ($link, $data) {
            $order = PurchaseOrder::with('items', 'supplier')->lockForUpdate()->findOrFail($link->purchase_order_id);
            if ($order->status !== 'pending') {
                return $this->alreadyAnswered($order);
            }

            // Where to pay them: needed up front when they're paid before delivery
            $payout = $this->payoutFrom($data, $order);
            if (! $payout && SupplierPaymentTerms::timing($order->supplier?->payment_terms) === SupplierPaymentTerms::BEFORE) {
                return response()->json([
                    'message' => "You're paid before delivery, so add the account the shop should pay into.",
                    'errors' => ['payout_account_number' => ['Add the account to be paid into.']],
                ], 422);
            }
            if ($payout) {
                $order->forceFill($payout)->save();
                $order->supplier?->forceFill($payout)->save();
            }

            // Check every line first; nothing is saved unless all of it is right
            $given = collect($data['items'])->keyBy('id');
            $plan = [];
            foreach ($order->items as $item) {
                $qty = (int) ($given[$item->id]['qty'] ?? $item->quantity);
                if ($qty > $item->quantity) {
                    return response()->json(['message' => "You can confirm up to {$item->quantity} {$item->product_name}.", 'errors' => ["items.{$item->id}" => ["Up to {$item->quantity}."]]], 422);
                }
                $plan[] = [$item, $qty];
            }
            if (collect($plan)->sum(fn ($p) => $p[1]) === 0) {
                return response()->json(['message' => "You confirmed none of the items. If you can't supply this order, decline it instead."], 422);
            }

            $short = [];
            $total = 0;
            foreach ($plan as [$item, $qty]) {
                $item->update(['cancelled_qty' => $item->quantity - $qty]);
                $total += $qty * (float) $item->price;
                if ($qty < $item->quantity) {
                    $short[] = "{$item->product_name}: {$qty} of {$item->quantity}";
                }
            }

            $note = trim((string) ($data['note'] ?? ''));
            $order->quietlyMoveTo(array_filter([
                'status' => 'processing',
                'total_amount' => $total,
                'supplier_confirmed_at' => now(),
                'supplier_note' => $note ?: null,
                'expected_date' => $data['delivery_date'] ?? null,
            ], fn ($v) => $v !== null));

            $summary = collect([
                $short ? 'Can supply less: ' . implode('; ', $short) : 'Can supply everything',
                ! empty($data['delivery_date']) ? 'Delivers on ' . \Carbon\Carbon::parse($data['delivery_date'])->format('M j, Y') : null,
                $note ?: null,
            ])->filter()->implode(' · ');
            PurchaseOrderEvent::recordForSupplier($order, 'supplier_confirmed', 'pending', 'processing', $summary);
            $this->logActivity($order, 'Confirmed a supplier order', "Confirmed supplier order {$order->order_number}: {$summary}");
            $this->tellShop($order, "{$order->supplier?->company_name} confirmed {$order->order_number}", $summary);

            return response()->json(['message' => 'Order confirmed. Thank you!', 'data' => $this->present($link->fresh())]);
        });
    }

    /** POST /supplier-orders/{token}/decline  { reason } */
    public function decline(Request $request, string $token): JsonResponse
    {
        $link = $this->link($token);
        if ($link instanceof JsonResponse) {
            return $link;
        }
        $reason = trim($request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']], [
            'reason.required' => 'Tell the shop why, so they can order elsewhere.',
        ])['reason']);

        return DB::transaction(function () use ($link, $reason) {
            $order = PurchaseOrder::with('supplier')->lockForUpdate()->findOrFail($link->purchase_order_id);
            if ($order->status !== 'pending') {
                return $this->alreadyAnswered($order);
            }

            // Back to a draft: the shop picks another supplier and sends it again
            $order->quietlyMoveTo(['status' => 'draft', 'supplier_declined_at' => now(), 'supplier_note' => $reason, 'submitted_at' => null]);
            PurchaseOrderEvent::recordForSupplier($order, 'supplier_declined', 'pending', 'draft', $reason);
            $this->logActivity($order, "Declined a supplier order", "Can't supply supplier order {$order->order_number}: “{$reason}”");
            $this->tellShop($order, "{$order->supplier?->company_name} can't supply {$order->order_number}", $reason);

            $present = $this->present($link->fresh());
            PurchaseOrderSupplierLink::revokeFor($order);

            return response()->json(['message' => 'The shop has been told.', 'data' => $present]);
        });
    }

    /** POST /supplier-orders/{token}/shipped  { note? } */
    public function shipped(Request $request, string $token): JsonResponse
    {
        $link = $this->link($token);
        if ($link instanceof JsonResponse) {
            return $link;
        }
        $note = trim((string) ($request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? ''));

        return DB::transaction(function () use ($link, $note) {
            $order = PurchaseOrder::with('supplier')->lockForUpdate()->findOrFail($link->purchase_order_id);
            if ($order->status !== 'processing') {
                return $order->status === 'pending'
                    ? response()->json(['message' => 'Confirm the order first.'], 409)
                    : $this->alreadyAnswered($order);
            }

            $order->quietlyMoveTo(['status' => 'shipped', 'supplier_shipped_at' => now()]);
            PurchaseOrderEvent::recordForSupplier($order, 'supplier_shipped', 'processing', 'shipped', $note ?: null);
            $this->logActivity($order, 'Sent a supplier order on its way', "Sent supplier order {$order->order_number} on its way" . ($note ? ": {$note}" : ''));
            $this->tellShop($order, "{$order->order_number} is on its way", $note ?: "{$order->supplier?->company_name} has sent the order.");

            return response()->json(['message' => 'Marked as shipped.', 'data' => $this->present($link->fresh())]);
        });
    }

    /** The usable link for a token, or the answer explaining why it can't be used */
    private function link(string $token): PurchaseOrderSupplierLink|JsonResponse
    {
        $link = PurchaseOrderSupplierLink::forToken($token);
        if (! $link || ! $link->order) {
            return response()->json(['code' => 'link_invalid', 'message' => "This link isn't valid. Use the link in the most recent email about this order."], 404);
        }
        if ($link->revoked_at) {
            return response()->json(['code' => 'link_replaced', 'message' => 'This link was replaced by a newer email, or the order was withdrawn. Use the link in the most recent email from the shop.'], 410);
        }
        if (! $link->expires_at->isFuture()) {
            return response()->json(['code' => 'link_expired', 'message' => 'This link expired on ' . $link->expires_at->format('F j, Y') . '. Ask the shop to send the order again for a new link.'], 410);
        }

        return $link;
    }

    private function alreadyAnswered(PurchaseOrder $order): JsonResponse
    {
        return response()->json([
            'code' => 'already_answered',
            'message' => 'This order was already answered, so nothing changed. The page now shows where it stands.',
        ], 409);
    }

    /** POST /supplier-orders/{token}/payout: change the account to be paid into, until it's been paid */
    public function payout(Request $request, string $token): JsonResponse
    {
        $link = $this->link($token);
        if ($link instanceof JsonResponse) {
            return $link;
        }
        $rules = $this->payoutRules($request);
        unset($rules['use_saved']);
        // Bank or e-wallet is worked out from the provider when not given
        foreach (['payout_provider', 'payout_account_name', 'payout_account_number'] as $f) {
            $rules[$f][0] = 'required';
        }
        $data = $request->validate($rules, $this->payoutMessages());

        return DB::transaction(function () use ($link, $data) {
            $order = PurchaseOrder::with('supplier')->lockForUpdate()->findOrFail($link->purchase_order_id);
            if (! in_array($order->status, ['pending', 'processing', 'shipped', 'received', 'partially_received', 'completed'], true)) {
                return response()->json(['message' => 'This order is closed, so the account can\'t be changed here.'], 409);
            }
            if ($this->paymentsFor($order)->where('kind', FundingRequestPayment::KIND_PAYMENT)->isNotEmpty()) {
                return response()->json(['message' => 'The shop already paid into the account you gave. Contact them to change it.'], 409);
            }
            $payout = $this->payoutFrom($data, $order);
            $order->forceFill($payout)->save();
            $order->supplier?->forceFill($payout)->save();
            PurchaseOrderEvent::recordForSupplier($order, 'supplier_payout', null, null, "Payment account: {$payout['payout_provider']} " . SupplierPayout::mask($payout['payout_account_number']));
            $this->tellShop($order, "{$order->supplier?->company_name} changed their payment account", "For {$order->order_number}: {$payout['payout_provider']} " . SupplierPayout::mask($payout['payout_account_number']));

            return response()->json(['message' => 'Payment account saved.', 'data' => $this->present($link->fresh())]);
        });
    }

    private function payoutRules(Request $request): array
    {
        $useSaved = $request->boolean('use_saved');

        return [
            'use_saved' => ['nullable', 'boolean'],
            'payout_method' => ['nullable', 'in:bank,ewallet'],
            'payout_provider' => [$useSaved ? 'nullable' : 'required_with:payout_account_number', 'nullable', 'string', \Illuminate\Validation\Rule::in(\App\Support\PayMongoChannels::all())],
            'payout_account_name' => [$useSaved ? 'nullable' : 'required_with:payout_account_number', 'nullable', 'string', 'max:120'],
            // Digits, with the spaces or dashes people type
            'payout_account_number' => [$useSaved ? 'nullable' : 'required_with:payout_provider', 'nullable', 'string', 'max:40', 'regex:/^[0-9 \-]{6,40}$/'],
        ];
    }

    private function payoutMessages(): array
    {
        return [
            'payout_provider.required' => 'Choose the bank or e-wallet.',
            'payout_provider.required_with' => 'Choose the bank or e-wallet.',
            'payout_account_name.required' => 'Enter the name on the account.',
            'payout_account_name.required_with' => 'Enter the name on the account.',
            'payout_account_number.required' => 'Enter the account or mobile number.',
            'payout_account_number.required_with' => 'Enter the account or mobile number.',
            'payout_account_number.regex' => 'Use numbers only (6 or more), spaces and dashes are fine.',
        ];
    }

    /** The account from the form, or the one on file when they chose to keep it */
    private function payoutFrom(array $data, PurchaseOrder $order): ?array
    {
        if (! empty($data['use_saved']) && SupplierPayout::has($order->supplier)) {
            return collect(SupplierPayout::FIELDS)->mapWithKeys(fn ($f) => [$f => $order->supplier->{$f}])->all();
        }
        if (empty($data['payout_account_number']) || empty($data['payout_provider'])) {
            return null;
        }

        return [
            'payout_method' => $data['payout_method'] ?? \App\Support\PayMongoChannels::kindOf($data['payout_provider']),
            'payout_provider' => trim($data['payout_provider']),
            'payout_account_name' => trim((string) ($data['payout_account_name'] ?? '')),
            'payout_account_number' => trim(preg_replace('/\s+/', ' ', $data['payout_account_number'])),
        ];
    }

    /** Money the shop sent (or got back) for this order, through a funding request */
    private function paymentsFor(PurchaseOrder $order)
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('funding_request_payments')) {
            return collect();
        }

        return FundingRequestPayment::where('purchase_order_id', $order->id)->orderBy('id')->get();
    }

    /** What the supplier sees: the order, never anything else from the shop */
    private function present(PurchaseOrderSupplierLink $link): array
    {
        $order = $link->order()->with('items', 'supplier')->first();
        $owner = User::find($order->owner_id);
        $terms = $order->supplier?->payment_terms;
        $payments = $this->paymentsFor($order);

        return [
            'payment' => [
                'terms' => $terms,
                'timing' => SupplierPaymentTerms::timing($terms),
                'due_days' => SupplierPaymentTerms::dueDays($terms),
                // The account they gave for this order, or the one they gave before
                'account' => SupplierPayout::masked($order) ?? SupplierPayout::masked($order->supplier),
                'account_for_order' => SupplierPayout::has($order),
                'can_change_account' => $payments->where('kind', FundingRequestPayment::KIND_PAYMENT)->isEmpty(),
                'paid' => round((float) $payments->where('kind', FundingRequestPayment::KIND_PAYMENT)->sum('amount'), 2),
                'payments' => $payments->whereIn('kind', [FundingRequestPayment::KIND_PAYMENT, FundingRequestPayment::KIND_REFUND])->values()->map(fn ($p) => [
                    'kind' => $p->kind,
                    'amount' => (float) $p->amount,
                    'paid_on' => $p->paid_on?->toDateString(),
                    'channel' => $p->payment_channel,
                    'reference' => $p->reference,
                    'receipt_number' => $p->receipt_number,
                ]),
            ],
            'shop_name' => $owner?->vendor?->store_name ?: ($owner?->name ?: 'The shop'),
            'supplier_name' => $order->supplier?->company_name,
            'contact_person' => $order->supplier?->contact_person,
            'order_number' => $order->order_number,
            'order_date' => $order->order_date?->toDateString(),
            'expected_date' => $order->expected_date?->toDateString(),
            'notes' => $order->notes,
            'stage' => match (true) {
                $order->supplier_declined_at && $order->status === 'draft' => 'declined',
                $order->status === 'pending' => 'awaiting',
                $order->status === 'processing' => 'confirmed',
                $order->status === 'shipped' => 'shipped',
                in_array($order->status, ['received', 'partially_received', 'completed'], true) => 'received',
                default => 'closed',
            },
            'confirmed_at' => $order->supplier_confirmed_at?->toIso8601String(),
            'shipped_at' => $order->supplier_shipped_at?->toIso8601String(),
            'supplier_note' => $order->supplier_note,
            'expires_at' => $link->expires_at->toIso8601String(),
            'total' => (float) $order->total_amount,
            'items' => $order->items->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->product_name,
                'quantity' => (int) $i->quantity,
                'confirmed' => (int) $i->quantity - (int) $i->cancelled_qty,
                'unit' => $i->unit,
                'price' => (float) $i->price,
            ])->values(),
        ];
    }

    /** The supplier's answer in the shop's activity log, next to staff and owner changes */
    private function logActivity(PurchaseOrder $order, string $action, string $details): void
    {
        try {
            $supplier = $order->supplier?->company_name ?: 'Supplier';
            \App\Models\StaffActivityLog::create([
                'owner_id' => $order->owner_id,
                'employee_id' => null,
                'actor_name' => \Illuminate\Support\Str::limit("{$supplier} (supplier)", 117),
                'action' => \Illuminate\Support\Str::limit($action, 157),
                'area' => 'Supplier orders',
                ...(\Illuminate\Support\Facades\Schema::hasColumn('staff_activity_logs', 'details') ? [
                    'details' => \Illuminate\Support\Str::limit($details, 497),
                    'subject' => \Illuminate\Support\Str::limit($supplier, 147),
                ] : []),
                'method' => 'POST',
                'status' => 200,
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // The answer itself is saved; the log line must never undo it
            Log::warning('Supplier activity log failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }

    private function tellShop(PurchaseOrder $order, string $title, ?string $body): void
    {
        try {
            app(NotificationService::class)->notify([$order->owner_id], 'purchase_order', $title, $body);
        } catch (\Throwable $e) {
            Log::warning('Supplier answer notification failed', ['order_id' => $order->id, 'error' => $e->getMessage()]);
        }
    }
}
