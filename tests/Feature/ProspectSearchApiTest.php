<?php

namespace Tests\Feature;

use App\__Domain__\Data\User\Factory as UserFactory;
use App\__Infrastructure__\Eloquent\UserEloquent;
use Tests\Concerns\ResetsTransactions;
use Tests\TestCase;

/**
 * Tests d'intégration pour l'API de recherche de prospects
 */
class ProspectSearchApiTest extends TestCase
{
    use ResetsTransactions;

    private UserEloquent $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = UserEloquent::factory()->create();
    }

    public function test_search_prospects_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'restaurant'
        ]);

        $response->assertStatus(401);
    }

    public function test_search_prospects_validates_required_query(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_search_prospects_validates_query_length(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', [
                'query' => 'a' // Too short
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', [
                'query' => str_repeat('a', 256) // Too long
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['query']);
    }

    public function test_search_prospects_validates_filters(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', [
                'query' => 'restaurant',
                'filters' => 'invalid' // Should be array
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['filters']);
    }

    public function test_search_prospects_validates_sources(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', [
                'query' => 'restaurant',
                'sources' => ['invalid_source']
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sources.0']);
    }

    public function test_search_prospects_accepts_valid_sources(): void
    {
        // Mock the services to avoid external API calls
        $this->mockExternalServices();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', [
                'query' => 'restaurant',
                'sources' => ['google_maps', 'demo']
            ]);

        $response->assertStatus(200);
    }

    public function test_search_prospects_with_valid_filters(): void
    {
        $this->mockExternalServices();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', [
                'query' => 'restaurant',
                'filters' => [
                    'location' => 'Paris',
                    'sector' => 'restaurant',
                    'radius' => 5000,
                    'postal_code' => '75001',
                    'limit' => 20
                ]
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'prospects',
                    'total_found',
                    'search',
                    'available_sources'
                ]
            ]);
    }

    public function test_search_prospects_successful_response_structure(): void
    {
        $this->mockExternalServices();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', [
                'query' => 'restaurant'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'prospects' => [
                        '*' => [
                            'id',
                            'name',
                            'company',
                            'sector',
                            'city',
                            'postal_code',
                            'address',
                            'phone',
                            'email',
                            'website',
                            'description',
                            'relevance_score',
                            'source',
                            'external_id'
                        ]
                    ],
                    'total_found',
                    'duplicates_found',
                    'sources_stats',
                    'deduplication_info',
                    'available_sources',
                    'quota_info'
                ]
            ]);
    }

    public function test_search_prospects_without_saving_search(): void
    {
        $this->mockExternalServices();

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', [
                'query' => 'restaurant',
                'save_search' => false
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'search' => null // Search should not be saved
                ]
            ]);
    }

    public function test_get_available_sources(): void
    {
        // Mock the aggregator service for this specific test
        $this->mock(\App\__Infrastructure__\Services\Aggregation\SearchAggregatorService::class)
            ->shouldReceive('getAvailableSources')
            ->andReturn([
                'google_maps' => ['name' => 'Google Maps', 'available' => false, 'description' => 'Google Maps API'],
                'demo' => ['name' => 'Demo', 'available' => true, 'description' => 'Demo data source']
            ]);
            
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/prospects/sources');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'sources' => [
                        'demo' => [
                            'name',
                            'available',
                            'description'
                        ],
                        'google_maps' => [
                            'name',
                            'available',
                            'description'
                        ]
                    ]
                ]
            ]);
    }

    public function test_sources_endpoint_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/prospects/sources');
        $response->assertStatus(401);
    }

    private function mockExternalServices(): void
    {
        // Mock the search aggregator service that is actually used by the controller
        $testResult = [
            'aggregated_results' => [
                [
                    'id' => 1,
                    'name' => 'Test Restaurant',
                    'company' => 'Test Company',
                    'sector' => 'Restaurant',
                    'city' => 'Paris',
                    'postal_code' => '75001',
                    'address' => '123 Test Street',
                    'phone' => '0123456789',
                    'email' => 'test@example.com',
                    'website' => 'http://test.com',
                    'description' => 'Test description',
                    'relevance_score' => 85.5,
                    'source' => 'demo',
                    'external_id' => 'demo_123'
                ]
            ],
            'total_found' => 1,
            'duplicates_found' => [],
            'search_stats' => [],
            'deduplication_info' => []
        ];
        
        $this->mock(\App\__Infrastructure__\Services\Aggregation\SearchAggregatorService::class)
            ->shouldReceive('search')
            ->andReturn($testResult)
            ->shouldReceive('getAvailableSources')
            ->andReturn([
                'google_maps' => ['name' => 'Google Maps', 'available' => false, 'description' => 'Google Maps API'],
                'demo' => ['name' => 'Demo', 'available' => true, 'description' => 'Demo data source']
            ]);
    }

    public function test_search_handles_service_errors_gracefully(): void
    {
        // Mock service to throw exception
        $this->mock(\App\__Infrastructure__\Services\Aggregation\SearchAggregatorService::class)
            ->shouldReceive('search')
            ->andThrow(new \Exception('Service unavailable'));

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/search', [
                'query' => 'restaurant'
            ]);

        $response->assertStatus(500)
            ->assertJson([
                'success' => false
            ])
            ->assertJsonStructure([
                'success',
                'message'
            ]);
    }
}