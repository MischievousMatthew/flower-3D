<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\BrevoService;

/**
 * The purchase order emailed to the supplier once it's approved.
 */
class PurchaseOrderMail
{
    /**
     * @param string|null $respondUrl The supplier's private link to confirm, decline or mark it shipped
     * @param \DateTimeInterface|null $linkExpires When that link stops working
     */
    public function __construct(
        private readonly PurchaseOrder $order,
        private readonly ?string $sentBy = null,
        private readonly ?string $respondUrl = null,
        private readonly ?\DateTimeInterface $linkExpires = null,
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

        $html = view('emails.purchase-order', [
            'order'    => $order,
            'shopName' => $shopName,
            'sentBy'   => $this->sentBy,
            'respondUrl' => $this->respondUrl,
            'linkExpires' => $this->linkExpires,
        ])->render();

        $response = (new BrevoService())->send($to, $order->supplier->company_name, "Purchase order {$order->order_number} from {$shopName}", $html);

        return $response->successful();
    }
}
