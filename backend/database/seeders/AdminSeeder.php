<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Matthew',
                'surname' => 'Javier',
                'email' => 'admin@admin.local',
                'contact_number' => '09491234567',
                'password' => Hash::make('superadmin1031'),
                'is_verified' => true,
                'role' => User::ROLE_ADMIN,
            ]
        );
    }
}
