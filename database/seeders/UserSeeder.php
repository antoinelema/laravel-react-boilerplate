<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\__Infrastructure__\Persistence\Eloquent\User;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer l'utilisateur de test demandé
        User::firstOrCreate(
            ['email' => 'test@test.com'],
            [
                'name' => 'Test',
                'firstname' => 'User',
                'email' => 'test@test.com',
                'password' => Hash::make('test'),
                'email_verified_at' => now(),
            ]
        );

        // Créer quelques autres utilisateurs de démonstration
        User::factory(3)->create();
    }
}