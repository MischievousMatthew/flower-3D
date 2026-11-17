<?php

namespace App\Support;

/**
 * The banks a funding request can be paid through: only those PayMongo
 * Checkout offers, each with its PayMongo payment method type. Supplies cost
 * too much for e-wallet limits, so paying suppliers is banks only; the
 * e-wallets stay listed only so older requests and accounts still read right.
 */
class PayMongoChannels
{
    // Named banks only. QR Ph is left out: its QR code can be scanned with an
    // e-wallet app too, and supplies are paid by bank only
    public const BANK = [
        'BPI' => 'dob',
        'UnionBank' => 'dob_ubp',
        'BDO' => 'brankas_bdo',
        'Landbank' => 'brankas_landbank',
        'Metrobank' => 'brankas_metrobank',
    ];

    // No longer offered (see above); kept to read older records
    public const EWALLET = [
        'GCash' => 'gcash',
        'Maya' => 'paymaya',
        'GrabPay' => 'grab_pay',
        'ShopeePay' => 'shopee_pay',
    ];

    /** ['bank' => [names]] */
    public static function names(): array
    {
        return ['bank' => array_keys(self::BANK)];
    }

    /** What can be chosen now: banks only */
    public static function all(): array
    {
        return array_keys(self::BANK);
    }

    /**
     * How each account number looks: how many digits and how they're grouped.
     * E-wallets use the mobile number. Matches ACCOUNT_FORMATS in paymongoChannels.js.
     */
    public const ACCOUNT_FORMATS = [
        'BPI' => ['lengths' => [10], 'groups' => [4, 3, 3], 'example' => '1234 567 890'],
        'BDO' => ['lengths' => [10, 12], 'groups' => [4, 4, 4], 'example' => '0012 3456 7890'],
        'Metrobank' => ['lengths' => [13], 'groups' => [3, 3, 5, 2], 'example' => '123 456 78901 2'],
        'Landbank' => ['lengths' => [10], 'groups' => [4, 4, 2], 'example' => '1234 5678 90'],
        'UnionBank' => ['lengths' => [12], 'groups' => [4, 4, 4], 'example' => '1234 5678 9012'],
        'mobile' => ['lengths' => [11], 'groups' => [4, 3, 4], 'example' => '0917 123 4567', 'prefix' => '09'],
    ];

    public static function accountFormat(?string $provider): ?array
    {
        return isset(self::EWALLET[$provider]) ? self::ACCOUNT_FORMATS['mobile'] : (self::ACCOUNT_FORMATS[$provider] ?? null);
    }

    /** What's wrong with the number for this bank or e-wallet, or null when it fits */
    public static function accountError(?string $provider, ?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);
        $f = self::accountFormat($provider);
        if ($digits === '') {
            return 'Enter the account or mobile number.';
        }
        if (! $f) {
            return strlen($digits) >= 6 ? null : 'Enter at least 6 digits.';
        }
        if (isset($f['prefix']) && ! str_starts_with($digits, $f['prefix'])) {
            return "Mobile numbers start with {$f['prefix']}, e.g. {$f['example']}.";
        }
        if (! in_array(strlen($digits), $f['lengths'], true)) {
            $want = implode(' or ', $f['lengths']);

            return isset(self::EWALLET[$provider])
                ? "The mobile number has {$want} digits, e.g. {$f['example']}."
                : "A {$provider} account number has {$want} digits, e.g. {$f['example']}.";
        }

        return null;
    }

    /** Spaced the way the bank prints it, e.g. 0917 123 4567 */
    public static function formatAccount(?string $provider, ?string $number): string
    {
        $digits = preg_replace('/\D/', '', (string) $number);
        $f = self::accountFormat($provider);
        if (! $f) {
            return trim(chunk_split($digits, 4, ' '));
        }
        $parts = [];
        $i = 0;
        foreach ($f['groups'] as $g) {
            if ($i >= strlen($digits)) {
                break;
            }
            $parts[] = substr($digits, $i, $g);
            $i += $g;
        }
        if ($i < strlen($digits)) {
            $parts[] = substr($digits, $i);
        }

        return implode(' ', $parts);
    }

    /** Accounts a supplier can be paid into: the same named banks */
    public static function payoutNames(): array
    {
        return self::all();
    }

    public static function methodOf(string $channel): ?string
    {
        // Only banks can be paid through now
        return self::BANK[$channel] ?? null;
    }

    public static function kindOf(string $channel): string
    {
        return isset(self::EWALLET[$channel]) ? 'ewallet' : 'bank';
    }
}
