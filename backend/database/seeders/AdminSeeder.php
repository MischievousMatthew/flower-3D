<?php

namespace Database\Seeders;

use App\Models\AdminRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Creates the first super admin only when the platform has no admin.
     *
     * This runs on every deploy, so it must never touch an existing account:
     * it used to reset the admin's password to a value written in this file.
     * The first password comes from ADMIN_INITIAL_PASSWORD (or a random one
     * that is written to the log once) and should be changed after signing in.
     */
    public function run(): void
    {
        if (User::where('role', User::ROLE_ADMIN)->exists()) {
            return;
        }

        $password = env('ADMIN_INITIAL_PASSWORD') ?: Str::password(16);
        $superAdminRole = Schema::hasTable('admin_roles') ? AdminRole::where('is_system', true)->value('id') : null;

        $admin = User::create([
            'name' => 'Super',
            'surname' => 'Admin',
            'username' => env('ADMIN_INITIAL_USERNAME', 'admin'),
            'email' => env('ADMIN_INITIAL_EMAIL', 'admin@admin.local'),
            'password' => Hash::make($password),
            'is_verified' => true,
            'role' => User::ROLE_ADMIN,
        ]);
        // The first admin is the owner: the only one who can edit or remove administrators
        $admin->forceFill(['admin_role_id' => $superAdminRole] + (Schema::hasColumn('users', 'is_admin_owner') ? ['is_admin_owner' => true] : []))->save();

        if (!env('ADMIN_INITIAL_PASSWORD')) {
            Log::warning("First super admin created: username '{$admin->username}', temporary password '{$password}'. Change it after signing in.");
        }
    }
}
