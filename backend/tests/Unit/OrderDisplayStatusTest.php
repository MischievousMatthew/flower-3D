<?php

namespace Tests\Unit;

use App\Models\Delivery;
use App\Models\Order;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** The one status the shop, the customer and the ERP all show for an order. */
class OrderDisplayStatusTest extends TestCase
{
    private function order(string $status, ?string $deliveryStatus): Order
    {
        $order = (new Order())->forceFill(['status' => $status]);
        $order->setRelation('delivery', $deliveryStatus ? (new Delivery())->forceFill(['status' => $deliveryStatus]) : null);

        return $order;
    }

    public static function cases(): array
    {
        return [
            'the delivery step decides' => ['processing', 'to_ship', 'to_ship', 'Ready'],
            'preparing' => ['processing', 'to_processed', 'to_processed', 'Preparing'],
            'out for delivery' => ['delivered', 'to_received', 'to_received', 'Out for delivery'],
            'old scanner key' => ['delivered', 'to_receive', 'to_received', 'Out for delivery'],
            'cancelled wins over the delivery' => ['cancelled', 'pending', 'cancelled', 'Cancelled'],
            'refunded after delivery' => ['refunded', 'completed', 'refunded', 'Refunded'],
            'returned after delivery' => ['returned', 'completed', 'returned', 'Returned'],
            'no delivery yet' => ['pending', null, 'pending', 'Pending'],
            'no delivery, preparing' => ['processing', null, 'to_processed', 'Preparing'],
            'no delivery, delivered' => ['delivered', null, 'to_received', 'Out for delivery'],
            'payment failed' => ['failed', null, 'cancelled', 'Cancelled'],
        ];
    }

    #[DataProvider('cases')]
    public function test_status_and_words(string $status, ?string $delivery, string $key, string $label): void
    {
        $order = $this->order($status, $delivery);
        $this->assertSame($key, $order->displayStatus());
        $this->assertSame($label, $order->displayStatusLabel());
    }
}
