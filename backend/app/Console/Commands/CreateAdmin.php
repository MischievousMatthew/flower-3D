<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateAdmin extends Command
{
    protected $signature = 'app:create-admin';

    protected $description = 'Create an admin user';

    public function handle()
    {
        $this->info('=== Create Admin User ===');

        $username = $this->ask('Username');
        $name = $this->ask('Name');
        $surname = $this->ask('Surname');
        $contact = $this->ask('Contact number');
        $password = $this->secret('Password');

        if (User::where('username', $username)->exists()) {
            $this->error('User with this username already exists.');
            return Command::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'surname' => $surname,
            'username' => $username,
            'email' => $username . '@admin.local',
            'contact_number' => $contact,
            'password' => Hash::make($password),
            'is_verified' => true,
            'role' => User::ROLE_ADMIN,
        ]);

        $this->info('✅ Admin user created successfully!');
        $this->line('Username: ' . $user->username);

        return Command::SUCCESS;
    }
}
