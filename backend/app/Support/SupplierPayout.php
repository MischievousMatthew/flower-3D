<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/** A supplier's bank or e-wallet account for being paid */
class SupplierPayout
{
    public const FIELDS = ['payout_method', 'payout_provider', 'payout_account_name', 'payout_account_number'];

    public static function has(?Model $m): bool
    {
        return $m !== null && filled($m->payout_provider) && filled($m->payout_account_number);
    }

    /** Last four digits only, for the supplier's own page */
    public static function mask(?string $number): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $number);

        return $digits === '' ? null : '•••• ' . substr($digits, -4);
    }

    /** Full details, for Finance paying into it */
    public static function full(?Model $m): ?array
    {
        if (! self::has($m)) {
            return null;
        }

        return [
            'method' => $m->payout_method,
            'provider' => $m->payout_provider,
            'account_name' => $m->payout_account_name,
            'account_number' => $m->payout_account_number,
        ];
    }

    /** Masked details, for showing the supplier what's on file */
    public static function masked(?Model $m): ?array
    {
        if (! self::has($m)) {
            return null;
        }

        return [
            'method' => $m->payout_method,
            'provider' => $m->payout_provider,
            'account_name' => $m->payout_account_name,
            'account_number' => self::mask($m->payout_account_number),
        ];
    }
}
