<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\__Infrastructure__\Services\WebEnrichmentService;
use App\__Infrastructure__\Services\External\DuckDuckGoService;
use App\__Infrastructure__\Services\External\GoogleSearchService;
use App\__Infrastructure__\Services\External\UniversalScraperService;
use App\__Infrastructure__\Services\Validation\RuleBasedValidationStrategy;
use App\__Domain__\Data\Enrichment\WebScrapingResult;
use App\__Domain__\Data\Enrichment\ContactData;
use App\__Domain__\Data\Enrichment\ValidationResult;
use Mockery;

class WebEnrichmentServiceTest extends TestCase
{
    private WebEnrichmentService $service;
    private $duckDuckGoServiceMock;
    private $googleSearchServiceMock;
    private $universalScraperServiceMock;
    private $validationStrategyMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->duckDuckGoServiceMock = Mockery::mock(DuckDuckGoService::class);
        $this->googleSearchServiceMock = Mockery::mock(GoogleSearchService::class);
        $this->universalScraperServiceMock = Mockery::mock(UniversalScraperService::class);
        $this->validationStrategyMock = Mockery::mock(RuleBasedValidationStrategy::class);

        $this->service = new WebEnrichmentService(
            $this->duckDuckGoServiceMock,
            $this->googleSearchServiceMock,
            $this->universalScraperServiceMock,
            $this->validationStrategyMock
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function testEnrichProspectContactsWithDuckDuckGoOnly(): void
    {
        // Données de test
        $prospectName = 'John Doe';
        $prospectCompany = 'Test Company';
        $options = [];

        // Contacts de test
        $testContacts = [
            ContactData::email(
                email: 'john@testcompany.com',
                validationScore: 85.0,
                confidenceLevel: 'high'
            )
        ];

        $validationResult = ValidationResult::valid(80.0, ['contact_quality' => 85.0]);

        // Configuration des mocks
        $this->duckDuckGoServiceMock
            ->shouldReceive('isConfigured')
            ->andReturn(true);

        $this->duckDuckGoServiceMock
            ->shouldReceive('searchProspectContacts')
            ->with($prospectName, $prospectCompany, $options)
            ->andReturn(WebScrapingResult::success(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'duckduckgo',
                contacts: $testContacts,
                validation: $validationResult,
                executionTimeMs: 1000.0
            ));

        $this->googleSearchServiceMock
            ->shouldReceive('isConfigured')
            ->andReturn(false);

        $this->universalScraperServiceMock
            ->shouldReceive('isConfigured')
            ->andReturn(true);

        $this->validationStrategyMock
            ->shouldReceive('validateContacts')
            ->andReturn($validationResult);

        // Exécution du test
        $result = $this->service->enrichProspectContacts($prospectName, $prospectCompany, $options);

        // Assertions
        $this->assertTrue($result->success);
        $this->assertEquals($prospectName, $result->prospectName);
        $this->assertEquals($prospectCompany, $result->prospectCompany);
        $this->assertEquals('web_enrichment_combined', $result->source);
        $this->assertCount(1, $result->contacts);
        $this->assertEquals('john@testcompany.com', $result->contacts[0]->value);
        $this->assertTrue($result->hasValidContacts());
    }

    public function testEnrichProspectContactsWithMultipleSources(): void
    {
        // Données de test
        $prospectName = 'Jane Smith';
        $prospectCompany = 'Example Corp';
        $options = ['urls_to_scrape' => ['https://example.com']];

        // Contacts de différentes sources
        $duckDuckGoContacts = [
            ContactData::email('jane@example.com', 75.0, 'medium')
        ];

        $googleContacts = [
            ContactData::email('j.smith@example.com', 90.0, 'high'),
            ContactData::phone('+33123456789', 80.0, 'high')
        ];

        $scrapingContacts = [
            ContactData::website('https://example.com', 70.0, 'medium')
        ];

        $validationResult = ValidationResult::valid(85.0, ['contact_quality' => 85.0]);

        // Configuration des mocks
        $this->setupServiceMocks(true, true, true);

        $this->duckDuckGoServiceMock
            ->shouldReceive('searchProspectContacts')
            ->andReturn(WebScrapingResult::success(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'duckduckgo',
                contacts: $duckDuckGoContacts,
                validation: $validationResult
            ));

        $this->googleSearchServiceMock
            ->shouldReceive('searchProspectContacts')
            ->andReturn(WebScrapingResult::success(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'google',
                contacts: $googleContacts,
                validation: $validationResult
            ));

        $this->universalScraperServiceMock
            ->shouldReceive('scrapeUrls')
            ->andReturn(WebScrapingResult::success(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'direct_scraping',
                contacts: $scrapingContacts,
                validation: $validationResult
            ));

        $this->validationStrategyMock
            ->shouldReceive('validateContacts')
            ->andReturn($validationResult);

        // Exécution du test
        $result = $this->service->enrichProspectContacts($prospectName, $prospectCompany, $options);

        // Assertions
        $this->assertTrue($result->success);
        $this->assertGreaterThan(1, count($result->contacts)); // Au moins 2 contacts uniques
        $this->assertTrue($result->hasValidContacts());
        $this->assertArrayHasKey('services_results', $result->metadata);
    }

    public function testEnrichProspectContactsWithDeduplication(): void
    {
        // Données de test avec doublons
        $prospectName = 'Test User';
        $prospectCompany = 'Test Corp';

        // Même email trouvé par deux sources différentes avec scores différents
        $duckDuckGoContacts = [
            ContactData::email('test@testcorp.com', 60.0, 'medium')
        ];

        $googleContacts = [
            ContactData::email('test@testcorp.com', 85.0, 'high') // Score plus élevé
        ];

        $validationResult = ValidationResult::valid(80.0, ['contact_quality' => 80.0]);

        // Configuration des mocks
        $this->setupServiceMocks(true, true, false);

        $this->duckDuckGoServiceMock
            ->shouldReceive('searchProspectContacts')
            ->andReturn(WebScrapingResult::success(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'duckduckgo',
                contacts: $duckDuckGoContacts,
                validation: $validationResult
            ));

        $this->googleSearchServiceMock
            ->shouldReceive('searchProspectContacts')
            ->andReturn(WebScrapingResult::success(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'google',
                contacts: $googleContacts,
                validation: $validationResult
            ));

        $this->validationStrategyMock
            ->shouldReceive('validateContacts')
            ->andReturn($validationResult);

        // Exécution du test
        $result = $this->service->enrichProspectContacts($prospectName, $prospectCompany);

        // Assertions
        $this->assertTrue($result->success);
        $this->assertCount(1, $result->contacts); // Dédupliqué
        $this->assertEquals(85.0, $result->contacts[0]->validationScore); // Garde le meilleur score
    }

    public function testGetAvailableServices(): void
    {
        // Configuration des mocks
        $this->setupServiceMocks(true, false, true);

        $this->duckDuckGoServiceMock
            ->shouldReceive('getServiceInfo')
            ->andReturn(['name' => 'DuckDuckGo', 'available' => true]);

        $this->googleSearchServiceMock
            ->shouldReceive('getServiceInfo')
            ->andReturn(['name' => 'Google Search', 'available' => false]);

        $this->universalScraperServiceMock
            ->shouldReceive('getServiceInfo')
            ->andReturn(['name' => 'Universal Scraper', 'available' => true]);

        $this->validationStrategyMock
            ->shouldReceive('isConfigured')
            ->andReturn(true);

        $this->validationStrategyMock
            ->shouldReceive('getServiceInfo')
            ->andReturn(['name' => 'Rule-Based Validation', 'available' => true]);

        // Exécution du test
        $services = $this->service->getAvailableServices();

        // Assertions
        $this->assertArrayHasKey('duckduckgo', $services);
        $this->assertArrayHasKey('google_search', $services);
        $this->assertArrayHasKey('universal_scraper', $services);
        $this->assertArrayHasKey('validation_strategy', $services);

        $this->assertTrue($services['duckduckgo']['enabled']);
        $this->assertTrue($services['duckduckgo']['configured']);
        $this->assertFalse($services['google_search']['configured']);
        $this->assertTrue($services['universal_scraper']['configured']);
    }

    public function testIsConfigured(): void
    {
        // Test avec au moins un service configuré
        $this->setupServiceMocks(true, false, false);
        $this->assertTrue($this->service->isConfigured());

        // Test avec aucun service configuré
        $this->setupServiceMocks(false, false, false);
        $this->assertFalse($this->service->isConfigured());
    }

    public function testEnrichProspectContactsHandlesFailures(): void
    {
        // Données de test
        $prospectName = 'Error Test';
        $prospectCompany = 'Error Corp';

        // Configuration des mocks pour simuler des erreurs
        $this->setupServiceMocks(true, false, false);

        $this->duckDuckGoServiceMock
            ->shouldReceive('searchProspectContacts')
            ->andReturn(WebScrapingResult::failure(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'duckduckgo',
                errorMessage: 'Service unavailable'
            ));

        $this->validationStrategyMock
            ->shouldReceive('validateContacts')
            ->andReturn(ValidationResult::invalid(0, ['No valid contacts']));

        // Exécution du test
        $result = $this->service->enrichProspectContacts($prospectName, $prospectCompany);

        // Assertions - Le service devrait retourner un résultat avec des contacts vides mais pas d'erreur
        $this->assertTrue($result->success);
        $this->assertEmpty($result->contacts);
    }

    private function setupServiceMocks(bool $duckDuckGoConfigured, bool $googleConfigured, bool $scraperConfigured): void
    {
        $this->duckDuckGoServiceMock
            ->shouldReceive('isConfigured')
            ->andReturn($duckDuckGoConfigured);

        $this->googleSearchServiceMock
            ->shouldReceive('isConfigured')
            ->andReturn($googleConfigured);

        $this->universalScraperServiceMock
            ->shouldReceive('isConfigured')
            ->andReturn($scraperConfigured);
    }
}