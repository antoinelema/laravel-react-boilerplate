<?php

namespace Tests\Feature;

use App\__Infrastructure__\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchLimitationTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function free_user_can_search_within_daily_limit()
    {
        config(['app.daily_search_limit' => 5]);
        
        $user = User::factory()->create([
            'subscription_type' => 'free'
        ]);
        
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Première recherche - devrait fonctionner
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/prospects/search', [
            'query' => 'restaurant Paris',
            'sources' => ['google_maps'],
            'limit' => 3
        ]);
        
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'prospects',
                'quota_info' => [
                    'is_premium',
                    'remaining',
                    'limit',
                    'used'
                ]
            ]
        ]);
        
        // Vérifier que le quota a été consommé
        $this->assertEquals(1, $user->fresh()->daily_searches_count);
    }

    /** @test */
    public function free_user_is_blocked_after_daily_limit()
    {
        config(['app.daily_search_limit' => 2]);
        
        $user = User::factory()->create([
            'subscription_type' => 'free',
            'daily_searches_count' => 2, // Déjà à la limite
            'daily_searches_reset_at' => now()
        ]);
        
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/prospects/search', [
            'query' => 'restaurant Paris',
            'sources' => ['google_maps'],
            'limit' => 3
        ]);
        
        $response->assertStatus(429); // Too Many Requests
        $response->assertJson([
            'success' => false,
            'error_code' => 'SEARCH_LIMIT_EXCEEDED'
        ]);
    }

    /** @test */
    public function premium_user_has_unlimited_searches()
    {
        $user = User::factory()->create();
        $user->upgradeToPremium(now()->addMonth());
        
        $token = $user->createToken('test-token')->plainTextToken;
        
        // Faire plusieurs recherches
        for ($i = 0; $i < 10; $i++) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->postJson('/api/v1/prospects/search', [
                'query' => 'restaurant Paris ' . $i,
                'sources' => ['google_maps'],
                'limit' => 1
            ]);
            
            $response->assertStatus(200);
        }
        
        // Vérifier que le compteur n'a pas bougé
        $this->assertEquals(0, $user->fresh()->daily_searches_count);
    }

    /** @test */
    public function quota_endpoint_returns_correct_info_for_free_user()
    {
        config(['app.daily_search_limit' => 5]);
        
        $user = User::factory()->create([
            'subscription_type' => 'free',
            'daily_searches_count' => 2,
            'daily_searches_reset_at' => now()
        ]);
        
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/prospects/quota');
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'quota_info' => [
                    'is_premium' => false,
                    'unlimited' => false,
                    'remaining' => 3,
                    'limit' => 5,
                    'used' => 2,
                    'can_search' => true
                ]
            ]
        ]);
    }

    /** @test */
    public function quota_endpoint_returns_correct_info_for_premium_user()
    {
        $user = User::factory()->create();
        $user->upgradeToPremium(now()->addMonth());
        
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/prospects/quota');
        
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'data' => [
                'quota_info' => [
                    'is_premium' => true,
                    'unlimited' => true,
                    'remaining' => -1
                ]
            ]
        ]);
    }

    /** @test */
    public function free_user_cannot_access_prospects_list()
    {
        $user = User::factory()->create(['subscription_type' => 'free']);
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/prospects');
        
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'PREMIUM_REQUIRED'
        ]);
    }

    /** @test */
    public function premium_user_can_access_prospects_list()
    {
        $user = User::factory()->create();
        $user->upgradeToPremium(now()->addMonth());
        
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->getJson('/api/v1/prospects');
        
        $response->assertStatus(200);
    }

    /** @test */
    public function free_user_cannot_create_notes()
    {
        $user = User::factory()->create(['subscription_type' => 'free']);
        $token = $user->createToken('test-token')->plainTextToken;
        
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ])->postJson('/api/v1/prospects/1/notes', [
            'content' => 'Test note',
            'type' => 'note'
        ]);
        
        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error_code' => 'PREMIUM_REQUIRED'
        ]);
    }
}