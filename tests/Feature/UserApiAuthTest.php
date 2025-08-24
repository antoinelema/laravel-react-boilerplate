<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserApiAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_endpoint_requires_authentication()
    {
        // Test sans authentification - retourne 401 pour les requêtes JSON
        $response = $this->getJson('/user');
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_access_user_endpoint()
    {
        $user = UserEloquent::create([
            'name' => 'Test',
            'firstname' => 'User', 
            'email' => 'test@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'subscription_type' => 'free'
        ]);

        $this->actingAs($user);

        $response = $this->getJson('/user');
        $response->assertStatus(200);
        $response->assertJson([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'firstname' => $user->firstname
        ]);
    }

    public function test_profile_endpoint_requires_authentication()
    {
        // Test GET profile sans authentification
        $response = $this->get('/profile');
        $response->assertRedirect('/login');

        // Test PUT profile sans authentification - retourne 401 pour JSON  
        $response = $this->putJson('/profile', [
            'name' => 'Test',
            'firstname' => 'User'
        ]);
        $response->assertStatus(401);
    }

    public function test_authenticated_user_can_update_profile()
    {
        $user = UserEloquent::create([
            'name' => 'Old',
            'firstname' => 'Name',
            'email' => 'old@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'subscription_type' => 'free'
        ]);

        $this->actingAs($user);

        $response = $this->putJson('/profile', [
            'name' => 'New',
            'firstname' => 'Name',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profil mis à jour'
        ]);

        // Vérifier que les données ont été mises à jour
        $user->refresh();
        $this->assertEquals('New', $user->name);
        $this->assertEquals('Name', $user->firstname);
    }

    public function test_session_authentication_works_with_credentials()
    {
        $user = UserEloquent::create([
            'name' => 'Session',
            'firstname' => 'User',
            'email' => 'session@example.com', 
            'password' => bcrypt('password'),
            'role' => 'user',
            'subscription_type' => 'free'
        ]);

        // Simuler une authentification via session web
        $this->actingAs($user, 'web');

        // Tester que les endpoints fonctionnent avec l'authentification par session
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest'
        ])->get('/user');

        $response->assertStatus(200);
        $response->assertJson([
            'email' => 'session@example.com'
        ]);
    }
}