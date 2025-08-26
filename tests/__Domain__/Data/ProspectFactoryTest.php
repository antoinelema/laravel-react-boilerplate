<?php

namespace Tests\__Domain__\Data;

use App\__Domain__\Data\Prospect\Factory;
use App\__Domain__\Data\Prospect\Model;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour la Factory de Prospect
 */
class ProspectFactoryTest extends TestCase
{
    public function test_create_prospect_with_minimal_data(): void
    {
        $data = [
            'user_id' => 1,
            'name' => 'John Doe'
        ];

        $prospect = Factory::create($data);

        $this->assertInstanceOf(Model::class, $prospect);
        $this->assertNull($prospect->id);
        $this->assertEquals(1, $prospect->userId);
        $this->assertEquals('John Doe', $prospect->name);
        $this->assertNull($prospect->company);
        $this->assertEquals(0, $prospect->relevanceScore);
        $this->assertEquals('new', $prospect->status);
        $this->assertEquals([], $prospect->contactInfo);
        $this->assertEquals([], $prospect->rawData);
    }

    public function test_create_prospect_with_complete_data(): void
    {
        $data = [
            'id' => 123,
            'user_id' => 1,
            'name' => 'John Doe',
            'company' => 'Acme Corp',
            'sector' => 'Technology',
            'city' => 'Paris',
            'postal_code' => '75001',
            'address' => '123 Rue de la Paix',
            'contact_info' => [
                'email' => 'john@acme.com',
                'phone' => '0123456789'
            ],
            'description' => 'Tech company',
            'relevance_score' => 85,
            'status' => 'qualified',
            'source' => 'google_maps',
            'external_id' => 'gm_123',
            'raw_data' => ['original_data' => 'value'],
            'created_at' => '2023-01-01 10:00:00',
            'updated_at' => '2023-01-02 11:00:00'
        ];

        $prospect = Factory::create($data);

        $this->assertEquals(123, $prospect->id);
        $this->assertEquals(1, $prospect->userId);
        $this->assertEquals('John Doe', $prospect->name);
        $this->assertEquals('Acme Corp', $prospect->company);
        $this->assertEquals('Technology', $prospect->sector);
        $this->assertEquals('Paris', $prospect->city);
        $this->assertEquals('75001', $prospect->postalCode);
        $this->assertEquals('123 Rue de la Paix', $prospect->address);
        $this->assertEquals(['email' => 'john@acme.com', 'phone' => '0123456789'], $prospect->contactInfo);
        $this->assertEquals('Tech company', $prospect->description);
        $this->assertEquals(85, $prospect->relevanceScore);
        $this->assertEquals('qualified', $prospect->status);
        $this->assertEquals('google_maps', $prospect->source);
        $this->assertEquals('gm_123', $prospect->externalId);
        $this->assertEquals(['original_data' => 'value'], $prospect->rawData);
        $this->assertInstanceOf(\DateTimeImmutable::class, $prospect->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $prospect->updatedAt);
    }

    public function test_create_from_api_data_google_maps(): void
    {
        $apiData = [
            'id' => 'place_123',
            'name' => 'Restaurant Le Petit',
            'company' => 'Restaurant Le Petit',
            'sector' => 'restaurant',
            'city' => 'Lyon',
            'postal_code' => '69001',
            'address' => '123 Rue de Lyon',
            'phone' => '0478123456',
            'website' => 'https://lepetit.fr',
            'description' => 'Cuisine française',
            'rating' => 4.5
        ];

        $prospect = Factory::createFromApiData($apiData, 1, 'google_maps');

        $this->assertEquals(1, $prospect->userId);
        $this->assertEquals('Restaurant Le Petit', $prospect->name);
        $this->assertEquals('Restaurant Le Petit', $prospect->company);
        $this->assertEquals('restaurant', $prospect->sector);
        $this->assertEquals('Lyon', $prospect->city);
        $this->assertEquals('69001', $prospect->postalCode);
        $this->assertEquals('123 Rue de Lyon', $prospect->address);
        $this->assertEquals('0478123456', $prospect->contactInfo['phone']);
        $this->assertEquals('https://lepetit.fr', $prospect->contactInfo['website']);
        $this->assertEquals('google_maps', $prospect->source);
        $this->assertEquals('place_123', $prospect->externalId);
        $this->assertEquals($apiData, $prospect->rawData);
        $this->assertGreaterThan(50, $prospect->relevanceScore); // Should have bonus for phone and website
    }

    public function test_create_from_api_data_demo(): void
    {
        $apiData = [
            'id' => 'pj_456',
            'name' => 'Boulangerie Martin',
            'email' => 'contact@martin.fr',
            'phone' => '0145678901',
            'city' => 'Marseille'
        ];

        $prospect = Factory::createFromApiData($apiData, 2, 'demo');

        $this->assertEquals(2, $prospect->userId);
        $this->assertEquals('Boulangerie Martin', $prospect->name);
        $this->assertEquals('Marseille', $prospect->city);
        $this->assertEquals('contact@martin.fr', $prospect->contactInfo['email']);
        $this->assertEquals('0145678901', $prospect->contactInfo['phone']);
        $this->assertEquals('demo', $prospect->source);
        $this->assertEquals('pj_456', $prospect->externalId);
        // Should have high score due to email + phone
        $this->assertGreaterThanOrEqual(85, $prospect->relevanceScore);
    }

    public function test_relevance_score_calculation(): void
    {
        // Test with no contact info
        $apiData = ['name' => 'Test Company'];
        $prospect = Factory::createFromApiData($apiData, 1, 'test');
        $this->assertEquals(50, $prospect->relevanceScore); // Base score

        // Test with email only
        $apiData = ['name' => 'Test Company', 'email' => 'test@company.com'];
        $prospect = Factory::createFromApiData($apiData, 1, 'test');
        $this->assertEquals(70, $prospect->relevanceScore); // Base + email bonus

        // Test with all contact info
        $apiData = [
            'name' => 'Test Company',
            'email' => 'test@company.com',
            'phone' => '0123456789',
            'website' => 'https://company.com',
            'description' => 'Great company',
            'company' => 'Test Company Inc'
        ];
        $prospect = Factory::createFromApiData($apiData, 1, 'test');
        $this->assertEquals(100, $prospect->relevanceScore); // Maximum capped at 100
    }

    public function test_extract_contact_info(): void
    {
        $apiData = [
            'name' => 'Test',
            'email' => 'test@example.com',
            'phone' => '0123456789',
            'website' => 'https://example.com',
            'social_networks' => ['twitter' => '@test']
        ];

        $prospect = Factory::createFromApiData($apiData, 1, 'test');

        $this->assertEquals([
            'email' => 'test@example.com',
            'phone' => '0123456789',
            'website' => 'https://example.com',
            'social_networks' => ['twitter' => '@test']
        ], $prospect->contactInfo);
    }

    public function test_handle_missing_fields_gracefully(): void
    {
        $apiData = []; // Empty data

        $prospect = Factory::createFromApiData($apiData, 1, 'test');

        $this->assertEquals('Unknown', $prospect->name);
        $this->assertEquals([], $prospect->contactInfo);
        $this->assertEquals(50, $prospect->relevanceScore); // Base score
    }
}