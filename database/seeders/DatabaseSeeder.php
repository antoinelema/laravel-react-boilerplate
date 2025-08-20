<?php

namespace Database\Seeders;

use App\__Infrastructure__\Eloquent\UserEloquent;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // UserEloquent::factory(10)->create();

        UserEloquent::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }
}
