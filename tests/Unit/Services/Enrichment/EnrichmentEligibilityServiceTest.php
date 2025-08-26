<?php

namespace Tests\Unit\Services\Enrichment;

use App\__Domain__\Data\Prospect\Model as Prospect;
use App\__Infrastructure__\Eloquent\ProspectEloquent;
use App\__Infrastructure__\Services\Enrichment\EnrichmentEligibilityService;
use Carbon\Carbon;
use Database\Factories\ProspectEloquentFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrichmentEligibilityServiceTest extends TestCase
{
    use RefreshDatabase;

    private EnrichmentEligibilityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EnrichmentEligibilityService::class);
    }

    public function test_completeness_score_calculation()
    {
        // Test avec données complètes
        $completeProspect = new Prospect(
            id: 1,
            userId: 1,
            name: 'John Doe',
            company: 'Test Company',
            sector: 'Tech',
            city: 'Test City',
            address: '123 Test St',
            contactInfo: [
                'email' => 'john@example.com',
                'phone' => '+1234567890',
                'website' => 'https://example.com'
            ],
            relevanceScore: 85
        );

        $completeScore = $this->service->calculateCompletenessScore($completeProspect);
        $this->assertGreaterThanOrEqual(80, $completeScore);

        // Test avec données partielles
        $partialProspect = new Prospect(
            id: 2,
            userId: 1,
            name: 'Jane Smith',
            company: 'Partial Company',
            city: 'Some City',
            contactInfo: [],
            relevanceScore: 85
        );

        $partialScore = $this->service->calculateCompletenessScore($partialProspect);
        $this->assertLessThan(60, $partialScore);
    }

    public function test_prospect_with_complete_contacts_is_not_eligible()
    {
        // Créer un prospect avec des données complètes en BDD
        $prospectEloquent = ProspectEloquentFactory::new()->create([
            'name' => 'Complete User',
            'company' => 'Test Company',
            'city' => 'Test City',
            'address' => '123 Test Street',
            'contact_info' => [
                'email' => 'complete@example.com',
                'phone' => '+1234567890',
                'website' => 'https://example.com'
            ],
            'data_completeness_score' => 90,
            'auto_enrich_enabled' => true,
            'enrichment_blacklisted_at' => null
        ]);

        $prospect = $prospectEloquent->toDomainModel();
        $eligibility = $this->service->isEligibleForEnrichment($prospect, [], $prospectEloquent);

        $this->assertFalse($eligibility['is_eligible']);
        $this->assertEquals('complete_data', $eligibility['reason']);
        $this->assertEquals(90, $eligibility['completeness_score']);
    }

    public function test_recently_enriched_prospect_is_not_eligible()
    {
        $prospectEloquent = ProspectEloquentFactory::new()->create([
            'name' => 'Recently Enriched',
            'company' => 'Test Company',
            'contact_info' => ['email' => 'recent@example.com'],
            'last_enrichment_at' => Carbon::now()->subDays(15),
            'data_completeness_score' => 60,
            'auto_enrich_enabled' => true,
            'enrichment_blacklisted_at' => null
        ]);

        $prospect = $prospectEloquent->toDomainModel();
        $eligibility = $this->service->isEligibleForEnrichment($prospect, [], $prospectEloquent);

        $this->assertFalse($eligibility['is_eligible']);
        $this->assertEquals('recently_enriched', $eligibility['reason']);
        $this->assertNotNull($eligibility['next_eligible_at']);
    }

    public function test_prospect_never_enriched_is_eligible()
    {
        $prospectEloquent = ProspectEloquentFactory::new()->create([
            'name' => 'Never Enriched',
            'company' => 'Test Company',
            'contact_info' => ['website' => 'https://example.com'],
            'last_enrichment_at' => null,
            'enrichment_attempts' => 0,
            'data_completeness_score' => 45,
            'auto_enrich_enabled' => true,
            'enrichment_blacklisted_at' => null
        ]);

        $prospect = $prospectEloquent->toDomainModel();
        $eligibility = $this->service->isEligibleForEnrichment($prospect, [], $prospectEloquent);

        $this->assertTrue($eligibility['is_eligible']);
        $this->assertEquals('never_enriched', $eligibility['reason']);
        $this->assertEquals('high', $eligibility['priority']);
    }

    public function test_blacklisted_prospect_is_not_eligible()
    {
        $prospectEloquent = ProspectEloquentFactory::new()->create([
            'name' => 'Blacklisted User',
            'company' => 'Test Company',
            'contact_info' => [],
            'enrichment_blacklisted_at' => Carbon::now()->subDay(),
            'auto_enrich_enabled' => true
        ]);

        $prospect = $prospectEloquent->toDomainModel();
        $eligibility = $this->service->isEligibleForEnrichment($prospect, [], $prospectEloquent);

        $this->assertFalse($eligibility['is_eligible']);
        $this->assertEquals('blacklisted', $eligibility['reason']);
    }

    public function test_get_eligible_prospects_filters_correctly()
    {
        // Créer un prospect éligible
        $eligibleProspect = ProspectEloquentFactory::new()->create([
            'name' => 'Eligible Prospect',
            'contact_info' => [],
            'last_enrichment_at' => null,
            'auto_enrich_enabled' => true,
            'enrichment_blacklisted_at' => null,
            'data_completeness_score' => 45
        ]);

        // Créer un prospect récemment enrichi (non éligible)
        ProspectEloquentFactory::new()->create([
            'name' => 'Recently Enriched',
            'contact_info' => ['email' => 'recent@example.com'],
            'last_enrichment_at' => Carbon::now()->subDays(10),
            'auto_enrich_enabled' => true,
            'enrichment_blacklisted_at' => null,
            'data_completeness_score' => 70
        ]);

        // Créer un prospect blacklisté (non éligible)
        ProspectEloquentFactory::new()->create([
            'name' => 'Blacklisted Prospect',
            'contact_info' => [],
            'last_enrichment_at' => null,
            'auto_enrich_enabled' => true,
            'enrichment_blacklisted_at' => Carbon::now()->subDay(),
            'data_completeness_score' => 30
        ]);

        $eligibleProspects = $this->service->getEligibleProspects();

        $this->assertEquals(1, $eligibleProspects->count());
        $this->assertEquals('Eligible Prospect', $eligibleProspects->first()->name);
    }

    public function test_eligibility_stats_calculation()
    {
        // Créer différents types de prospects
        ProspectEloquentFactory::new()->create([
            'contact_info' => [],
            'last_enrichment_at' => null,
            'enrichment_status' => 'never',
            'data_completeness_score' => 40,
            'auto_enrich_enabled' => true,
            'enrichment_blacklisted_at' => null
        ]); // Éligible

        ProspectEloquentFactory::new()->create([
            'contact_info' => [
                'email' => 'complete@example.com',
                'phone' => '+1234567890'
            ],
            'last_enrichment_at' => Carbon::now()->subDays(10),
            'enrichment_status' => 'completed',
            'data_completeness_score' => 90,
            'auto_enrich_enabled' => true,
            'enrichment_blacklisted_at' => null
        ]); // Complet

        ProspectEloquentFactory::new()->create([
            'contact_info' => [],
            'enrichment_blacklisted_at' => Carbon::now(),
            'enrichment_status' => 'never',
            'data_completeness_score' => 20,
            'auto_enrich_enabled' => true
        ]); // Blacklisté

        $stats = $this->service->getEligibilityStats();

        $this->assertEquals(3, $stats['total_prospects']);
        $this->assertEquals(1, $stats['eligible_for_enrichment']);
        $this->assertEquals(1, $stats['complete_data']);
        $this->assertEquals(1, $stats['blacklisted']);
        $this->assertEquals(2, $stats['never_enriched']);
    }
}