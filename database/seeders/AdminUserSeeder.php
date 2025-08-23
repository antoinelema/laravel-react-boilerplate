<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\__Infrastructure__\Persistence\Eloquent\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@prospecto.fr'],
            [
                'name' => 'Admin',
                'firstname' => 'System',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'subscription_type' => 'premium',
                'subscription_expires_at' => now()->addYears(10),
                'email_verified_at' => now(),
            ]
        );
    }
}
