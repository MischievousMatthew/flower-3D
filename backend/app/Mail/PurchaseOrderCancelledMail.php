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
        $e = fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

        $items = $order->items->map(fn ($i) => '<li style="margin:0 0 4px;">' . $e($i->product_name) . ' &middot; ' . number_format($i->quantity) . ' ' . $e($i->unit ?? '') . '</li>')->implode('');

        $html = '<!DOCTYPE html><html lang="en"><body style="margin:0;padding:0;background:#f3f5f4;font-family:Arial,sans-serif;color:#1f2430;">'
            . '<div style="max-width:620px;margin:0 auto;padding:32px 16px;"><div style="background:#ffffff;border-radius:18px;padding:32px;border:1px solid #e3e8e5;">'
            . '<p style="margin:0 0 6px;font-size:12px;letter-spacing:.12em;text-transform:uppercase;color:#b4443c;font-weight:700;">Order cancelled</p>'
            . '<h1 style="margin:0 0 16px;font-size:24px;color:#1f2430;">' . $e($order->order_number) . '</h1>'
            . '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#3b4250;">Hello ' . $e($order->supplier?->contact_person ?: 'there') . ',<br>'
            . $e($shopName) . ' has cancelled this order. Please don\'t prepare or send it.</p>'
            . '<ul style="margin:0 0 16px;padding-left:18px;font-size:14px;color:#3b4250;">' . $items . '</ul>'
            . '<div style="margin:0 0 18px;padding:14px 16px;border-radius:12px;background:#f3f7f5;border-left:3px solid #4DB173;">'
            . '<p style="margin:0 0 4px;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#2f8f5b;">Reason</p>'
            . '<p style="margin:0;font-size:14px;line-height:1.6;color:#3b4250;white-space:pre-line;">' . $e($this->reason) . '</p></div>'
            . '<p style="margin:0;font-size:13px;color:#7a8290;">Sent by ' . $e($shopName) . ' through BloomCraft. The link in the earlier email no longer works.</p>'
            . '</div></div></body></html>';

        $response = (new BrevoService())->send($to, $order->supplier->company_name, "Cancelled: purchase order {$order->order_number} from {$shopName}", $html);

        return $response->successful();
    }
}
