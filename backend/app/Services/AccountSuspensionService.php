<?php

namespace App\Services;

use App\Models\AccountEvent;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Suspending a vendor or customer account.
 *
 * - Vendor: the owner and their staff are signed out and can't sign in, the
 *   store is hidden from customers and takes no new orders.
 * - Customer: they're signed out and can't sign in (so they can't order,
 *   review, report or chat).
 *
 * Orders, payments and balances are never touched. Reactivating lifts it.
 */
class AccountSuspensionService
{
    private const SUSPENDABLE = [User::ROLE_VENDOR, User::ROLE_CUSTOMER];

    public const VENDOR_MESSAGE = 'This store has been suspended by BloomCraft. Contact support to have it reviewed.';
    public const CUSTOMER_MESSAGE = 'Your account has been suspended by BloomCraft. Contact support to have it reviewed.';

    public function suspend(User $user, ?User $admin, string $reason): void
    {
        if (! in_array($user->role, self::SUSPENDABLE, true)) {
            throw new LogicException('Only vendor and customer accounts can be suspended.');
        }
        if ($user->is_suspended) {
            throw new LogicException('This account is already suspended.');
        }

        DB::transaction(function () use ($user, $admin, $reason) {
            // Clearing the tokens signs everyone out straight away
            $user->forceFill(['is_suspended' => true, 'api_token' => null])->save();
            if ($user->role === User::ROLE_VENDOR) {
                Employee::query()->where('owner_id', $user->id)->update(['api_token' => null]);
            }

            AccountEvent::create([
                'user_id' => $user->id,
                'admin_id' => $admin?->id,
                'action' => AccountEvent::SUSPENDED,
                'reason' => $reason,
            ]);
        });

        Log::info('Account suspended', ['user_id' => $user->id, 'role' => $user->role, 'admin_id' => $admin?->id]);
    }

    public function reactivate(User $user, ?User $admin, ?string $note = null): void
    {
        if (! in_array($user->role, self::SUSPENDABLE, true)) {
            throw new LogicException('Only vendor and customer accounts can be reactivated.');
        }
        if (! $user->is_suspended) {
            throw new LogicException('This account is not suspended.');
        }

        DB::transaction(function () use ($user, $admin, $note) {
            $user->forceFill(['is_suspended' => false])->save();

            AccountEvent::create([
                'user_id' => $user->id,
                'admin_id' => $admin?->id,
                'action' => AccountEvent::REACTIVATED,
                'reason' => $note,
            ]);
        });

        Log::info('Account reactivated', ['user_id' => $user->id, 'role' => $user->role, 'admin_id' => $admin?->id]);
    }

    /** True when the account (or, for staff, their store) is suspended */
    public static function isBlocked($account): bool
    {
        if ($account instanceof User) {
            // Admins turned off in Security → Administrators are blocked too
            return in_array($account->role, [...self::SUSPENDABLE, User::ROLE_ADMIN], true) && (bool) $account->is_suspended;
        }
        if ($account instanceof Employee && $account->owner_id) {
            return (bool) User::query()->whereKey($account->owner_id)->value('is_suspended');
        }

        return false;
    }

    public static function messageFor($account): string
    {
        if ($account instanceof User && $account->role === User::ROLE_ADMIN) {
            return 'Your admin access has been turned off. Contact a super admin.';
        }

        return $account instanceof User && $account->role === User::ROLE_CUSTOMER
            ? self::CUSTOMER_MESSAGE
            : self::VENDOR_MESSAGE;
    }
}
