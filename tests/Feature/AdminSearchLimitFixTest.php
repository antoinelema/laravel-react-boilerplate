<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent as User;
use Tests\Concerns\ResetsTransactions;
use Tests\TestCase;

class AdminSearchLimitFixTest extends TestCase
{
    use ResetsTransactions;

    public function test_admin_can_search_without_limit_via_api()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        // Simule un admin qui a déjà fait des recherches
        $admin->update(['daily_searches_count' => 10]);
        
        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'restaurant test',
            'filters' => [
                'location' => 'Paris',
                'radius' => 5000,
                'limit' => 10
            ],
            'sources' => ['demo']
        ]);

        // L'admin ne doit JAMAIS recevoir 429
        $this->assertNotEquals(429, $response->getStatusCode());
        
        if ($response->getStatusCode() === 429) {
            echo "Erreur 429 reçue pour admin !\n";
            echo "Response: " . $response->getContent() . "\n";
        }
        
        echo "Status Code pour admin: " . $response->getStatusCode() . "\n";
    }

    public function test_regular_user_gets_limited_correctly()
    {
        $user = User::factory()->create([
            'role' => 'user', 
            'subscription_type' => 'free',
            'daily_searches_count' => 5,
            'daily_searches_reset_at' => now()->addHour() // Futur pour éviter le reset
        ]);
        
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'restaurant test',
            'filters' => [
                'location' => 'Paris',
                'limit' => 10
            ],
            'sources' => ['demo']
        ]);

        // L'utilisateur normal doit recevoir 429
        $this->assertEquals(429, $response->getStatusCode());
        
        echo "Status Code pour user normal: " . $response->getStatusCode() . "\n";
    }

    public function test_direct_middleware_behavior()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $admin->update(['daily_searches_count' => 10]);
        
        $quotaService = new \App\__Infrastructure__\Services\User\SearchQuotaService();
        $canSearch = $quotaService->canUserSearch($admin);
        
        echo "Admin can search (direct service): " . ($canSearch ? 'YES' : 'NO') . "\n";
        echo "Admin role: " . $admin->role . "\n";
        echo "Admin isAdmin(): " . ($admin->isAdmin() ? 'YES' : 'NO') . "\n";
        
        $this->assertTrue($canSearch, 'Admin should be able to search');
    }
}