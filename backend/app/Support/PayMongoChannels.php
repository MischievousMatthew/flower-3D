<?php

namespace App\Support;

/**
 * The banks and e-wallets a funding request can be paid through: only those
 * PayMongo Checkout offers, each with its PayMongo payment method type.
 */
class PayMongoChannels
{
    // QR Ph: PayMongo shows a QR code the payer scans with almost any bank or e-wallet app
    public const QR = 'QR Ph';

    public const BANK = [
        'QR Ph' => 'qrph',
        'BPI' => 'dob',
        'UnionBank' => 'dob_ubp',
        'BDO' => 'brankas_bdo',
        'Landbank' => 'brankas_landbank',
        'Metrobank' => 'brankas_metrobank',
    ];

    public const EWALLET = [
        'GCash' => 'gcash',
        'Maya' => 'paymaya',
        'GrabPay' => 'grab_pay',
        'ShopeePay' => 'shopee_pay',
    ];

    /** ['bank' => [names], 'ewallet' => [names]] */
    public static function names(): array
    {
        return ['bank' => array_keys(self::BANK), 'ewallet' => array_keys(self::EWALLET)];
    }

    public static function all(): array
    {
        return array_merge(array_keys(self::BANK), array_keys(self::EWALLET));
    }

    /** Accounts a supplier can be paid into: everything except QR Ph */
    public static function payoutNames(): array
    {
        return array_values(array_diff(self::all(), [self::QR]));
    }

    public static function methodOf(string $channel): ?string
    {
        return self::BANK[$channel] ?? self::EWALLET[$channel] ?? null;
    }

    public static function kindOf(string $channel): string
    {
        return isset(self::EWALLET[$channel]) ? 'ewallet' : 'bank';
    }
}
