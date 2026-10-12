<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // What each kind of administrator may do
        Schema::create('admin_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60)->unique();
            $table->string('description', 255)->nullable();
            $table->json('permissions');
            $table->boolean('is_system')->default(false); // Super admin: can't be edited or deleted
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('admin_role_id')->nullable()->constrained('admin_roles')->nullOnDelete();
        });

        // Every change an admin makes (and every export)
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('admin_name', 120)->nullable();
            $table->string('action', 120)->index();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->string('target_id', 40)->nullable();
            $table->unsignedSmallInteger('status_code');
            $table->json('details')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Failed sign-ins (successful ones are in login_audit_logs)
        Schema::create('login_attempts', function (Blueprint $table) {
            $table->id();
            $table->string('identifier', 190)->index(); // username/email typed, lowercased
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 20)->default('user'); // user | employee
            $table->string('reason', 40); // unknown_account | wrong_password | locked | suspended | unverified
            $table->string('ip_address', 45)->nullable()->index();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        // Server errors returned by the API
        Schema::create('api_error_logs', function (Blueprint $table) {
            $table->id();
            $table->string('method', 10);
            $table->string('path', 255);
            $table->unsignedSmallInteger('status_code');
            $table->string('exception', 190)->nullable();
            $table->string('message', 500)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        $now = now();
        $roles = [
            ['Super admin', 'Full access, including administrators and security settings.', ['*'], true],
            ['Operations', 'Orders, vendors, customers and support. No finance or security.', ['dashboard.view', 'orders.view', 'orders.notes', 'vendors.view', 'vendors.manage', 'customers.view', 'customers.manage', 'products.view', 'products.moderate', 'support.view', 'support.reply'], false],
            ['Support agent', 'Answers support requests and looks up orders and customers.', ['dashboard.view', 'orders.view', 'orders.notes', 'customers.view', 'support.view', 'support.reply'], false],
            ['Content editor', 'Website content, journal, categories and occasions.', ['dashboard.view', 'content.manage', 'products.view', 'products.taxonomy'], false],
            ['Finance', 'Revenue, payments and exports. View only.', ['dashboard.view', 'revenue.view', 'finance.view', 'finance.export', 'orders.view'], false],
        ];
        foreach ($roles as [$name, $description, $permissions, $system]) {
            DB::table('admin_roles')->insert([
                'name' => $name, 'description' => $description, 'permissions' => json_encode($permissions),
                'is_system' => $system, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Everyone who is an admin today keeps full access
        $super = DB::table('admin_roles')->where('name', 'Super admin')->value('id');
        DB::table('users')->where('role', 'admin')->update(['admin_role_id' => $super]);
    }

    public function down(): void
    {
        Schema::dropIfExists('api_error_logs');
        Schema::dropIfExists('login_attempts');
        Schema::dropIfExists('admin_audit_logs');
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('admin_role_id');
        });
        Schema::dropIfExists('admin_roles');
    }
};
