<?php

namespace Tests\__Infrastructure__\Services;

use App\__Domain__\Data\Prospect\Model as ProspectModel;
use App\__Infrastructure__\Services\External\GoogleMapsService;
use App\__Infrastructure__\Services\External\PagesJaunesService;
use App\__Infrastructure__\Services\ProspectEnrichment\ProspectEnrichmentService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests unitaires pour le service d'enrichissement de prospects
 */
class ProspectEnrichmentServiceTest extends TestCase
{
    private PagesJaunesService|MockObject $pagesJaunesService;
    private GoogleMapsService|MockObject $googleMapsService;
    private ProspectEnrichmentService $enrichmentService;

    protected function setUp(): void
    {
        $this->pagesJaunesService = $this->createMock(PagesJaunesService::class);
        $this->googleMapsService = $this->createMock(GoogleMapsService::class);
        
        $this->enrichmentService = new ProspectEnrichmentService(
            $this->pagesJaunesService,
            $this->googleMapsService
        );
    }

    public function test_search_prospects_from_google_maps(): void
    {
        $userId = 1;
        $query = 'restaurant';
        $filters = ['city' => 'Paris'];
        $sources = ['google_maps'];

        $mockApiResults = [
            [
                'id' => 'place_123',
                'name' => 'Restaurant Le Petit',
                'city' => 'Paris',
                'phone' => '0123456789',
                'website' => 'https://lepetit.fr'
            ]
        ];

        $this->googleMapsService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        $this->googleMapsService
            ->expects($this->once())
            ->method('searchPlaces')
            ->with($query, $filters)
            ->willReturn($mockApiResults);

        $this->pagesJaunesService
            ->expects($this->never())
            ->method('search');

        $results = $this->enrichmentService->searchProspects($userId, $query, $filters, $sources);

        $this->assertCount(1, $results);
        $this->assertInstanceOf(ProspectModel::class, $results[0]);
        $this->assertEquals('Restaurant Le Petit', $results[0]->name);
        $this->assertEquals($userId, $results[0]->userId);
        $this->assertEquals('google_maps', $results[0]->source);
    }

    public function test_search_prospects_from_pages_jaunes(): void
    {
        $userId = 1;
        $query = 'boulangerie';
        $filters = [];
        $sources = ['pages_jaunes'];

        $mockApiResults = [
            [
                'id' => 'pj_456',
                'name' => 'Boulangerie Martin',
                'email' => 'contact@martin.fr'
            ]
        ];

        $this->pagesJaunesService
            ->expects($this->once())
            ->method('isConfigured')
            ->willReturn(true);

        $this->pagesJaunesService
            ->expects($this->once())
            ->method('search')
            ->with($query, $filters)
            ->willReturn($mockApiResults);

        $this->googleMapsService
            ->expects($this->never())
            ->method('searchPlaces');

        $results = $this->enrichmentService->searchProspects($userId, $query, $filters, $sources);

        $this->assertCount(1, $results);
        $this->assertEquals('Boulangerie Martin', $results[0]->name);
        $this->assertEquals('pages_jaunes', $results[0]->source);
    }

    public function test_search_prospects_from_multiple_sources(): void
    {
        $userId = 1;
        $query = 'restaurant';
        $filters = [];
        $sources = ['google_maps', 'pages_jaunes'];

        $googleResults = [
            ['id' => 'gm_123', 'name' => 'Restaurant Google', 'phone' => '0123456789']
        ];
        $pagesJaunesResults = [
            ['id' => 'pj_456', 'name' => 'Restaurant PJ', 'email' => 'contact@pj.fr']
        ];

        $this->googleMapsService
            ->method('isConfigured')
            ->willReturn(true);
        $this->googleMapsService
            ->method('searchPlaces')
            ->willReturn($googleResults);

        $this->pagesJaunesService
            ->method('isConfigured')
            ->willReturn(true);
        $this->pagesJaunesService
            ->method('search')
            ->willReturn($pagesJaunesResults);

        $results = $this->enrichmentService->searchProspects($userId, $query, $filters, $sources);

        $this->assertCount(2, $results);
        $this->assertEquals('Restaurant Google', $results[0]->name);
        $this->assertEquals('Restaurant PJ', $results[1]->name);
    }

    public function test_search_prospects_default_sources_when_empty(): void
    {
        $userId = 1;
        $query = 'test';
        $sources = []; // Empty sources should use default

        $this->googleMapsService->method('isConfigured')->willReturn(true);
        $this->googleMapsService->method('searchPlaces')->willReturn([]);
        
        $this->pagesJaunesService->method('isConfigured')->willReturn(true);
        $this->pagesJaunesService->method('search')->willReturn([]);

        // Both services should be called when sources is empty
        $this->googleMapsService->expects($this->once())->method('searchPlaces');
        $this->pagesJaunesService->expects($this->once())->method('search');

        $this->enrichmentService->searchProspects($userId, $query, [], $sources);
    }

    public function test_search_prospects_skips_unconfigured_services(): void
    {
        $userId = 1;
        $query = 'test';
        $sources = ['google_maps', 'pages_jaunes'];

        $this->googleMapsService
            ->method('isConfigured')
            ->willReturn(false); // Not configured

        $this->pagesJaunesService
            ->method('isConfigured')
            ->willReturn(true);
        $this->pagesJaunesService
            ->method('search')
            ->willReturn([]);

        // Google Maps should not be called
        $this->googleMapsService->expects($this->never())->method('searchPlaces');
        $this->pagesJaunesService->expects($this->once())->method('search');

        $results = $this->enrichmentService->searchProspects($userId, $query, [], $sources);
        $this->assertEmpty($results);
    }

    public function test_search_prospects_handles_service_exceptions(): void
    {
        $userId = 1;
        $query = 'test';
        $sources = ['google_maps', 'pages_jaunes'];

        $this->googleMapsService->method('isConfigured')->willReturn(true);
        $this->googleMapsService
            ->method('searchPlaces')
            ->willThrowException(new \Exception('Google Maps API error'));

        $this->pagesJaunesService->method('isConfigured')->willReturn(true);
        $this->pagesJaunesService
            ->method('search')
            ->willReturn([['name' => 'Test Result']]);

        // Should continue with other sources even if one fails
        $results = $this->enrichmentService->searchProspects($userId, $query, [], $sources);
        
        $this->assertCount(1, $results);
        $this->assertEquals('Test Result', $results[0]->name);
    }

    public function test_deduplicate_prospects(): void
    {
        $userId = 1;
        $query = 'restaurant';
        $sources = ['google_maps', 'pages_jaunes'];

        // Same prospect from different sources
        $googleResults = [
            ['name' => 'Restaurant Le Petit', 'city' => 'Paris', 'postal_code' => '75001']
        ];
        $pagesJaunesResults = [
            ['name' => 'Restaurant Le Petit', 'city' => 'Paris', 'postal_code' => '75001']
        ];

        $this->googleMapsService->method('isConfigured')->willReturn(true);
        $this->googleMapsService->method('searchPlaces')->willReturn($googleResults);
        
        $this->pagesJaunesService->method('isConfigured')->willReturn(true);
        $this->pagesJaunesService->method('search')->willReturn($pagesJaunesResults);

        $results = $this->enrichmentService->searchProspects($userId, $query, [], $sources);

        // Should only return one prospect (deduplicated)
        $this->assertCount(1, $results);
        $this->assertEquals('Restaurant Le Petit', $results[0]->name);
    }

    public function test_results_sorted_by_relevance_score(): void
    {
        $userId = 1;
        $query = 'restaurant';
        $sources = ['google_maps'];

        $mockResults = [
            ['name' => 'Restaurant Basic'], // Lower score (no contact info)
            ['name' => 'Restaurant Premium', 'email' => 'contact@premium.fr', 'phone' => '0123456789', 'website' => 'https://premium.fr'] // Higher score
        ];

        $this->googleMapsService->method('isConfigured')->willReturn(true);
        $this->googleMapsService->method('searchPlaces')->willReturn($mockResults);

        $results = $this->enrichmentService->searchProspects($userId, $query, [], $sources);

        $this->assertCount(2, $results);
        // Results should be sorted by relevance score (descending)
        $this->assertEquals('Restaurant Premium', $results[0]->name);
        $this->assertEquals('Restaurant Basic', $results[1]->name);
        $this->assertGreaterThan($results[1]->relevanceScore, $results[0]->relevanceScore);
    }

    public function test_get_available_sources(): void
    {
        $this->googleMapsService->method('isConfigured')->willReturn(true);
        $this->pagesJaunesService->method('isConfigured')->willReturn(false);

        $sources = $this->enrichmentService->getAvailableSources();

        $this->assertArrayHasKey('google_maps', $sources);
        $this->assertArrayHasKey('pages_jaunes', $sources);
        
        $this->assertTrue($sources['google_maps']['available']);
        $this->assertFalse($sources['pages_jaunes']['available']);
        
        $this->assertEquals('Google Maps', $sources['google_maps']['name']);
        $this->assertEquals('Pages Jaunes', $sources['pages_jaunes']['name']);
    }

    public function test_enrich_prospect_with_google_maps(): void
    {
        $prospect = new ProspectModel(
            id: 1,
            userId: 1,
            name: 'Restaurant Test',
            city: 'Paris'
        );

        $enrichmentData = [
            'name' => 'Restaurant Test',
            'phone' => '0123456789',
            'website' => 'https://restaurant-test.fr',
            'description' => 'Excellent restaurant'
        ];

        $this->googleMapsService->method('isConfigured')->willReturn(true);
        $this->googleMapsService
            ->method('searchPlaces')
            ->willReturn([$enrichmentData]);

        $enrichedProspect = $this->enrichmentService->enrichProspect($prospect);

        $this->assertEquals('0123456789', $enrichedProspect->contactInfo['phone']);
        $this->assertEquals('https://restaurant-test.fr', $enrichedProspect->contactInfo['website']);
        $this->assertEquals('Excellent restaurant', $enrichedProspect->description);
    }

    public function test_enrich_prospect_preserves_existing_data(): void
    {
        $prospect = new ProspectModel(
            id: 1,
            userId: 1,
            name: 'Restaurant Test',
            contactInfo: ['email' => 'existing@test.fr'],
            description: 'Existing description'
        );

        $enrichmentData = [
            'name' => 'Restaurant Test',
            'phone' => '0123456789',
            'description' => 'New description from API'
        ];

        $this->googleMapsService->method('isConfigured')->willReturn(true);
        $this->googleMapsService->method('searchPlaces')->willReturn([$enrichmentData]);

        $enrichedProspect = $this->enrichmentService->enrichProspect($prospect);

        // Should keep existing email
        $this->assertEquals('existing@test.fr', $enrichedProspect->contactInfo['email']);
        // Should add new phone
        $this->assertEquals('0123456789', $enrichedProspect->contactInfo['phone']);
        // Should keep existing description (not overwrite)
        $this->assertEquals('Existing description', $enrichedProspect->description);
    }
}