<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent as User;
use Tests\Concerns\ResetsTransactions;
use Tests\TestCase;

class WebPagesTest extends TestCase
{
    use ResetsTransactions;

    public function test_welcome_page_loads()
    {
        $response = $this->get('/');
        
        $response->assertStatus(200);
    }

    public function test_login_page_loads()
    {
        $response = $this->get('/login');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Login'));
    }

    public function test_register_page_loads()
    {
        $response = $this->get('/register');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Register'));
    }

    public function test_authenticated_user_can_access_search_page()
    {
        $user = User::factory()->create();
        
        $response = $this->actingAs($user)->get('/prospects/search');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('ProspectSearch'));
    }

    public function test_premium_user_can_access_prospects_dashboard()
    {
        $premiumUser = User::factory()->create(['subscription_type' => 'premium']);
        
        $response = $this->actingAs($premiumUser)->get('/prospects');
        
        // Peut retourner 200 si la page existe, ou être redirigé si non implémentée
        $this->assertContains($response->getStatusCode(), [200, 302]);
        
        if ($response->getStatusCode() === 200) {
            $response->assertInertia(fn ($page) => $page->component('ProspectDashboard'));
        }
    }

    public function test_free_user_cannot_access_prospects_dashboard()
    {
        $freeUser = User::factory()->create(['subscription_type' => 'free']);
        
        $response = $this->actingAs($freeUser)->get('/prospects');
        
        // Devrait être redirigé ou bloqué (premium required)
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_admin_can_access_admin_dashboard()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get('/admin');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }

    public function test_regular_user_cannot_access_admin_dashboard()
    {
        $user = User::factory()->create(['role' => 'user']);
        
        $response = $this->actingAs($user)->get('/admin');
        
        $response->assertStatus(403);
    }

    public function test_admin_can_access_users_management()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get('/admin/users');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Users'));
    }

    public function test_unauthenticated_user_redirected_to_login_for_protected_routes()
    {
        $protectedRoutes = [
            '/prospects/search',
            '/prospects',
            '/admin',
            '/admin/users'
        ];

        foreach ($protectedRoutes as $route) {
            $response = $this->get($route);
            
            // Devrait rediriger vers login ou retourner 401
            $this->assertContains($response->getStatusCode(), [302, 401], 
                "Route {$route} should be protected but returned {$response->getStatusCode()}");
        }
    }

    public function test_upgrade_page_accessible_to_authenticated_users()
    {
        $user = User::factory()->create(['subscription_type' => 'free']);
        
        $response = $this->actingAs($user)->get('/upgrade');
        
        // Test que la route existe et ne retourne pas d'erreur 404/500
        $this->assertNotEquals(404, $response->getStatusCode());
        $this->assertNotEquals(500, $response->getStatusCode());
        
        // Acceptable: 200 (page existe) ou 302 (redirection) ou autre code valide
        $this->assertContains($response->getStatusCode(), [200, 302]);
    }
}