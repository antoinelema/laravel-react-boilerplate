<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent;
use Tests\Concerns\ResetsTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserApiAuthTest extends TestCase
{
    use ResetsTransactions;

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
            'firstname' => 'Name'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profil mis à jour'
        ]);

        // Vérifier que les données ont été mises à jour (mais pas le mot de passe)
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

    public function test_password_change_requires_current_password()
    {
        $user = UserEloquent::create([
            'name' => 'Password',
            'firstname' => 'User',
            'email' => 'password@example.com',
            'password' => bcrypt('oldpassword'),
            'role' => 'user',
            'subscription_type' => 'free'
        ]);

        $this->actingAs($user);

        // Test sans current_password
        $response = $this->putJson('/profile', [
            'name' => 'Password',
            'firstname' => 'User',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_validates_current_password()
    {
        $user = UserEloquent::create([
            'name' => 'Password',
            'firstname' => 'User',
            'email' => 'password2@example.com',
            'password' => bcrypt('oldpassword'),
            'role' => 'user',
            'subscription_type' => 'free'
        ]);

        $this->actingAs($user);

        // Test avec mauvais current_password
        $response = $this->putJson('/profile', [
            'name' => 'Password',
            'firstname' => 'User',
            'current_password' => 'wrongpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['current_password']);
    }

    public function test_password_change_works_with_correct_current_password()
    {
        $user = UserEloquent::create([
            'name' => 'Password',
            'firstname' => 'User',
            'email' => 'password3@example.com',
            'password' => bcrypt('oldpassword'),
            'role' => 'user',
            'subscription_type' => 'free'
        ]);

        $this->actingAs($user);

        // Test avec bon current_password
        $response = $this->putJson('/profile', [
            'name' => 'Password',
            'firstname' => 'User',
            'current_password' => 'oldpassword',
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'message' => 'Profil mis à jour'
        ]);

        // Vérifier que le mot de passe a été changé
        $user->refresh();
        $this->assertTrue(Hash::check('newpassword123', $user->password));
    }
}