<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_register_route_creates_user()
    {
        $payload = [
            'name' => 'Doe',
            'firstname' => 'John',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ];
        $response = $this->post('/register', $payload);
        $response->assertStatus(201)->assertJsonFragment([
            'name' => 'Doe',
            'firstname' => 'John',
            'email' => 'john.doe@example.com',
        ]);
        $this->assertDatabaseHas('users', [
            'name' => 'Doe',
            'firstname' => 'John',
            'email' => 'john.doe@example.com',
        ]);
    }

    public function test_profile_update_route_updates_user()
    {
        $user = UserEloquent::create([
            'name' => 'Old',
            'firstname' => 'Name',
            'email' => 'old@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);
        $payload = [
            'name' => 'New',
            'firstname' => 'Firstname',
            'password' => 'newpassword',
            'password_confirmation' => 'newpassword',
            'current_password' => 'password',
        ];
        $response = $this->putJson('/profile', $payload);
        $response->assertStatus(200)->assertJsonFragment([
            'message' => 'Profil mis à jour',
        ]);
        $this->assertDatabaseHas('users', [
            'name' => 'New',
            'firstname' => 'Firstname',
            'email' => 'old@example.com',
        ]);
    }
}
