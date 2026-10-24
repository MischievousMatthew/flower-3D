<?php

namespace App\Support;

/**
 * Reads what PayMongo says about a payment: the amount charged, its fee, what
 * reaches the shop and how the customer paid. PayMongo sends amounts in
 * centavos; these come back in pesos.
 */
final class PayMongoPayment
{
    /** From a whole webhook body (checkout_session.payment.paid or payment.paid) */
    public static function fromEvent(array $payload): ?array
    {
        $resource = data_get($payload, 'data.attributes.data');
        if (! is_array($resource)) {
            return null;
        }

        // A checkout session lists its payments; a payment event is the payment itself
        $payment = data_get($resource, 'attributes.payments.0') ?? $resource;

        return self::fromPaymentResource(is_array($payment) ? $payment : []);
    }

    /** From a payment resource: ['id' => 'pay_…', 'attributes' => [...]] */
    public static function fromPaymentResource(array $payment): ?array
    {
        $attributes = $payment['attributes'] ?? [];
        if (! isset($attributes['amount'])) {
            return null;
        }

        $peso = fn ($centavos) => $centavos === null ? null : round(((int) $centavos) / 100, 2);

        return [
            'payment_id' => $payment['id'] ?? null,
            'charged_amount' => $peso($attributes['amount']),
            'fee' => $peso($attributes['fee'] ?? null),
            'net_amount' => $peso($attributes['net_amount'] ?? null),
            'source' => data_get($attributes, 'source.type') ?? data_get($attributes, 'payment_method_type'),
        ];
    }
}
