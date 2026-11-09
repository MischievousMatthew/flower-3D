<?php

namespace App\Support;

/**
 * Reads a supplier's payment terms (free text, e.g. "Pay before delivery",
 * "Cash on delivery", "15 days") to know when a funding request is paid.
 *
 *  - before_delivery: paid once the supplier confirms what they can supply
 *  - after_delivery:  paid once the stock is in, for what was received;
 *                     "15 days" etc. gives a due date after delivery
 *
 * Unclear or missing terms count as after delivery, so nothing is paid for
 * stock that might not come.
 */
class SupplierPaymentTerms
{
    public const BEFORE = 'before_delivery';
    public const AFTER = 'after_delivery';

    public static function timing(?string $terms): string
    {
        $t = strtolower((string) $terms);

        return preg_match('/before|advance|upfront|up-front|up front|prepaid|pre-paid|prepay|full payment first|pay first|down ?payment/', $t)
            ? self::BEFORE
            : self::AFTER;
    }

    /** Days after delivery the payment is due, for terms like "30 days" or "Net 15" */
    public static function dueDays(?string $terms): ?int
    {
        if (self::timing($terms) === self::BEFORE) {
            return null;
        }
        if (preg_match('/(\d{1,3})\s*-?\s*(day|d\b)|net\s*(\d{1,3})/i', (string) $terms, $m)) {
            return (int) ($m[1] !== '' ? $m[1] : $m[3]);
        }

        return null;
    }
}
