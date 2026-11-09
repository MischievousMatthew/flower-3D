<?php

namespace App\Services;

use App\Models\FundingPaymentCheckout;
use App\Models\FundingRequest;
use App\Models\PurchaseOrderEvent;
use App\Support\PayMongoChannels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Finance pays a supplier through PayMongo Checkout. PayMongo's return link is
 * never proof of payment: the checkout session is looked up at PayMongo (or
 * arrives by the signed webhook) before the payment is recorded, and the
 * supplier is emailed a receipt.
 */
class FundingPayMongoService
{
    /** PayMongo's smallest e-wallet and online-banking payment */
    public const MINIMUM = 20.0;

    public function __construct(private readonly FundingPaymentService $payments) {}

    public function create(int $ownerId, int $fundingId, float $amount, string $channel, ?string $payerName): FundingPaymentCheckout
    {
        $key = config('services.paymongo.secret_key');
        if (!$key) {
            throw new HttpException(503, 'PayMongo isn\'t set up yet, so payments can\'t be made here. Record it as paid another way.');
        }
        $method = PayMongoChannels::methodOf($channel);
        if (!$method) {
            throw new HttpException(422, 'Choose a bank or e-wallet PayMongo offers.');
        }

        $f = FundingRequest::where('owner_id', $ownerId)->with(['linkedOrder.supplier', 'linkedOrder.items', 'payments'])->findOrFail($fundingId);
        $state = $this->payments->state($f);
        if (($state['stage'] ?? null) !== 'to_pay') {
            throw new HttpException(422, 'There\'s nothing to pay on this request right now.');
        }
        $amount = round($amount, 2);
        if ($amount < self::MINIMUM) {
            throw new HttpException(422, sprintf('PayMongo payments start at ₱%s.', number_format(self::MINIMUM, 2)));
        }
        if ($amount > $state['to_pay']) {
            throw new HttpException(422, sprintf('You can pay up to ₱%s.', number_format($state['to_pay'], 2)));
        }

        $order = $f->linkedOrder;
        $supplier = $order?->supplier?->company_name ?: 'the supplier';
        $checkout = FundingPaymentCheckout::create([
            'owner_id' => $ownerId,
            'funding_request_id' => $f->id,
            'purchase_order_id' => $order?->id,
            'status' => 'pending',
            'amount' => $amount,
            'payment_channel' => $channel,
            'reference_number' => 'BC-FP-' . $f->id . '-' . strtoupper(bin2hex(random_bytes(5))),
            'paid_by_name' => $payerName,
        ]);

        $callback = rtrim((string) config('app.url'), '/') . '/api/funding-payments/callback?checkout_id=' . $checkout->id;
        $attributes = [
            'send_email_receipt' => false,
            'show_description' => true,
            'show_line_items' => true,
            'description' => "Payment to {$supplier} for " . ($order?->order_number ?? $f->finance_request_id),
            'line_items' => [[
                'currency' => 'PHP',
                'amount' => (int) round($amount * 100),
                'name' => "Payment to {$supplier}",
                'description' => trim(($order?->order_number ? "{$order->order_number} · " : '') . ($f->product_name ?? 'Stock')),
                'quantity' => 1,
            ]],
            'payment_method_types' => [$method],
            'success_url' => $callback . '&success=true',
            'cancel_url' => $callback . '&success=false',
            'reference_number' => $checkout->reference_number,
            'metadata' => [
                'payment_context' => 'funding_payment',
                'funding_payment_checkout_id' => (string) $checkout->id,
                'funding_request_id' => (string) $f->id,
                'owner_id' => (string) $ownerId,
            ],
        ];

        try {
            $response = Http::withBasicAuth($key, '')->acceptJson()->timeout(30)
                ->post('https://api.paymongo.com/v1/checkout_sessions', ['data' => ['attributes' => $attributes]]);
            $data = $response->json();
            $url = data_get($data, 'data.attributes.checkout_url');
            if (!$response->successful() || !$url) {
                $detail = (string) data_get($data, 'errors.0.detail', '');
                $checkout->update(['status' => 'failed', 'metadata' => ['gateway_error' => $detail]]);
                Log::warning('Funding PayMongo checkout refused', ['checkout_id' => $checkout->id, 'detail' => $detail]);
                throw new HttpException(422, str_contains(strtolower($detail), 'payment_method')
                    ? "{$channel} isn't turned on in the PayMongo account yet. Choose another bank or e-wallet, or turn it on in PayMongo."
                    : 'PayMongo couldn\'t start the payment. Please try again.');
            }
            $checkout->update(['paymongo_checkout_session_id' => data_get($data, 'data.id'), 'checkout_url' => $url]);

            return $checkout->fresh();
        } catch (HttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            $checkout->update(['status' => 'failed']);
            Log::warning('Funding PayMongo checkout failed', ['checkout_id' => $checkout->id, 'error' => $e->getMessage()]);
            throw new HttpException(502, 'PayMongo couldn\'t be reached. Please try again.');
        }
    }

    public function handlesWebhook(array $payload): bool
    {
        return data_get($payload, 'data.attributes.data.attributes.metadata.payment_context') === 'funding_payment';
    }

    public function handleWebhook(array $payload): void
    {
        $id = data_get($payload, 'data.attributes.data.attributes.metadata.funding_payment_checkout_id');
        if ($id && ($checkout = FundingPaymentCheckout::find($id))) {
            // Looked up at PayMongo again rather than trusting the payload
            $this->confirm($checkout);
        }
    }

    /** Checks the session at PayMongo; records the payment once, when it's paid */
    public function confirm(FundingPaymentCheckout $checkout): bool
    {
        if ($checkout->status !== 'pending' || !$checkout->paymongo_checkout_session_id) {
            return $checkout->status === 'paid';
        }
        $key = config('services.paymongo.secret_key');
        if (!$key) {
            return false;
        }

        try {
            $response = Http::withBasicAuth($key, '')->acceptJson()->timeout(15)
                ->get('https://api.paymongo.com/v1/checkout_sessions/' . urlencode($checkout->paymongo_checkout_session_id));
            if (!$response->successful()) {
                return false;
            }
            $attributes = data_get($response->json(), 'data.attributes', []);
            if ((string) data_get($attributes, 'metadata.funding_payment_checkout_id') !== (string) $checkout->id) {
                Log::warning('Funding checkout metadata did not match', ['checkout_id' => $checkout->id]);
                return false;
            }
            $payment = collect(data_get($attributes, 'payments', []))
                ->first(fn ($p) => in_array(strtolower((string) data_get($p, 'attributes.status')), ['paid', 'succeeded'], true));
            if (!$payment) {
                return false;
            }
            $paymentId = (string) data_get($payment, 'id');
            $paidCentavos = (int) data_get($payment, 'attributes.amount', 0);
            if ($paidCentavos > 0 && abs($paidCentavos - (int) round((float) $checkout->amount * 100)) > 0) {
                Log::warning('Funding checkout paid a different amount', ['checkout_id' => $checkout->id, 'paid' => $paidCentavos]);
            }
        } catch (\Throwable $e) {
            Log::warning('Funding checkout lookup failed', ['checkout_id' => $checkout->id, 'error' => $e->getMessage()]);
            return false;
        }

        // Only one of the return link and the webhook records it
        return DB::transaction(function () use ($checkout, $paymentId) {
            $locked = FundingPaymentCheckout::lockForUpdate()->find($checkout->id);
            if ($locked->status !== 'pending') {
                return $locked->status === 'paid';
            }
            try {
                $state = $this->payments->pay($locked->owner_id, $locked->funding_request_id, [
                    'amount' => (float) $locked->amount,
                    'payment_method' => PayMongoChannels::kindOf($locked->payment_channel),
                    'payment_channel' => $locked->payment_channel,
                    'reference' => $paymentId,
                    'paid_on' => now()->timezone('Asia/Manila')->toDateString(),
                    'note' => "Paid through PayMongo ({$locked->reference_number})",
                    'recorded_by_name' => $locked->paid_by_name,
                    'via' => 'paymongo',
                ]);
                $locked->update(['status' => 'paid', 'paymongo_payment_id' => $paymentId, 'paid_at' => now(), 'funding_request_payment_id' => $state['payment_id'] ?? null]);

                return true;
            } catch (HttpException $e) {
                // Paid at PayMongo but no longer payable here (e.g. paid twice): kept for a person to sort out
                $locked->update(['status' => 'needs_review', 'paymongo_payment_id' => $paymentId, 'metadata' => ['problem' => $e->getMessage()]]);
                Log::warning('Funding checkout paid but not recorded', ['checkout_id' => $locked->id, 'reason' => $e->getMessage()]);

                return false;
            }
        });
    }

    public function cancel(FundingPaymentCheckout $checkout): void
    {
        if ($checkout->status === 'pending') {
            $checkout->update(['status' => 'cancelled']);
        }
    }
}
