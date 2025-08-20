<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAccessTest extends TestCase
{
    use RefreshDatabase;


    public function test_guest_cannot_access_protected_route()
    {
        $response = $this->get('/profile');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_protected_route()
    {
        $user = UserEloquent::create([
            'name' => 'Test User',
            'firstname' => 'Test',
            'email' => uniqid('user').'@example.com',
            'password' => bcrypt('password'),
        ]);
        $this->actingAs($user);
        $response = $this->get('/profile', ['Accept' => 'application/json']);
        $response->assertOk();
    }
}
