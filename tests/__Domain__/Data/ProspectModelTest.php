<?php

namespace Tests\__Domain__\Data;

use App\__Domain__\Data\Prospect\Model;
use PHPUnit\Framework\TestCase;

/**
 * Tests unitaires pour le Model de Prospect
 */
class ProspectModelTest extends TestCase
{
    private function createTestProspect(array $overrides = []): Model
    {
        $defaults = [
            'id' => 1,
            'userId' => 1,
            'name' => 'John Doe',
            'company' => 'Acme Corp',
            'contactInfo' => [
                'email' => 'john@acme.com',
                'phone' => '0123456789',
                'website' => 'https://acme.com'
            ]
        ];

        $data = array_merge($defaults, $overrides);

        return new Model(
            id: $data['id'] ?? null,
            userId: $data['userId'],
            name: $data['name'],
            company: $data['company'] ?? null,
            sector: $data['sector'] ?? null,
            city: $data['city'] ?? null,
            postalCode: $data['postalCode'] ?? null,
            address: $data['address'] ?? null,
            contactInfo: $data['contactInfo'] ?? [],
            description: $data['description'] ?? null,
            relevanceScore: $data['relevanceScore'] ?? 0,
            status: $data['status'] ?? 'new',
            source: $data['source'] ?? null,
            externalId: $data['externalId'] ?? null,
            rawData: $data['rawData'] ?? []
        );
    }

    public function test_prospect_creation(): void
    {
        $prospect = $this->createTestProspect();

        $this->assertEquals(1, $prospect->id);
        $this->assertEquals(1, $prospect->userId);
        $this->assertEquals('John Doe', $prospect->name);
        $this->assertEquals('Acme Corp', $prospect->company);
        $this->assertEquals('new', $prospect->status);
        $this->assertEquals(0, $prospect->relevanceScore);
    }

    public function test_get_email(): void
    {
        $prospect = $this->createTestProspect();
        $this->assertEquals('john@acme.com', $prospect->getEmail());

        $prospectNoEmail = $this->createTestProspect(['contactInfo' => []]);
        $this->assertNull($prospectNoEmail->getEmail());
    }

    public function test_get_phone(): void
    {
        $prospect = $this->createTestProspect();
        $this->assertEquals('0123456789', $prospect->getPhone());

        $prospectNoPhone = $this->createTestProspect(['contactInfo' => ['email' => 'test@test.com']]);
        $this->assertNull($prospectNoPhone->getPhone());
    }

    public function test_get_website(): void
    {
        $prospect = $this->createTestProspect();
        $this->assertEquals('https://acme.com', $prospect->getWebsite());

        $prospectNoWebsite = $this->createTestProspect(['contactInfo' => ['email' => 'test@test.com']]);
        $this->assertNull($prospectNoWebsite->getWebsite());
    }

    public function test_update_status(): void
    {
        $prospect = $this->createTestProspect();
        $this->assertEquals('new', $prospect->status);

        $updatedProspect = $prospect->updateStatus('qualified');
        $this->assertEquals('qualified', $updatedProspect->status);
        $this->assertSame($prospect, $updatedProspect); // Should return same instance
    }

    public function test_update_relevance_score(): void
    {
        $prospect = $this->createTestProspect();

        // Test normal score update
        $updatedProspect = $prospect->updateRelevanceScore(85);
        $this->assertEquals(85, $updatedProspect->relevanceScore);

        // Test score capping at 100
        $updatedProspect = $prospect->updateRelevanceScore(150);
        $this->assertEquals(100, $updatedProspect->relevanceScore);

        // Test score flooring at 0
        $updatedProspect = $prospect->updateRelevanceScore(-10);
        $this->assertEquals(0, $updatedProspect->relevanceScore);
    }

    public function test_contact_info_array_handling(): void
    {
        // Test with null contact info
        $prospect = $this->createTestProspect(['contactInfo' => null]);
        $this->assertEquals([], $prospect->contactInfo);
        $this->assertNull($prospect->getEmail());

        // Test with empty array
        $prospect = $this->createTestProspect(['contactInfo' => []]);
        $this->assertEquals([], $prospect->contactInfo);
        $this->assertNull($prospect->getPhone());

        // Test with partial contact info
        $prospect = $this->createTestProspect(['contactInfo' => ['email' => 'test@test.com']]);
        $this->assertEquals('test@test.com', $prospect->getEmail());
        $this->assertNull($prospect->getPhone());
        $this->assertNull($prospect->getWebsite());
    }

    public function test_immutable_dates(): void
    {
        $now = new \DateTimeImmutable();
        $prospect = new Model(
            id: 1,
            userId: 1,
            name: 'Test',
            createdAt: $now,
            updatedAt: $now
        );

        $this->assertInstanceOf(\DateTimeImmutable::class, $prospect->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $prospect->updatedAt);
        $this->assertEquals($now, $prospect->createdAt);
        $this->assertEquals($now, $prospect->updatedAt);
    }

    public function test_raw_data_array_handling(): void
    {
        // Test with null raw data
        $prospect = $this->createTestProspect(['rawData' => null]);
        $this->assertEquals([], $prospect->rawData);

        // Test with complex raw data
        $complexData = [
            'api_response' => ['status' => 'ok'],
            'metadata' => ['source' => 'google_maps', 'version' => '1.0']
        ];
        $prospect = $this->createTestProspect(['rawData' => $complexData]);
        $this->assertEquals($complexData, $prospect->rawData);
    }
}