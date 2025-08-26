<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent as User;
use Tests\Concerns\ResetsTransactions;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use ResetsTransactions;

    /** @test */
    public function admin_can_access_dashboard()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'subscription_type' => 'premium'
        ]);

        $response = $this->actingAs($admin)->get('/admin');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Dashboard'));
    }

    /** @test */
    public function regular_user_cannot_access_admin_dashboard()
    {
        $user = User::factory()->create(['role' => 'user']);

        $response = $this->actingAs($user)->get('/admin');
        
        $response->assertStatus(403);
    }

    /** @test */
    public function unauthenticated_user_cannot_access_admin_dashboard()
    {
        $response = $this->get('/admin');
        
        $response->assertRedirect('/login');
    }

    /** @test */
    public function admin_can_access_users_page()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'subscription_type' => 'premium'
        ]);

        $response = $this->actingAs($admin)->get('/admin/users');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Admin/Users'));
    }

    /** @test */
    public function admin_can_access_stats_api()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $token = $admin->createToken('admin-test')->plainTextToken;

        // Créer quelques utilisateurs de test
        User::factory()->count(5)->create(['subscription_type' => 'free']);
        User::factory()->count(3)->create([
            'subscription_type' => 'premium',
            'subscription_expires_at' => now()->addMonth()
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/stats');

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'users' => [
                    'total',
                    'premium',
                    'free',
                    'admin',
                    'new_today',
                    'new_week',
                    'new_month'
                ],
                'searches' => [
                    'total_today',
                    'average_per_user',
                    'users_at_limit'
                ],
                'subscriptions' => [
                    'active_premium',
                    'expired',
                    'expiring_soon'
                ]
            ]
        ]);

        $data = $response->json();
        
        // Vérifier que les données de base sont présentes
        $this->assertEquals(9, $data['data']['users']['total']); // 5 free + 3 premium + 1 admin
        $this->assertEquals(3, $data['data']['users']['premium']);
        $this->assertEquals(1, $data['data']['users']['admin']);
    }

    /** @test */
    public function regular_user_cannot_access_admin_stats_api()
    {
        $user = User::factory()->create(['role' => 'user']);
        $token = $user->createToken('user-test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/admin/stats');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'ADMIN_REQUIRED'
        ]);
    }

    /** @test */
    public function admin_can_upgrade_user_to_premium()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'subscription_type' => 'free',
            'role' => 'user'
        ]);
        
        $token = $admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/users/{$user->id}/upgrade", [
            'duration_months' => 1
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);

        // Vérifier que l'utilisateur a été mis à niveau
        $user->refresh();
        $this->assertTrue($user->isPremium());
        $this->assertEquals('premium', $user->subscription_type);
        $this->assertNotNull($user->subscription_expires_at);
    }

    /** @test */
    public function admin_can_downgrade_user_to_free()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'subscription_type' => 'premium',
            'subscription_expires_at' => now()->addMonth(),
            'role' => 'user'
        ]);
        
        $token = $admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/users/{$user->id}/downgrade");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);

        // Vérifier que l'utilisateur a été rétrogradé
        $user->refresh();
        $this->assertFalse($user->isPremium());
        $this->assertEquals('free', $user->subscription_type);
        $this->assertNull($user->subscription_expires_at);
    }

    /** @test */
    public function admin_can_reset_user_search_quota()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'subscription_type' => 'free',
            'daily_searches_count' => 5,
            'daily_searches_reset_at' => now()->subDay(),
            'role' => 'user'
        ]);
        
        $token = $admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/users/{$user->id}/reset-quota");

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true
        ]);

        // Vérifier que le quota a été réinitialisé
        $user->refresh();
        $this->assertEquals(0, $user->daily_searches_count);
        $this->assertTrue($user->daily_searches_reset_at->isSameDay(now()));
    }

    /** @test */
    public function admin_can_get_user_details()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $user = User::factory()->create([
            'subscription_type' => 'premium',
            'subscription_expires_at' => now()->addMonth(),
            'role' => 'user'
        ]);
        
        $token = $admin->createToken('admin-test')->plainTextToken;

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson("/api/v1/admin/users/{$user->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'user' => [
                    'id',
                    'name',
                    'firstname',
                    'email',
                    'role',
                    'subscription_type',
                    'is_premium',
                    'daily_searches_count',
                    'remaining_searches'
                ],
                'subscriptions',
                'prospects_count'
            ]
        ]);

        $response->assertJson([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                    'role' => 'user',
                    'subscription_type' => 'premium',
                    'is_premium' => true
                ]
            ]
        ]);
    }

    /** @test */
    public function regular_user_cannot_perform_admin_actions()
    {
        $user1 = User::factory()->create(['role' => 'user']);
        $user2 = User::factory()->create(['role' => 'user']);
        
        $token = $user1->createToken('user-test')->plainTextToken;

        // Test upgrade
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson("/api/v1/admin/users/{$user2->id}/upgrade", [
            'duration_months' => 1
        ]);

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'ADMIN_REQUIRED'
        ]);
    }

    /** @test */
    public function admin_dashboard_displays_correct_stats()
    {
        // Créer des données de test
        User::factory()->count(5)->create(['subscription_type' => 'free', 'role' => 'user']);
        User::factory()->count(3)->create([
            'subscription_type' => 'premium',
            'subscription_expires_at' => now()->addMonth(),
            'role' => 'user'
        ]);
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/admin');
        
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => 
            $page->component('Admin/Dashboard')
                 ->has('stats')
                 ->where('stats.total_users', 9) // 5 free + 3 premium + 1 admin
                 ->where('stats.premium_users', 3)
                 ->where('stats.admin_users', 1)
        );
    }
}
