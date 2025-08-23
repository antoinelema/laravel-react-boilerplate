<?php

namespace Tests\Feature;

use App\__Infrastructure__\Persistence\Eloquent\User;
use App\__Infrastructure__\Services\User\SearchQuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSearchLimitTest extends TestCase
{
    use RefreshDatabase;

    private SearchQuotaService $searchQuotaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->searchQuotaService = new SearchQuotaService();
    }

    public function test_admin_can_always_search()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $canSearch = $this->searchQuotaService->canUserSearch($admin);

        $this->assertTrue($canSearch);
    }

    public function test_admin_has_unlimited_searches()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $remaining = $this->searchQuotaService->getRemainingSearches($admin);

        $this->assertEquals(-1, $remaining); // -1 indique illimité
    }

    public function test_admin_quota_info_shows_unlimited()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $quotaInfo = $this->searchQuotaService->getQuotaInfo($admin);

        $this->assertTrue($quotaInfo['is_admin']);
        $this->assertTrue($quotaInfo['unlimited']);
        $this->assertEquals(-1, $quotaInfo['remaining']);
        $this->assertEquals(-1, $quotaInfo['limit']);
    }

    public function test_admin_consume_search_quota_always_succeeds()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $result = $this->searchQuotaService->consumeSearchQuota($admin);

        $this->assertTrue($result);
        
        // Vérifier que le compteur n'a pas été incrémenté pour les admins
        $admin->refresh();
        $this->assertEquals(0, $admin->daily_searches_count);
    }

    public function test_admin_can_search_via_middleware()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $this->actingAs($admin, 'sanctum');

        $response = $this->postJson('/api/v1/prospects/search', [
            'company_name' => 'Test Company',
            'location' => 'Paris',
            'sources' => ['societe_com']
        ]);

        // Le test peut échouer sur d'autres aspects (API keys manquantes, etc.)
        // mais il ne devrait PAS échouer sur la limitation de quota (429)
        $this->assertNotEquals(429, $response->getStatusCode());
    }

    public function test_admin_with_many_searches_can_still_search()
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'daily_searches_count' => 100 // Bien au-dessus de la limite normale
        ]);

        $canSearch = $this->searchQuotaService->canUserSearch($admin);
        $result = $this->searchQuotaService->consumeSearchQuota($admin);

        $this->assertTrue($canSearch);
        $this->assertTrue($result);
    }

    public function test_regular_user_vs_admin_quota_behavior()
    {
        $regularUser = User::factory()->create([
            'role' => 'user',
            'subscription_type' => 'free',
            'daily_searches_count' => 5, // À la limite
            'daily_searches_reset_at' => now() // Éviter le reset automatique
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'daily_searches_count' => 5, // Même nombre de recherches
            'daily_searches_reset_at' => now()
        ]);

        $regularUserCanSearch = $this->searchQuotaService->canUserSearch($regularUser);
        $adminCanSearch = $this->searchQuotaService->canUserSearch($admin);

        $this->assertFalse($regularUserCanSearch); // L'utilisateur normal ne peut pas
        $this->assertTrue($adminCanSearch); // L'admin peut toujours
    }
}