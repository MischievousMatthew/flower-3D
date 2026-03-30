<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // -----------------------------
        // Admin User
        // -----------------------------
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Matthew',
                'surname' => 'Javier',
                'username' => 'admin',
                'email' => 'admin@admin.local',
                'contact_number' => '09491234567',
                'password' => Hash::make('superadmin1031'),
                'is_verified' => true,
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'plan' => 'Free Plan',
                'status' => false,
            ]
        );

        // -----------------------------
        // Test Customer User
        // -----------------------------
        User::updateOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test',
                'surname' => 'User',
                'username' => 'testuser',
                'email' => 'test@example.com',
                'contact_number' => '09123456789',
                'password' => Hash::make('password'),
                'is_verified' => true,
                'role' => 'customer',
                'email_verified_at' => now(),
                'remember_token' => Str::random(10),
                'plan' => 'Free Plan',
                'status' => false,
            ]
        );
    }
}