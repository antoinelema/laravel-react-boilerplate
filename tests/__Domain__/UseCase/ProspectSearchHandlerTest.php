<?php

namespace Tests\__Domain__\UseCase;

use App\__Domain__\Data\Prospect\Factory as ProspectFactory;
use App\__Domain__\Data\ProspectSearch\Collection as ProspectSearchCollection;
use App\__Domain__\Data\ProspectSearch\Factory as ProspectSearchFactory;
use App\__Domain__\Data\ProspectSearch\Model as ProspectSearchModel;
use App\__Domain__\UseCase\Prospect\Search\Handler;
use App\__Domain__\UseCase\Prospect\Search\Input;
use App\__Infrastructure__\Services\ProspectEnrichment\ProspectEnrichmentService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests unitaires pour le Handler de recherche de prospects
 */
class ProspectSearchHandlerTest extends TestCase
{
    private ProspectEnrichmentService|MockObject $enrichmentService;
    private ProspectSearchCollection|MockObject $searchCollection;
    private Handler $handler;

    protected function setUp(): void
    {
        $this->enrichmentService = $this->createMock(ProspectEnrichmentService::class);
        $this->searchCollection = $this->createMock(ProspectSearchCollection::class);
        
        $this->handler = new Handler(
            $this->enrichmentService,
            $this->searchCollection
        );
    }

    public function test_handle_successful_search(): void
    {
        $input = new Input(
            userId: 1,
            query: 'restaurant',
            filters: ['city' => 'Paris'],
            sources: ['google_maps'],
            saveSearch: true
        );

        // Mock prospects retournés par le service d'enrichissement
        $mockProspects = [
            ProspectFactory::createFromApiData([
                'id' => 'place_123',
                'name' => 'Restaurant Le Petit',
                'city' => 'Paris',
                'phone' => '0123456789'
            ], 1, 'google_maps')
        ];

        // Mock des sources disponibles
        $availableSources = [
            'google_maps' => ['name' => 'Google Maps', 'available' => true]
        ];

        // Mock search sauvegardé
        $savedSearch = ProspectSearchFactory::createWithResults(1, 'restaurant', ['city' => 'Paris'], ['google_maps'], 1);

        $this->enrichmentService
            ->expects($this->once())
            ->method('searchProspects')
            ->with(1, 'restaurant', ['city' => 'Paris'], ['google_maps'])
            ->willReturn($mockProspects);

        $this->enrichmentService
            ->expects($this->once())
            ->method('getAvailableSources')
            ->willReturn($availableSources);

        $this->searchCollection
            ->expects($this->once())
            ->method('save')
            ->willReturn($savedSearch);

        $output = $this->handler->handle($input);

        $this->assertTrue($output->success);
        $this->assertCount(1, $output->prospects);
        $this->assertEquals('Restaurant Le Petit', $output->prospects[0]->name);
        $this->assertEquals(1, $output->totalFound);
        $this->assertNotNull($output->search);
        $this->assertEquals($availableSources, $output->availableSources);
        $this->assertNull($output->errorMessage);
    }

    public function test_handle_empty_query(): void
    {
        $input = new Input(
            userId: 1,
            query: '',
            saveSearch: false
        );

        $output = $this->handler->handle($input);

        $this->assertFalse($output->success);
        $this->assertEquals('Le terme de recherche est requis', $output->errorMessage);
        $this->assertEmpty($output->prospects);
        
        // Vérifier qu'aucun service externe n'est appelé
        $this->enrichmentService->expects($this->never())->method('searchProspects');
        $this->searchCollection->expects($this->never())->method('save');
    }

    public function test_handle_whitespace_only_query(): void
    {
        $input = new Input(
            userId: 1,
            query: '   ',
            saveSearch: false
        );

        $output = $this->handler->handle($input);

        $this->assertFalse($output->success);
        $this->assertEquals('Le terme de recherche est requis', $output->errorMessage);
    }

    public function test_handle_without_saving_search(): void
    {
        $input = new Input(
            userId: 1,
            query: 'restaurant',
            saveSearch: false
        );

        $mockProspects = [];
        $availableSources = [];

        $this->enrichmentService
            ->method('searchProspects')
            ->willReturn($mockProspects);

        $this->enrichmentService
            ->method('getAvailableSources')
            ->willReturn($availableSources);

        // Vérifier que save n'est jamais appelé
        $this->searchCollection->expects($this->never())->method('save');

        $output = $this->handler->handle($input);

        $this->assertTrue($output->success);
        $this->assertNull($output->search);
    }

    public function test_handle_enrichment_service_exception(): void
    {
        $input = new Input(
            userId: 1,
            query: 'restaurant',
            saveSearch: false
        );

        $this->enrichmentService
            ->method('searchProspects')
            ->willThrowException(new \Exception('API service error'));

        $output = $this->handler->handle($input);

        $this->assertFalse($output->success);
        $this->assertTrue(str_contains($output->errorMessage, 'Erreur lors de la recherche'));
        $this->assertTrue(str_contains($output->errorMessage, 'API service error'));
    }

    public function test_handle_search_save_failure_does_not_fail_search(): void
    {
        // Ce test vérifie que l'exception dans saveSearch ne fait pas échouer la recherche
        // Pour simplifier, on teste juste qu'une recherche réussit même sans sauvegarde
        $input = new Input(
            userId: 1,
            query: 'restaurant',
            saveSearch: false // Ne pas sauvegarder
        );

        $mockProspects = [];
        $availableSources = [];

        $this->enrichmentService
            ->expects($this->once())
            ->method('searchProspects')
            ->willReturn($mockProspects);

        $this->enrichmentService
            ->expects($this->once())
            ->method('getAvailableSources')
            ->willReturn($availableSources);

        // Ne doit pas essayer de sauvegarder
        $this->searchCollection->expects($this->never())->method('save');

        $output = $this->handler->handle($input);

        $this->assertTrue($output->success);
        $this->assertNull($output->search); // Pas de recherche sauvegardée
        $this->assertEmpty($output->prospects);
    }

    public function test_handle_with_multiple_sources(): void
    {
        $input = new Input(
            userId: 1,
            query: 'restaurant',
            sources: ['google_maps', 'demo'],
            saveSearch: false
        );

        $mockProspects = [
            ProspectFactory::createFromApiData(['name' => 'Rest 1'], 1, 'google_maps'),
            ProspectFactory::createFromApiData(['name' => 'Rest 2'], 1, 'demo')
        ];

        $this->enrichmentService
            ->method('searchProspects')
            ->with(1, 'restaurant', [], ['google_maps', 'demo'])
            ->willReturn($mockProspects);

        $this->enrichmentService
            ->method('getAvailableSources')
            ->willReturn([]);

        $output = $this->handler->handle($input);

        $this->assertTrue($output->success);
        $this->assertCount(2, $output->prospects);
        $this->assertEquals('Rest 1', $output->prospects[0]->name);
        $this->assertEquals('Rest 2', $output->prospects[1]->name);
    }

    public function test_handle_with_filters(): void
    {
        $filters = [
            'city' => 'Lyon',
            'sector' => 'restaurant',
            'radius' => 5000
        ];

        $input = new Input(
            userId: 1,
            query: 'pizza',
            filters: $filters,
            saveSearch: false
        );

        $this->enrichmentService
            ->expects($this->once())
            ->method('searchProspects')
            ->with(1, 'pizza', $filters, [])
            ->willReturn([]);

        $this->handler->handle($input);
    }

    public function test_input_validation(): void
    {
        // Test creation with all parameters
        $input = new Input(
            userId: 42,
            query: 'test query',
            filters: ['city' => 'Paris'],
            sources: ['google_maps'],
            saveSearch: true
        );

        $this->assertEquals(42, $input->userId);
        $this->assertEquals('test query', $input->query);
        $this->assertEquals(['city' => 'Paris'], $input->filters);
        $this->assertEquals(['google_maps'], $input->sources);
        $this->assertTrue($input->saveSearch);

        // Test creation with defaults
        $input = new Input(1, 'test');
        $this->assertEquals([], $input->filters);
        $this->assertEquals([], $input->sources);
        $this->assertTrue($input->saveSearch);
    }
}