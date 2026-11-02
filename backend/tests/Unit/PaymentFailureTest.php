<?php

namespace Tests\Unit;

use App\Models\Order;
use PHPUnit\Framework\TestCase;

class PaymentFailureTest extends TestCase
{
    private function order(string $paymentStatus, array $response): Order
    {
        $order = new Order();
        $order->payment_status = $paymentStatus;
        $order->paymongo_response = $response;

        return $order;
    }

    public function test_no_reason_unless_the_payment_failed(): void
    {
        $this->assertNull($this->order('paid', ['failed_code' => 'insufficient_funds'])->paymentFailure());
        $this->assertNull($this->order('unpaid', [])->paymentFailure());
    }

    public function test_paymongo_decline_uses_its_code(): void
    {
        $failure = $this->order('failed', ['failed_code' => 'insufficient_funds', 'cancelled_at' => 'x'])->paymentFailure();

        $this->assertSame('declined', $failure['reason']);
        $this->assertSame('insufficient_funds', $failure['code']);
        $this->assertStringContainsString('enough balance', $failure['message']);
    }

    public function test_unknown_decline_code_still_reads_plainly(): void
    {
        $failure = $this->order('failed', ['failed_code' => 'something_new'])->paymentFailure();

        $this->assertSame('Your bank or e-wallet declined the payment.', $failure['message']);
    }

    public function test_cancelled_not_started_and_unknown(): void
    {
        $this->assertSame('cancelled', $this->order('failed', ['cancelled_at' => '2026-11-02'])->paymentFailure()['reason']);
        $this->assertSame('not_started', $this->order('failed', ['error' => 'cURL error 28'])->paymentFailure()['reason']);
        $this->assertSame('unconfirmed', $this->order('failed', [])->paymentFailure()['reason']);
    }

    public function test_technical_start_error_is_never_shown(): void
    {
        $failure = $this->order('failed', ['error' => 'cURL error 28: timed out'])->paymentFailure();

        $this->assertStringNotContainsString('cURL', json_encode($failure));
    }
}
