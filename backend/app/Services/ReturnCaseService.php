<?php

namespace App\Services;

use App\Helpers\CloudinaryHelper;
use App\Models\Employee;
use App\Models\Order;
use App\Models\OrderRequest;
use App\Models\OrderRequestMessage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Return/refund cases the customer and the vendor settle between them.
 *
 * 1. The customer opens a case: refund only, or return the item; asking for a
 *    full refund, a partial refund or a replacement.
 * 2. Either side makes offers; the other accepts or counters. The vendor can
 *    reject, the customer can withdraw. Both can message.
 * 3. Once agreed: the item comes back (returns), the vendor sends the refund
 *    or replacement, and the customer confirms it arrived.
 * 4. A confirmed refund is recorded on the vendor's books for the agreed amount.
 *
 * Every method throws RuntimeException with a message safe to show.
 */
class ReturnCaseService
{
    public const REASONS = [
        'damaged' => 'Arrived damaged or wilted',
        'wrong_item' => 'Wrong item or arrangement',
        'missing_item' => 'Missing items',
        'not_as_described' => 'Not as described or pictured',
        'late' => 'Arrived late',
        'quality' => 'Poor quality',
        'other' => 'Something else',
    ];

    public const REFUND_METHODS = ['gcash' => 'GCash', 'maya' => 'Maya', 'bank_transfer' => 'Bank transfer', 'cash' => 'Cash', 'other' => 'Other'];

    public function __construct(private NotificationService $notifications) {}

    // ── Opening ──────────────────────────────────────────────────────────

    public function open(Order $order, User $customer, array $data, ?UploadedFile $media = null): OrderRequest
    {
        $order->loadMissing('delivery');
        $delivered = $order->status === 'completed' || $order->delivery?->status === 'completed';
        if (!$delivered) {
            throw new RuntimeException('You can ask for a return or refund once the order has been delivered.');
        }
        if (OrderRequest::where('order_id', $order->id)->whereIn('status', OrderRequest::OPEN_STATUSES)->exists()) {
            throw new RuntimeException('There is already an open return/refund for this order.');
        }
        if (OrderRequest::where('order_id', $order->id)->where('status', 'completed')->exists()) {
            throw new RuntimeException('A return/refund for this order has already been settled.');
        }

        $resolution = $data['resolution'];
        $this->assertResolution($order, $resolution, $data['amount'] ?? null);
        $amount = $this->amountFor($order, $resolution, $data['amount'] ?? null);
        [$mediaPath, $mediaType] = $this->storeMedia($media);

        $case = DB::transaction(function () use ($order, $customer, $data, $resolution, $amount, $mediaPath, $mediaType) {
            $case = OrderRequest::create([
                'owner_id' => $order->vendor_id,
                'order_id' => $order->id,
                'user_id' => $customer->id,
                'type' => $data['type'],
                'reason' => $data['reason'],
                'reason_category' => $data['reason_category'] ?? 'other',
                'resolution_requested' => $resolution,
                'amount_requested' => $amount,
                'media_path' => $mediaPath,
                'media_type' => $mediaType,
                'status' => 'pending',
                'offer_resolution' => $resolution,
                'offer_amount' => $amount,
                'offer_by' => 'customer',
                'offered_at' => now(),
                'last_activity_at' => now(),
            ]);
            $this->log($case, $this->customerActor($customer), 'opened', $data['reason'], $resolution, $amount, $mediaPath, $mediaType);

            return $case;
        });

        $this->notifyVendor($case, 'New return/refund request', "{$this->customerName($customer)} asked for {$this->describe($resolution, $amount)} on order {$order->order_number}.");

        return $case;
    }

    // ── Agreeing ─────────────────────────────────────────────────────────

    /** Either side proposes (or updates) what should happen */
    public function offer(OrderRequest $case, array $actor, string $resolution, ?float $amount, ?string $note): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor, $resolution, $amount, $note) {
            $this->assertStatus($case, ['pending'], 'This case is no longer being negotiated.');
            $order = $case->order;
            $this->assertResolution($order, $resolution, $amount);
            $amount = $this->amountFor($order, $resolution, $amount);

            $case->update([
                'offer_resolution' => $resolution,
                'offer_amount' => $amount,
                'offer_by' => $actor['role'],
                'offered_at' => now(),
                'last_activity_at' => now(),
            ]);
            $this->log($case, $actor, 'offer', $note, $resolution, $amount);

            return [
                'title' => $actor['role'] === 'vendor' ? 'The shop made an offer' : 'The customer made a counter-offer',
                'body' => ucfirst($this->describe($resolution, $amount)) . " for order {$order->order_number}.",
            ];
        });
    }

    /** Accept the other side's offer: the case is settled */
    public function accept(OrderRequest $case, array $actor, ?string $note = null): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor, $note) {
            $this->assertStatus($case, ['pending'], 'This case is no longer being negotiated.');
            if (!$case->offer_resolution) {
                throw new RuntimeException('There is no offer to accept yet.');
            }
            if ($case->offer_by === $actor['role']) {
                throw new RuntimeException("You made this offer. Wait for the other side to accept it, or change it.");
            }

            $case->update([
                'status' => 'approved',
                'settled_resolution' => $case->offer_resolution,
                'settled_amount' => $case->offer_amount,
                'settled_at' => now(),
                'admin_notes' => $note ?: $case->admin_notes,
                'last_activity_at' => now(),
            ]);
            $this->log($case, $actor, 'accepted', $note, $case->settled_resolution, $case->settled_amount);

            $next = $case->type === 'return'
                ? 'Next: the item is sent back to the shop.'
                : (OrderRequest::isRefund($case->settled_resolution) ? 'Next: the shop sends the refund.' : 'Next: the shop sends the replacement.');

            return [
                'title' => 'Return/refund agreed',
                'body' => 'Agreed on ' . $this->describe($case->settled_resolution, $case->settled_amount) . " for order {$case->order->order_number}. {$next}",
            ];
        });
    }

    public function reject(OrderRequest $case, array $actor, string $reason): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor, $reason) {
            $this->assertStatus($case, ['pending'], 'Only a case still being negotiated can be rejected.');
            $case->update(['status' => 'rejected', 'closed_reason' => $reason, 'admin_notes' => $reason, 'last_activity_at' => now()]);
            $this->log($case, $actor, 'rejected', $reason);

            return ['title' => 'Return/refund request declined', 'body' => "The shop declined the request on order {$case->order->order_number}: {$reason}"];
        });
    }

    public function withdraw(OrderRequest $case, array $actor, ?string $note): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor, $note) {
            $this->assertStatus($case, ['pending'], 'An agreed case can no longer be withdrawn.');
            $case->update(['status' => 'withdrawn', 'closed_reason' => $note, 'last_activity_at' => now()]);
            $this->log($case, $actor, 'withdrawn', $note);

            return ['title' => 'Return/refund request withdrawn', 'body' => "The customer withdrew the request on order {$case->order->order_number}."];
        });
    }

    public function message(OrderRequest $case, array $actor, string $body, ?UploadedFile $media = null): OrderRequest
    {
        if (!$case->isOpen()) {
            throw new RuntimeException('This case is closed.');
        }
        [$mediaPath, $mediaType] = $this->storeMedia($media);

        return $this->mutate($case, function (OrderRequest $case) use ($actor, $body, $mediaPath, $mediaType) {
            $case->update(['last_activity_at' => now()]);
            $this->log($case, $actor, 'message', $body, null, null, $mediaPath, $mediaType);

            return [
                'title' => $actor['role'] === 'vendor' ? 'New message from the shop' : 'New message from the customer',
                'body' => Str::limit($body, 140) . " (order {$case->order->order_number})",
            ];
        }, notifyImportant: false);
    }

    // ── Carrying it out ──────────────────────────────────────────────────

    public function itemReceived(OrderRequest $case, array $actor, ?string $note): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor, $note) {
            if ($case->stage() !== 'awaiting_return') {
                throw new RuntimeException('This case is not waiting for the item to come back.');
            }
            $case->update(['item_received_at' => now(), 'last_activity_at' => now()]);
            $this->log($case, $actor, 'item_received', $note);

            $order = $case->order;
            $order->update(['status' => 'returned']);
            if ($delivery = $order->delivery) {
                $delivery->update(['status' => 'returned', 'last_scanned_at' => now()]);
                $delivery->logs()->create([
                    'owner_id' => $delivery->owner_id ?? $order->vendor_id,
                    'status' => 'returned',
                    'scanned_at' => now(),
                    'notes' => 'Returned item received by the shop.',
                ]);
            }

            return ['title' => 'The shop received your return', 'body' => "Order {$order->order_number}: the returned item arrived at the shop."];
        });
    }

    public function refundSent(OrderRequest $case, array $actor, string $method, ?string $reference, ?string $note): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor, $method, $reference, $note) {
            if ($case->stage() !== 'awaiting_refund') {
                throw new RuntimeException($case->stage() === 'awaiting_return'
                    ? 'Confirm the returned item arrived before sending the refund.'
                    : 'This case is not waiting for a refund.');
            }
            $case->update(['refund_method' => $method, 'refund_reference' => $reference, 'refund_sent_at' => now(), 'last_activity_at' => now()]);
            $label = self::REFUND_METHODS[$method] ?? $method;
            $this->log($case, $actor, 'refund_sent', trim("Sent by {$label}" . ($reference ? " · ref {$reference}" : '') . ($note ? ". {$note}" : '')), null, $case->settled_amount);

            return [
                'title' => 'Your refund was sent',
                'body' => '₱' . number_format($case->settled_amount, 2) . " by {$label} for order {$case->order->order_number}. Please confirm once you receive it.",
            ];
        });
    }

    /** The customer got the money: the case closes and the refund is recorded */
    public function confirmRefund(OrderRequest $case, array $actor): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor) {
            if ($case->stage() !== 'confirm_refund') {
                throw new RuntimeException('There is no refund waiting for your confirmation.');
            }
            $case->update(['status' => 'completed', 'completed_at' => now(), 'last_activity_at' => now()]);
            $this->log($case, $actor, 'refund_confirmed', null, null, $case->settled_amount);

            $order = $case->order;
            $full = $case->settled_resolution === OrderRequest::FULL_REFUND
                || $case->settled_amount >= (float) $order->total_amount - 0.005;
            if ($full) {
                $order->update(['status' => 'refunded', 'payment_status' => 'refunded']);
                if ($delivery = $order->delivery) {
                    $delivery->update(['status' => 'refunded', 'last_scanned_at' => now()]);
                    $delivery->logs()->create([
                        'owner_id' => $delivery->owner_id ?? $order->vendor_id,
                        'status' => 'refunded',
                        'scanned_at' => now(),
                        'notes' => 'Refund received by the customer.',
                    ]);
                }
            }
            // Recorded as a refund on the vendor's books, so it comes off their profit
            app(VendorFinanceService::class)->handleOrderRefund($order->fresh(), $case->settled_amount, [
                'order_request_id' => $case->id,
                'refund_method' => $case->refund_method,
                'partial' => !$full,
            ]);

            return ['title' => 'Refund confirmed by the customer', 'body' => "Order {$order->order_number}: the customer received ₱" . number_format($case->settled_amount, 2) . '. The case is closed.'];
        });
    }

    public function refundMissing(OrderRequest $case, array $actor, ?string $note): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor, $note) {
            if ($case->stage() !== 'confirm_refund') {
                throw new RuntimeException('There is no refund waiting for your confirmation.');
            }
            $case->update(['refund_sent_at' => null, 'last_activity_at' => now()]);
            $this->log($case, $actor, 'refund_missing', $note ?: "The customer hasn't received the refund yet.");

            return ['title' => "Customer hasn't received the refund", 'body' => "Order {$case->order->order_number}: please check the transfer and send it again."];
        });
    }

    public function replacementSent(OrderRequest $case, array $actor, ?string $note): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor, $note) {
            if ($case->stage() !== 'awaiting_replacement') {
                throw new RuntimeException($case->stage() === 'awaiting_return'
                    ? 'Confirm the returned item arrived before sending the replacement.'
                    : 'This case is not waiting for a replacement.');
            }
            $case->update(['replacement_note' => $note, 'replacement_sent_at' => now(), 'last_activity_at' => now()]);
            $this->log($case, $actor, 'replacement_sent', $note);

            return ['title' => 'Your replacement is on its way', 'body' => "Order {$case->order->order_number}. Please confirm once it arrives."];
        });
    }

    public function confirmReplacement(OrderRequest $case, array $actor): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor) {
            if ($case->stage() !== 'confirm_replacement') {
                throw new RuntimeException('There is no replacement waiting for your confirmation.');
            }
            $case->update(['status' => 'completed', 'completed_at' => now(), 'last_activity_at' => now()]);
            $this->log($case, $actor, 'replacement_confirmed');

            return ['title' => 'Replacement received', 'body' => "Order {$case->order->order_number}: the customer received the replacement. The case is closed."];
        });
    }

    public function replacementMissing(OrderRequest $case, array $actor, ?string $note): OrderRequest
    {
        return $this->mutate($case, function (OrderRequest $case) use ($actor, $note) {
            if ($case->stage() !== 'confirm_replacement') {
                throw new RuntimeException('There is no replacement waiting for your confirmation.');
            }
            $case->update(['replacement_sent_at' => null, 'last_activity_at' => now()]);
            $this->log($case, $actor, 'replacement_missing', $note ?: "The customer hasn't received the replacement yet.");

            return ['title' => "Customer hasn't received the replacement", 'body' => "Order {$case->order->order_number}: please check the delivery."];
        });
    }

    // ── Actors ───────────────────────────────────────────────────────────

    public function customerActor(User $user): array
    {
        return ['role' => 'customer', 'name' => $this->customerName($user), 'user_id' => $user->id, 'employee_id' => null];
    }

    /** The vendor owner or one of their staff */
    public function vendorActor($account): array
    {
        if ($account instanceof Employee) {
            return ['role' => 'vendor', 'name' => trim(($account->name ?? 'Staff') . ' (store staff)'), 'user_id' => null, 'employee_id' => $account->id];
        }

        return ['role' => 'vendor', 'name' => trim(($account->name ?? 'Store') . ' (store owner)'), 'user_id' => $account?->id, 'employee_id' => null];
    }

    // ── Formatting (shared by customer, vendor and admin views) ──────────

    public function summary(OrderRequest $case): array
    {
        return [
            'id' => $case->id,
            'type' => $case->type,
            'status' => $case->status,
            'stage' => $case->stage(),
            'next_actor' => $case->nextActor(),
            'reason' => $case->reason,
            'reason_category' => $case->reason_category,
            'reason_label' => self::REASONS[$case->reason_category] ?? 'Something else',
            'requested' => ['resolution' => $case->resolution_requested, 'amount' => $case->amount_requested],
            'offer' => $case->status === 'pending' && $case->offer_resolution
                ? ['resolution' => $case->offer_resolution, 'amount' => $case->offer_amount, 'by' => $case->offer_by, 'at' => $case->offered_at?->toIso8601String()]
                : null,
            'settled' => $case->settled_resolution
                ? ['resolution' => $case->settled_resolution, 'amount' => $case->settled_amount, 'at' => $case->settled_at?->toIso8601String()]
                : null,
            'item_received_at' => $case->item_received_at?->toIso8601String(),
            'refund' => $case->refund_method
                ? ['method' => $case->refund_method, 'method_label' => self::REFUND_METHODS[$case->refund_method] ?? $case->refund_method, 'reference' => $case->refund_reference, 'sent_at' => $case->refund_sent_at?->toIso8601String()]
                : null,
            'replacement_sent_at' => $case->replacement_sent_at?->toIso8601String(),
            'completed_at' => $case->completed_at?->toIso8601String(),
            'closed_reason' => $case->closed_reason,
            'media_url' => $case->media_path ? CloudinaryHelper::getUrl($case->media_path, $case->media_type === 'video' ? 'video' : 'image') : null,
            'media_type' => $case->media_type,
            'created_at' => $case->created_at?->toIso8601String(),
            'last_activity_at' => ($case->last_activity_at ?? $case->updated_at)?->toIso8601String(),
        ];
    }

    public function detail(OrderRequest $case): array
    {
        $case->loadMissing(['messages', 'order:id,order_number,total_amount,payment_method,payment_status,status,vendor_id']);

        return $this->summary($case) + [
            'order' => [
                'id' => $case->order?->id,
                'order_number' => $case->order?->order_number,
                'total_amount' => (float) ($case->order?->total_amount ?? 0),
                'payment_method' => $case->order?->payment_method,
                'payment_status' => $case->order?->payment_status,
            ],
            'messages' => $case->messages->map(fn (OrderRequestMessage $m) => [
                'id' => $m->id,
                'author_role' => $m->author_role,
                'author_name' => $m->author_name,
                'kind' => $m->kind,
                'body' => $m->body,
                'resolution' => $m->resolution,
                'amount' => $m->amount,
                'media_url' => $m->media_url,
                'media_type' => $m->media_type,
                'at' => $m->created_at?->toIso8601String(),
            ])->values(),
            'refund_methods' => self::REFUND_METHODS,
        ];
    }

    // ── Internals ────────────────────────────────────────────────────────

    /** Locks the case, applies the change, then tells the other side */
    private function mutate(OrderRequest $case, callable $change, bool $notifyImportant = true): OrderRequest
    {
        $actorRole = null;
        $notice = DB::transaction(function () use ($case, $change, &$actorRole) {
            $locked = OrderRequest::query()->whereKey($case->id)->lockForUpdate()->firstOrFail();
            $locked->setRelation('order', Order::query()->with('delivery')->findOrFail($locked->order_id));

            return $change($locked);
        });

        $fresh = $case->fresh(['order', 'user']);
        $last = $fresh->messages()->latest('id')->first();
        if ($notice && $last) {
            $last->author_role === 'customer'
                ? $this->notifyVendor($fresh, $notice['title'], $notice['body'], $notifyImportant)
                : $this->notifyCustomer($fresh, $notice['title'], $notice['body'], $notifyImportant);
        }

        return $fresh;
    }

    private function log(OrderRequest $case, array $actor, string $kind, ?string $body = null, ?string $resolution = null, ?float $amount = null, ?string $mediaPath = null, ?string $mediaType = null): void
    {
        OrderRequestMessage::create([
            'order_request_id' => $case->id,
            'author_role' => $actor['role'],
            'author_name' => Str::limit((string) $actor['name'], 115, ''),
            'author_user_id' => $actor['user_id'] ?? null,
            'author_employee_id' => $actor['employee_id'] ?? null,
            'kind' => $kind,
            'body' => $body !== null && trim($body) !== '' ? trim($body) : null,
            'resolution' => $resolution,
            'amount' => $amount,
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
        ]);
    }

    private function assertStatus(OrderRequest $case, array $statuses, string $message): void
    {
        if (!in_array($case->status, $statuses, true)) {
            throw new RuntimeException($message);
        }
    }

    private function assertResolution(Order $order, string $resolution, ?float $amount): void
    {
        if (!in_array($resolution, OrderRequest::RESOLUTIONS, true)) {
            throw new RuntimeException('Choose a full refund, a partial refund or a replacement.');
        }
        if (OrderRequest::isRefund($resolution) && !in_array($order->payment_status, ['paid'], true)) {
            throw new RuntimeException('A refund is only possible once the order has been paid. A replacement is still possible.');
        }
        if ($resolution === OrderRequest::PARTIAL_REFUND) {
            $total = (float) $order->total_amount;
            if ($amount === null || $amount <= 0 || $amount >= $total) {
                throw new RuntimeException('A partial refund must be more than ₱0 and less than the order total of ₱' . number_format($total, 2) . '.');
            }
        }
    }

    private function amountFor(Order $order, string $resolution, ?float $amount): ?float
    {
        return match ($resolution) {
            OrderRequest::FULL_REFUND => round((float) $order->total_amount, 2),
            OrderRequest::PARTIAL_REFUND => round((float) $amount, 2),
            default => null,
        };
    }

    public function describe(?string $resolution, ?float $amount): string
    {
        return match ($resolution) {
            OrderRequest::FULL_REFUND => 'a full refund of ₱' . number_format((float) $amount, 2),
            OrderRequest::PARTIAL_REFUND => 'a partial refund of ₱' . number_format((float) $amount, 2),
            OrderRequest::REPLACEMENT => 'a replacement',
            default => 'a return/refund',
        };
    }

    private function storeMedia(?UploadedFile $file): array
    {
        if (!$file || !$file->isValid()) {
            return [null, null];
        }
        $type = str_starts_with((string) $file->getMimeType(), 'video/') ? 'video' : 'image';
        $result = CloudinaryHelper::upload($file->getRealPath(), ['folder' => 'returns', 'resource_type' => $type]);
        $path = is_array($result) ? ($result['public_id'] ?? $result['secure_url'] ?? null) : $result;
        if (!$path) {
            throw new RuntimeException('The photo or video could not be uploaded. Please try again.');
        }

        return [$path, $type];
    }

    private function customerName(User $user): string
    {
        return trim($user->name . ' ' . ($user->surname ?? '')) ?: 'Customer';
    }

    private function notifyVendor(OrderRequest $case, string $title, string $body, bool $important = true): void
    {
        $vendorId = $case->order?->vendor_id ?? $case->owner_id;
        $this->notifications->notify([$vendorId], 'return_case', $title, $body, '/vendor/reservation', $important);
    }

    private function notifyCustomer(OrderRequest $case, string $title, string $body, bool $important = true): void
    {
        $this->notifications->notify([$case->user_id], 'return_case', $title, $body, '/customer/orders', $important);
    }
}
