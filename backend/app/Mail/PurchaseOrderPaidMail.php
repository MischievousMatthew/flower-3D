<?php

namespace App\Mail;

use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\BrevoService;
use App\Support\SupplierPayout;

/**
 * Tells the supplier the shop has paid for a purchase order, with the
 * reference number so they can find it on their statement.
 */
class PurchaseOrderPaidMail
{
    public function __construct(
        private readonly PurchaseOrder $order,
        private readonly float $amount,
        private readonly string $paidOn,
        private readonly string $reference,
        private readonly ?string $channel = null,
    ) {}

    /** True when the email service accepted it */
    public function send(): bool
    {
        $order = $this->order->loadMissing('supplier');
        $to = $order->supplier?->email;
        if (! $to) {
            return false;
        }

        $owner = User::find($order->owner_id);
        $shopName = $owner?->vendor?->store_name ?: ($owner?->name ?: 'Our flower shop');
        $account = SupplierPayout::masked($order) ?? SupplierPayout::masked($order->supplier);
        $html = view('emails.purchase-order-paid', [
            'order' => $order,
            'shopName' => $shopName,
            'amount' => $this->amount,
            'paidOn' => \Carbon\Carbon::parse($this->paidOn),
            'reference' => $this->reference,
            'channel' => $this->channel,
            'account' => $account,
        ])->render();

        $response = (new BrevoService())->send($to, $order->supplier->company_name, sprintf('Payment sent: ₱%s for %s from %s', number_format($this->amount, 2), $order->order_number, $shopName), $html);

        return $response->successful();
    }
}
