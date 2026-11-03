<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderEvent;
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
        ], [
            'delivery_date.after_or_equal' => 'Choose today or a later date.',
        ]);

        return DB::transaction(function () use ($link, $data) {
            $order = PurchaseOrder::with('items', 'supplier')->lockForUpdate()->findOrFail($link->purchase_order_id);
            if ($order->status !== 'pending') {
                return $this->alreadyAnswered($order);
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

    /** What the supplier sees: the order, never anything else from the shop */
    private function present(PurchaseOrderSupplierLink $link): array
    {
        $order = $link->order()->with('items', 'supplier')->first();
        $owner = User::find($order->owner_id);

        return [
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
