<?php

namespace App\Traits;

use App\Models\Employee;

trait ScopesOwner
{
    /**
     * Resolve the CEO/Vendor ID (owner_id) from the authenticated user.
     * Works for both Vendor users and Employee users.
     *
     * @return int
     */
    protected function getOwnerId(): int
    {
        $user = auth()->user();
        
        if ($user instanceof Employee) {
            return $user->owner_id;
        }
        
        return $user->id;
    }
}
