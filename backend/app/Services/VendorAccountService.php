<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Models\VendorAccountEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * Suspending a vendor signs out the owner and their staff, blocks their
 * logins, hides the store from customers and stops new orders to it.
 * Reactivating lifts all of that. Existing orders, payments and balances
 * are left untouched.
 */
class VendorAccountService
{
    public function suspend(User $vendor, ?User $admin, string $reason): void
    {
        if ($vendor->role !== User::ROLE_VENDOR) {
            throw new LogicException('Only vendor accounts can be suspended.');
        }
        if ($vendor->is_suspended) {
            throw new LogicException('This vendor is already suspended.');
        }

        DB::transaction(function () use ($vendor, $admin, $reason) {
            // Clearing the tokens signs everyone out straight away
            $vendor->forceFill(['is_suspended' => true, 'api_token' => null])->save();
            Employee::query()->where('owner_id', $vendor->id)->update(['api_token' => null]);

            VendorAccountEvent::create([
                'vendor_id' => $vendor->id,
                'admin_id' => $admin?->id,
                'action' => VendorAccountEvent::SUSPENDED,
                'reason' => $reason,
            ]);
        });

        Log::info('Vendor suspended', ['vendor_id' => $vendor->id, 'admin_id' => $admin?->id]);
    }

    public function reactivate(User $vendor, ?User $admin, ?string $note = null): void
    {
        if ($vendor->role !== User::ROLE_VENDOR) {
            throw new LogicException('Only vendor accounts can be reactivated.');
        }
        if (! $vendor->is_suspended) {
            throw new LogicException('This vendor is not suspended.');
        }

        DB::transaction(function () use ($vendor, $admin, $note) {
            $vendor->forceFill(['is_suspended' => false])->save();

            VendorAccountEvent::create([
                'vendor_id' => $vendor->id,
                'admin_id' => $admin?->id,
                'action' => VendorAccountEvent::REACTIVATED,
                'reason' => $note,
            ]);
        });

        Log::info('Vendor reactivated', ['vendor_id' => $vendor->id, 'admin_id' => $admin?->id]);
    }

    /** True when the user is a suspended vendor, or staff of one */
    public static function isBlocked($account): bool
    {
        if ($account instanceof User) {
            return $account->role === User::ROLE_VENDOR && (bool) $account->is_suspended;
        }
        if ($account instanceof Employee && $account->owner_id) {
            return (bool) User::query()->whereKey($account->owner_id)->value('is_suspended');
        }

        return false;
    }

    public const BLOCKED_MESSAGE = 'This store has been suspended by BloomCraft. Contact support to have it reviewed.';
}
