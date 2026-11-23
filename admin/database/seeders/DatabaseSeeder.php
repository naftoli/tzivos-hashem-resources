<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $accounts = [
            ['email' => 'admin@example.com', 'name' => 'Admin', 'role' => 'hq_admin'],
            ['email' => 'hqstaff@example.com', 'name' => 'Test HQ Staff', 'role' => 'hq_staff'],
            ['email' => 'base@example.com', 'name' => 'Test Base', 'role' => 'base'],
            ['email' => 'teacher@example.com', 'name' => 'Test Teacher', 'role' => 'teacher'],
            ['email' => 'newssite@example.com', 'name' => 'Test News Site', 'role' => 'news'],
        ];

        foreach ($accounts as $account) {
            User::updateOrCreate(
                ['email' => $account['email']],
                ['name' => $account['name'], 'password' => Hash::make('password'), 'role' => $account['role']],
            );
        }
    }
}
