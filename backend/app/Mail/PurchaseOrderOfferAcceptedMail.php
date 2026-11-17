<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\BrevoService;

/**
 * Tells a supplier who offered less than was ordered that the shop accepted
 * their numbers, so they can now prepare and ship it.
 */
class PurchaseOrderOfferAcceptedMail
{
    public function __construct(
        private readonly PurchaseOrder $order,
        private readonly ?string $respondUrl = null,
        private readonly ?string $note = null,
    ) {}

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
        $html = view('emails.purchase-order-offer-accepted', [
            'order' => $order,
            'shopName' => $shopName,
            'respondUrl' => $this->respondUrl,
            'note' => $this->note,
        ])->render();

        $response = (new BrevoService())->send($to, $order->supplier->company_name, "Accepted: purchase order {$order->order_number} from {$shopName}", $html);

        return $response->successful();
    }
}
