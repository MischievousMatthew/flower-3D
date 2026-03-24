<?php

namespace App\Traits;

use Illuminate\Http\Request;

/**
 * ResolvesOwner
 *
 * Determines the correct vendor/owner ID regardless of whether the
 * authenticated user is a Vendor (User) or an Employee.
 *
 * Usage: `use ResolvesOwner;` in any controller that creates resources
 * owned by a vendor — products, inventory items, orders, etc.
 *
 * Rules:
 *   - Employee  → uses employees.owner_id  (the vendor who employs them)
 *   - Vendor    → uses users.id            (their own ID)
 */
trait ResolvesOwner
{
    /**
     * Return the vendor owner ID for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return int
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function resolveOwnerId(Request $request): int
    {
        $user = $request->user();

        if (!$user) {
            abort(401, 'Unauthenticated.');
        }

        // Employees have an `owner_id` column pointing to their vendor.
        // Vendors (users with role=vendor) do NOT have this column, so
        // owner_id will be null — fall back to their own id.
        if (!empty($user->owner_id)) {
            return (int) $user->owner_id;
        }

        return (int) $user->id;
    }

    /**
     * Convenience: return true if the current user is an employee.
     */
    protected function isEmployee(Request $request): bool
    {
        return !empty($request->user()?->owner_id);
    }
}