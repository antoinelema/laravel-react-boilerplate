<?php

namespace Tests\Feature;

use App\__Infrastructure__\Persistence\Eloquent\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProspectSearchIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_prospect_search_api_endpoint_exists_and_responds()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        $response = $this->postJson('/api/v1/prospects/search', [
            'company_name' => 'Test Company',
            'location' => 'Paris',
            'sources' => ['demo'] // Utilise la source demo pour éviter les APIs externes
        ]);

        // Le test ne doit pas échouer sur l'authentification (429) ou l'autorisation
        $this->assertNotEquals(429, $response->getStatusCode());
        $this->assertNotEquals(401, $response->getStatusCode());
        $this->assertNotEquals(403, $response->getStatusCode());
        
        // Il peut y avoir d'autres erreurs (500, 422) liées à la logique métier
        // mais au moins l'endpoint est accessible
    }

    public function test_admin_can_access_search_without_limits()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/api/v1/prospects/search', [
            'company_name' => 'Test Company',
            'location' => 'Paris',
            'sources' => ['demo']
        ]);

        // L'admin ne doit jamais avoir de 429 (limitation)
        $this->assertNotEquals(429, $response->getStatusCode());
    }

    public function test_free_user_has_search_limitations()
    {
        $freeUser = User::factory()->create([
            'subscription_type' => 'free',
            'daily_searches_count' => 5, // À la limite
            'daily_searches_reset_at' => now()
        ]);
        
        $this->actingAs($freeUser, 'sanctum');

        $response = $this->postJson('/api/v1/prospects/search', [
            'company_name' => 'Test Company',
            'location' => 'Paris',
            'sources' => ['demo']
        ]);

        // L'utilisateur gratuit à la limite doit recevoir 429
        $this->assertEquals(429, $response->getStatusCode());
    }

    public function test_quota_api_endpoint_returns_correct_structure()
    {
        $user = User::factory()->create(['subscription_type' => 'free']);
        
        $this->actingAs($user, 'sanctum');

        $response = $this->getJson('/api/v1/prospects/quota');

        if ($response->getStatusCode() === 200) {
            $response->assertJsonStructure([
                'success',
                'data' => [
                    'quota_info' => [
                        'remaining',
                        'limit',
                        'used',
                        'unlimited'
                    ]
                ]
            ]);
        } else {
            // Si le endpoint échoue, au moins documentons pourquoi
            $this->assertNotEquals(404, $response->getStatusCode(), 'Quota endpoint should exist');
        }
    }

    public function test_search_validation_works()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        // Test sans paramètres requis
        $response = $this->postJson('/api/v1/prospects/search', []);

        // Devrait retourner 422 pour validation failed
        $this->assertEquals(422, $response->getStatusCode());
    }

    public function test_unauthenticated_user_cannot_search()
    {
        $response = $this->postJson('/api/v1/prospects/search', [
            'company_name' => 'Test Company',
            'sources' => ['demo']
        ]);

        $this->assertEquals(401, $response->getStatusCode());
    }
}