<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\BrevoService;

/**
 * Tells the supplier a purchase order they were sent has been cancelled.
 */
class PurchaseOrderCancelledMail
{
    public function __construct(private readonly PurchaseOrder $order, private readonly string $reason) {}

    /** True when the email service accepted it */
    public function send(): bool
    {
        $order = $this->order->loadMissing('items', 'supplier');
        $to = $order->supplier?->email;
        if (! $to) {
            return false;
        }

        $owner = User::find($order->owner_id);
        $shopName = $owner?->vendor?->store_name ?: ($owner?->name ?: 'Our flower shop');
        $html = view('emails.purchase-order-cancelled', [
            'order' => $order,
            'shopName' => $shopName,
            'reason' => $this->reason,
        ])->render();

        $response = (new BrevoService())->send($to, $order->supplier->company_name, "Cancelled: purchase order {$order->order_number} from {$shopName}", $html);

        return $response->successful();
    }
}
