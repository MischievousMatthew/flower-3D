<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Services\ReturnCaseService;
use Tests\TestCase;

/** A customer's partial refund request is half the order total. */
class PartialRefundAmountTest extends TestCase
{
    public function test_a_partial_refund_is_half_the_order_total(): void
    {
        $this->assertSame(75.0, ReturnCaseService::partialRefundFor((new Order())->forceFill(['total_amount' => 150])));
        $this->assertSame(1000.0, ReturnCaseService::partialRefundFor((new Order())->forceFill(['total_amount' => 2000])));
        $this->assertSame(50.13, ReturnCaseService::partialRefundFor((new Order())->forceFill(['total_amount' => 100.25])));
    }
}
