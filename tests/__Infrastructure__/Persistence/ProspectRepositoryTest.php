<?php

namespace Tests\__Infrastructure__\Persistence;

use App\__Domain__\Data\Prospect\Model as ProspectModel;
use App\__Infrastructure__\Eloquent\ProspectEloquent;
use App\__Infrastructure__\Eloquent\UserEloquent;
use App\__Infrastructure__\Persistence\Prospect\ProspectRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests d'intégration pour ProspectRepository
 */
class ProspectRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private ProspectRepository $repository;
    private UserEloquent $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->repository = new ProspectRepository();
        $this->user = UserEloquent::factory()->create();
    }

    public function test_find_by_id_returns_prospect(): void
    {
        $eloquent = ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Test Prospect'
        ]);

        $prospect = $this->repository->findById($eloquent->id);

        $this->assertInstanceOf(ProspectModel::class, $prospect);
        $this->assertEquals($eloquent->id, $prospect->id);
        $this->assertEquals('Test Prospect', $prospect->name);
        $this->assertEquals($this->user->id, $prospect->userId);
    }

    public function test_find_by_id_returns_null_when_not_found(): void
    {
        $prospect = $this->repository->findById(999);
        
        $this->assertNull($prospect);
    }

    public function test_find_by_user_id_returns_user_prospects_only(): void
    {
        $otherUser = UserEloquent::factory()->create();
        
        // Create prospects for current user
        ProspectEloquent::factory()->count(3)->create(['user_id' => $this->user->id]);
        
        // Create prospects for other user (should not be returned)
        ProspectEloquent::factory()->count(2)->create(['user_id' => $otherUser->id]);

        $prospects = $this->repository->findByUserId($this->user->id);

        $this->assertCount(3, $prospects);
        foreach ($prospects as $prospect) {
            $this->assertEquals($this->user->id, $prospect->userId);
        }
    }

    public function test_find_by_user_id_with_status_filter(): void
    {
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'new'
        ]);
        
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'qualified'
        ]);

        $prospects = $this->repository->findByUserIdWithFilters($this->user->id, ['status' => 'new']);

        $this->assertCount(1, $prospects);
        $this->assertEquals('new', $prospects[0]->status);
    }

    public function test_find_by_user_id_with_sector_filter(): void
    {
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'sector' => 'restaurant'
        ]);
        
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'sector' => 'tech'
        ]);

        $prospects = $this->repository->findByUserIdWithFilters($this->user->id, ['sector' => 'restaurant']);

        $this->assertCount(1, $prospects);
        $this->assertEquals('restaurant', $prospects[0]->sector);
    }

    public function test_find_by_user_id_with_city_filter(): void
    {
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'city' => 'Paris'
        ]);
        
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'city' => 'Lyon'
        ]);

        $prospects = $this->repository->findByUserIdWithFilters($this->user->id, ['city' => 'Paris']);

        $this->assertCount(1, $prospects);
        $this->assertEquals('Paris', $prospects[0]->city);
    }

    public function test_find_by_user_id_with_min_score_filter(): void
    {
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'relevance_score' => 85
        ]);
        
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'relevance_score' => 65
        ]);

        $prospects = $this->repository->findByUserIdWithFilters($this->user->id, ['min_score' => 80]);

        $this->assertCount(1, $prospects);
        $this->assertEquals(85, $prospects[0]->relevanceScore);
    }

    public function test_find_by_user_id_with_search_filter(): void
    {
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Restaurant Le Petit',
            'company' => 'Le Petit SARL'
        ]);
        
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Boulangerie Martin',
            'sector' => 'bakery'
        ]);

        $prospects = $this->repository->findByUserIdWithFilters($this->user->id, ['search' => 'Restaurant']);

        $this->assertCount(1, $prospects);
        $this->assertEquals('Restaurant Le Petit', $prospects[0]->name);
    }

    public function test_find_by_external_id_returns_correct_prospect(): void
    {
        ProspectEloquent::factory()->create([
            'external_id' => 'gm_123',
            'source' => 'google_maps'
        ]);
        
        ProspectEloquent::factory()->create([
            'external_id' => 'gm_123',
            'source' => 'pages_jaunes' // Same external ID but different source
        ]);

        $prospect = $this->repository->findByExternalId('gm_123', 'google_maps');

        $this->assertNotNull($prospect);
        $this->assertEquals('gm_123', $prospect->externalId);
        $this->assertEquals('google_maps', $prospect->source);
    }

    public function test_save_creates_new_prospect(): void
    {
        $prospectModel = new ProspectModel(
            id: null,
            userId: $this->user->id,
            name: 'New Prospect',
            company: 'New Company',
            contactInfo: ['email' => 'new@company.com'],
            relevanceScore: 75
        );

        $savedProspect = $this->repository->save($prospectModel);

        $this->assertNotNull($savedProspect->id);
        $this->assertEquals('New Prospect', $savedProspect->name);
        $this->assertEquals('New Company', $savedProspect->company);
        $this->assertEquals(75, $savedProspect->relevanceScore);
        
        $this->assertDatabaseHas('prospects', [
            'name' => 'New Prospect',
            'company' => 'New Company',
            'user_id' => $this->user->id
        ]);
    }

    public function test_save_updates_existing_prospect(): void
    {
        $eloquent = ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name'
        ]);

        $prospectModel = new ProspectModel(
            id: $eloquent->id,
            userId: $this->user->id,
            name: 'Updated Name',
            company: 'Updated Company'
        );

        $savedProspect = $this->repository->save($prospectModel);

        $this->assertEquals($eloquent->id, $savedProspect->id);
        $this->assertEquals('Updated Name', $savedProspect->name);
        $this->assertEquals('Updated Company', $savedProspect->company);
        
        $this->assertDatabaseHas('prospects', [
            'id' => $eloquent->id,
            'name' => 'Updated Name',
            'company' => 'Updated Company'
        ]);
    }

    public function test_delete_removes_prospect(): void
    {
        $eloquent = ProspectEloquent::factory()->create(['user_id' => $this->user->id]);
        
        $prospectModel = new ProspectModel(
            id: $eloquent->id,
            userId: $this->user->id,
            name: 'To Delete'
        );

        $this->repository->delete($prospectModel);

        $this->assertDatabaseMissing('prospects', ['id' => $eloquent->id]);
    }

    public function test_count_by_user_id(): void
    {
        $otherUser = UserEloquent::factory()->create();
        
        ProspectEloquent::factory()->count(5)->create(['user_id' => $this->user->id]);
        ProspectEloquent::factory()->count(3)->create(['user_id' => $otherUser->id]);

        $count = $this->repository->countByUserId($this->user->id);

        $this->assertEquals(5, $count);
    }

    public function test_search_by_query(): void
    {
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Restaurant Le Petit',
            'company' => 'Le Petit SARL',
            'sector' => 'restaurant',
            'city' => 'Paris',
            'relevance_score' => 85
        ]);
        
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Boulangerie Martin',
            'relevance_score' => 65
        ]);

        $prospects = $this->repository->searchByQuery($this->user->id, 'restaurant');

        $this->assertCount(1, $prospects);
        $this->assertEquals('Restaurant Le Petit', $prospects[0]->name);
        
        // Should be sorted by relevance score (desc)
        $this->assertEquals(85, $prospects[0]->relevanceScore);
    }

    public function test_to_domain_conversion_with_dates(): void
    {
        $now = now();
        $eloquent = ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'created_at' => $now,
            'updated_at' => $now
        ]);

        $prospect = $this->repository->findById($eloquent->id);

        $this->assertInstanceOf(\DateTimeImmutable::class, $prospect->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $prospect->updatedAt);
        $this->assertEquals($now->format('Y-m-d H:i:s'), $prospect->createdAt->format('Y-m-d H:i:s'));
    }

    public function test_to_domain_conversion_with_json_fields(): void
    {
        $contactInfo = [
            'email' => 'test@example.com',
            'phone' => '0123456789',
            'website' => 'https://example.com'
        ];
        
        $rawData = [
            'api_response' => ['status' => 'ok'],
            'metadata' => ['version' => '1.0']
        ];

        $eloquent = ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'contact_info' => $contactInfo,
            'raw_data' => $rawData
        ]);

        $prospect = $this->repository->findById($eloquent->id);

        $this->assertEquals($contactInfo, $prospect->contactInfo);
        $this->assertEquals($rawData, $prospect->rawData);
    }
}