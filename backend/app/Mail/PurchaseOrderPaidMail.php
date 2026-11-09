<?php

namespace App\Mail;

use App\Models\FundingRequest;
use App\Models\FundingRequestPayment;
use App\Models\PurchaseOrder;
use App\Models\User;
use App\Services\BrevoService;
use App\Support\SupplierPayout;

/**
 * Tells the supplier the shop has paid for a purchase order, as a BloomCraft
 * payment receipt: receipt number, amount, how and when it was paid, the
 * reference (PayMongo's payment ID when paid through PayMongo), and what it
 * was for.
 */
class PurchaseOrderPaidMail
{
    public function __construct(
        private readonly PurchaseOrder $order,
        private readonly FundingRequestPayment $payment,
        private readonly ?FundingRequest $funding = null,
        // 'paymongo' when paid through PayMongo Checkout
        private readonly ?string $via = null,
    ) {}

    /** True when the email service accepted it */
    public function send(): bool
    {
        $order = $this->order->loadMissing('supplier', 'items');
        $to = $order->supplier?->email;
        if (! $to) {
            return false;
        }

        $owner = User::find($order->owner_id);
        $shopName = $owner?->vendor?->store_name ?: ($owner?->name ?: 'Our flower shop');
        $html = view('emails.purchase-order-paid', [
            'order' => $order,
            'shopName' => $shopName,
            'payment' => $this->payment,
            'funding' => $this->funding,
            'viaPayMongo' => $this->via === 'paymongo',
            'account' => SupplierPayout::masked($order) ?? SupplierPayout::masked($order->supplier),
        ])->render();

        $amount = number_format((float) $this->payment->amount, 2);
        $response = (new BrevoService())->send($to, $order->supplier->company_name, "Payment receipt: ₱{$amount} for {$order->order_number} from {$shopName}", $html);

        return $response->successful();
    }
}
