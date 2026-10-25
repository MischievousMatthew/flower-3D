<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * Finds the account someone typed (username or email) the way sign-up and
 * "forgot password" do: capitals don't matter. PostgreSQL compares text
 * case-sensitively, so "Ana@Gmail.com" used to miss "ana@gmail.com".
 */
final class AccountLookup
{
    /**
     * @return array{0: ?Model, 1: bool} the account (or null) and whether the password is right.
     * An exact match comes first; if two older accounts differ only by capitals,
     * the one whose password matches is used, so neither owner is locked out.
     */
    public static function signIn(Builder $query, ?string $typed, ?string $password): array
    {
        $typed = trim((string) $typed);
        if ($typed === '') {
            return [null, false];
        }
        $lower = mb_strtolower($typed);

        $candidates = $query
            ->where(fn ($q) => $q->whereRaw('lower(username) = ?', [$lower])->orWhereRaw('lower(email) = ?', [$lower]))
            ->orderByRaw('case when username = ? or email = ? then 0 else 1 end', [$typed, $typed])
            ->limit(5)
            ->get();

        foreach ($candidates as $account) {
            if ($account->password && Hash::check((string) $password, $account->password)) {
                return [$account, true];
            }
        }

        // No such account: check against a stand-in anyway, so the answer takes
        // as long as a wrong password and doesn't reveal which names exist
        if ($candidates->isEmpty()) {
            Hash::check((string) $password, self::standIn());
        }

        return [$candidates->first(), false];
    }

    private static ?string $standIn = null;

    private static function standIn(): string
    {
        return self::$standIn ??= Hash::make(bin2hex(random_bytes(16)));
    }
}
