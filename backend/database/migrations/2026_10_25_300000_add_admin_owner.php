<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * One administrator is the owner: the only one who can edit or remove other
 * administrators, and whom nobody else can turn off, demote or set a password
 * for. It's the first admin account (the one the seeder creates, @admin).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'is_admin_owner')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_admin_owner')->default(false);
            });
        }

        if (DB::table('users')->where('is_admin_owner', true)->exists()) {
            return;
        }
        $admins = DB::table('users')->where('role', 'admin');
        $ownerId = (clone $admins)->where(fn ($q) => $q->where('username', env('ADMIN_INITIAL_USERNAME', 'admin'))
            ->orWhere('email', env('ADMIN_INITIAL_EMAIL', 'admin@admin.local')))->orderBy('id')->value('id')
            ?? (clone $admins)->orderBy('id')->value('id');
        if ($ownerId) {
            DB::table('users')->where('id', $ownerId)->update(['is_admin_owner' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'is_admin_owner')) {
            Schema::table('users', fn (Blueprint $table) => $table->dropColumn('is_admin_owner'));
        }
    }
};
