<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use Tests\Concerns\ResetsTransactions;
use App\__Infrastructure__\Services\ProspectEnrichment\ProspectEnrichmentService;
use App\__Infrastructure__\Services\WebEnrichmentService;
use App\__Infrastructure__\Services\External\GoogleMapsService;
use App\__Infrastructure__\Services\Enrichment\EnrichmentEligibilityService;
use App\__Domain__\Data\Enrichment\WebScrapingResult;
use App\__Domain__\Data\Enrichment\ContactData;
use App\__Domain__\Data\Enrichment\ValidationResult;
use App\__Infrastructure__\Eloquent\ProspectEloquent;
use Illuminate\Support\Facades\DB;
use Mockery;

class ProspectEnrichmentUpdateTest extends TestCase
{
    use ResetsTransactions;

    private ProspectEnrichmentService $enrichmentService;
    private $webEnrichmentServiceMock;
    private $googleMapsServiceMock;
    private $eligibilityServiceMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Créer les mocks
        $this->webEnrichmentServiceMock = Mockery::mock(WebEnrichmentService::class);
        $this->googleMapsServiceMock = Mockery::mock(GoogleMapsService::class);
        $this->eligibilityServiceMock = Mockery::mock(EnrichmentEligibilityService::class);

        // Créer le service avec les mocks
        $this->enrichmentService = new ProspectEnrichmentService(
            $this->googleMapsServiceMock,
            $this->webEnrichmentServiceMock,
            $this->eligibilityServiceMock
        );
    }

    /** @test */
    public function it_updates_prospect_main_fields_after_successful_enrichment()
    {
        // Créer un prospect de test sans informations de contact  
        $prospect = ProspectEloquent::factory()->create([
            'name' => 'Restaurant Test',
            'company' => 'Ma Pizzeria',
            'contact_info' => [],
        ]);

        // Mocker le service d'éligibilité
        $this->eligibilityServiceMock
            ->shouldReceive('isEligibleForEnrichment')
            ->once()
            ->andReturn(['is_eligible' => true]);

        $this->eligibilityServiceMock
            ->shouldReceive('updateCompletenessScore')
            ->once()
            ->with($prospect->id)
            ->andReturn(85);

        // Créer des contacts d'enrichissement simulés
        $mockContacts = [
            ContactData::email(
                email: 'contact@ma-pizzeria.fr',
                validationScore: 85,
                confidenceLevel: 'high',
                context: ['source_url' => 'https://ma-pizzeria.fr/contact'],
                validationDetails: []
            ),
            ContactData::phone(
                phone: '+33142345678',
                validationScore: 90,
                confidenceLevel: 'high',
                context: ['source_url' => 'https://ma-pizzeria.fr/contact'],
                validationDetails: []
            ),
            ContactData::website(
                url: 'https://ma-pizzeria.fr',
                validationScore: 95,
                confidenceLevel: 'high',
                context: ['source_url' => 'https://ma-pizzeria.fr'],
                validationDetails: []
            ),
        ];

        $mockValidation = new ValidationResult(
            isValid: true,
            overallScore: 88.0,
            ruleScores: [],
            validationMessages: []
        );

        $mockWebResult = WebScrapingResult::success(
            prospectName: 'Restaurant Test',
            prospectCompany: 'Ma Pizzeria',
            source: 'web_enrichment',
            contacts: $mockContacts,
            validation: $mockValidation,
            metadata: [],
            executionTimeMs: 1500
        );

        // Mocker le service d'enrichissement web
        $this->webEnrichmentServiceMock
            ->shouldReceive('enrichProspectContacts')
            ->once()
            ->andReturn($mockWebResult);

        // Exécuter l'enrichissement
        $result = $this->enrichmentService->enrichProspectWebContacts(
            $prospect->toDomainModel(),
            [
                'force' => false,
                'max_contacts' => 10,
                'user_id' => 1,
                'triggered_by' => 'test'
            ]
        );

        // Vérifier que l'enrichissement a réussi
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('contacts', $result);

        // Recharger le prospect depuis la base de données
        $prospect->refresh();

        // Vérifier que les champs principaux ont été mis à jour dans contact_info
        $contactInfo = $prospect->contact_info;
        $this->assertEquals('contact@ma-pizzeria.fr', $contactInfo['email']);
        $this->assertEquals('+33142345678', $contactInfo['phone']);
        $this->assertEquals('https://ma-pizzeria.fr', $contactInfo['website']);
        
        // Vérifier que les métadonnées d'enrichissement ont été mises à jour
        $this->assertEquals('completed', $prospect->enrichment_status);
        $this->assertNotNull($prospect->last_enrichment_at);
        $this->assertEquals(88.0, $prospect->enrichment_score);
        $this->assertEquals(85, $prospect->data_completeness_score);
        
        // Vérifier que les données détaillées d'enrichissement sont sauvegardées
        $this->assertNotNull($prospect->enrichment_data);
        $enrichmentData = json_decode($prospect->enrichment_data, true);
        $this->assertArrayHasKey('emails', $enrichmentData);
        $this->assertArrayHasKey('phones', $enrichmentData);
        $this->assertArrayHasKey('websites', $enrichmentData);
    }

    /** @test */
    public function it_preserves_existing_high_quality_data()
    {
        // Créer un prospect avec déjà un email de qualité
        $prospect = ProspectEloquent::factory()->create([
            'name' => 'Restaurant Test',
            'company' => 'Ma Pizzeria',
            'contact_info' => [
                'email' => 'direction@ma-pizzeria.fr' // Email existant de qualité
            ],
        ]);

        $this->eligibilityServiceMock
            ->shouldReceive('isEligibleForEnrichment')
            ->once()
            ->andReturn(['is_eligible' => true]);

        $this->eligibilityServiceMock
            ->shouldReceive('updateCompletenessScore')
            ->once()
            ->andReturn(75);

        // Créer un contact d'enrichissement avec un score plus faible
        $mockContacts = [
            ContactData::email(
                email: 'info@ma-pizzeria.fr', // Score plus faible que l'existant
                validationScore: 65, // Score inférieur au seuil de 70
                confidenceLevel: 'medium',
                context: ['source_url' => 'https://example.com'],
                validationDetails: []
            ),
        ];

        $mockValidation = new ValidationResult(
            isValid: true,
            overallScore: 65.0,
            ruleScores: [],
            validationMessages: []
        );

        $mockWebResult = WebScrapingResult::success(
            prospectName: 'Restaurant Test',
            prospectCompany: 'Ma Pizzeria', 
            source: 'web_enrichment',
            contacts: $mockContacts,
            validation: $mockValidation,
            metadata: [],
            executionTimeMs: 1000
        );

        $this->webEnrichmentServiceMock
            ->shouldReceive('enrichProspectContacts')
            ->once()
            ->andReturn($mockWebResult);

        // Exécuter l'enrichissement
        $this->enrichmentService->enrichProspectWebContacts(
            $prospect->toDomainModel(),
            [
                'force' => false,
                'max_contacts' => 10,
                'user_id' => 1,
                'triggered_by' => 'test'
            ]
        );

        // Recharger le prospect
        $prospect->refresh();

        // Vérifier que l'email existant n'a PAS été remplacé
        $this->assertEquals('direction@ma-pizzeria.fr', $prospect->contact_info['email']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}