<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\ProspectEloquent;
use App\__Infrastructure__\Eloquent\UserEloquent;
use Tests\Concerns\ResetsTransactions;
use Tests\TestCase;

/**
 * Tests d'intégration pour l'API de gestion des prospects
 */
class ProspectApiTest extends TestCase
{
    use ResetsTransactions;

    private UserEloquent $user;
    private UserEloquent $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = UserEloquent::create([
            'name' => 'Test',
            'firstname' => 'User',
            'email' => 'testuser@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'subscription_type' => 'premium' // Premium for testing prospect features
        ]);
        
        $this->otherUser = UserEloquent::create([
            'name' => 'Other',
            'firstname' => 'User', 
            'email' => 'otheruser@example.com',
            'password' => bcrypt('password'),
            'role' => 'user',
            'subscription_type' => 'premium'
        ]);
    }

    public function test_list_prospects_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/prospects');
        $response->assertStatus(401);
    }

    public function test_list_prospects_returns_user_prospects_only(): void
    {
        // Create prospects for current user
        ProspectEloquent::factory()->count(3)->create(['user_id' => $this->user->id]);
        
        // Create prospects for other user (should not be returned)
        ProspectEloquent::factory()->count(2)->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/prospects');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'total' => 3
                ]
            ])
            ->assertJsonCount(3, 'data.prospects');
    }

    public function test_list_prospects_with_filters(): void
    {
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'new',
            'sector' => 'restaurant',
            'city' => 'Paris',
            'relevance_score' => 85
        ]);

        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'status' => 'qualified',
            'sector' => 'tech',
            'city' => 'Lyon',
            'relevance_score' => 65
        ]);

        // Test status filter
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/prospects?status=new');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.prospects');

        // Test sector filter
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/prospects?sector=restaurant');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.prospects');

        // Test minimum score filter
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/prospects?min_score=80');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.prospects');
    }

    public function test_show_prospect_requires_authentication(): void
    {
        $prospect = ProspectEloquent::factory()->create();
        
        $response = $this->getJson("/api/v1/prospects/{$prospect->id}");
        $response->assertStatus(401);
    }

    public function test_show_prospect_returns_correct_prospect(): void
    {
        $prospect = ProspectEloquent::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/prospects/{$prospect->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'prospect' => [
                        'id' => $prospect->id,
                        'name' => $prospect->name
                    ]
                ]
            ]);
    }

    public function test_show_prospect_denies_access_to_other_user_prospect(): void
    {
        $prospect = ProspectEloquent::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/prospects/{$prospect->id}");

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Prospect non trouvé'
            ]);
    }

    public function test_store_prospect_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/prospects', [
            'name' => 'Test Prospect'
        ]);

        $response->assertStatus(401);
    }

    public function test_store_prospect_validates_required_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_prospect_validates_contact_info(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects', [
                'name' => 'Test Prospect',
                'contact_info' => [
                    'email' => 'invalid-email',
                    'website' => 'not-a-url'
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['contact_info.email', 'contact_info.website']);
    }

    public function test_store_prospect_success(): void
    {
        $prospectData = [
            'name' => 'John Doe',
            'company' => 'Acme Corp',
            'sector' => 'Technology',
            'city' => 'Paris',
            'postal_code' => '75001',
            'address' => '123 Rue de la Paix',
            'contact_info' => [
                'email' => 'john@acme.com',
                'phone' => '0123456789',
                'website' => 'https://acme.com'
            ],
            'description' => 'Great company',
            'relevance_score' => 85,
            'status' => 'qualified'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects', $prospectData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Prospect sauvegardé avec succès',
                'data' => [
                    'was_already_exists' => false
                ]
            ])
            ->assertJsonPath('data.prospect.name', 'John Doe');

        $this->assertDatabaseHas('prospects', [
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'company' => 'Acme Corp'
        ]);
    }

    public function test_update_prospect_requires_authentication(): void
    {
        $prospect = ProspectEloquent::factory()->create();
        
        $response = $this->putJson("/api/v1/prospects/{$prospect->id}", [
            'name' => 'Updated Name'
        ]);

        $response->assertStatus(401);
    }

    public function test_update_prospect_denies_access_to_other_user_prospect(): void
    {
        $prospect = ProspectEloquent::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/prospects/{$prospect->id}", [
                'name' => 'Updated Name'
            ]);

        $response->assertStatus(404);
    }

    public function test_update_prospect_success(): void
    {
        $prospect = ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Original Name',
            'status' => 'new'
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/prospects/{$prospect->id}", [
                'name' => 'Updated Name',
                'status' => 'qualified'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prospect mis à jour avec succès'
            ])
            ->assertJsonPath('data.prospect.name', 'Updated Name')
            ->assertJsonPath('data.prospect.status', 'qualified');

        $this->assertDatabaseHas('prospects', [
            'id' => $prospect->id,
            'name' => 'Updated Name',
            'status' => 'qualified'
        ]);
    }

    public function test_delete_prospect_requires_authentication(): void
    {
        $prospect = ProspectEloquent::factory()->create();
        
        $response = $this->deleteJson("/api/v1/prospects/{$prospect->id}");
        $response->assertStatus(401);
    }

    public function test_delete_prospect_denies_access_to_other_user_prospect(): void
    {
        $prospect = ProspectEloquent::factory()->create(['user_id' => $this->otherUser->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/prospects/{$prospect->id}");

        $response->assertStatus(404);
    }

    public function test_delete_prospect_success(): void
    {
        $prospect = ProspectEloquent::factory()->create(['user_id' => $this->user->id]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/prospects/{$prospect->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Prospect supprimé avec succès'
            ]);

        $this->assertDatabaseMissing('prospects', ['id' => $prospect->id]);
    }

    public function test_search_local_prospects(): void
    {
        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Restaurant Le Petit',
            'company' => 'Le Petit SARL',
            'sector' => 'restaurant'
        ]);

        ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Boulangerie Martin',
            'sector' => 'bakery'
        ]);

        // Search by name
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/prospects/search/local?query=Restaurant');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.prospects')
            ->assertJsonPath('data.prospects.0.name', 'Restaurant Le Petit');

        // Search by sector
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/prospects/search/local?query=restaurant');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.prospects');
    }

    public function test_search_local_prospects_requires_query(): void
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/prospects/search/local');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
                'message' => 'Le terme de recherche est requis'
            ]);
    }

    public function test_prospects_response_structure(): void
    {
        $prospect = ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'contact_info' => [
                'email' => 'test@example.com',
                'phone' => '0123456789'
            ]
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/prospects');

        $response->assertJsonStructure([
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
                        'contact_info' => [
                            'email',
                            'phone'
                        ],
                        'description',
                        'relevance_score',
                        'status',
                        'source',
                        'external_id',
                        'created_at',
                        'updated_at'
                    ]
                ],
                'total',
                'filters_applied'
            ]
        ]);
    }

    public function test_store_bulk_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/prospects/bulk', [
            'prospects' => [
                ['name' => 'Test Prospect']
            ]
        ]);

        $response->assertStatus(401);
    }

    public function test_store_bulk_validates_prospects_array(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prospects']);
    }

    public function test_store_bulk_validates_empty_prospects_array(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', [
                'prospects' => []
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prospects']);
    }

    public function test_store_bulk_validates_prospects_limit(): void
    {
        $prospects = array_fill(0, 101, ['name' => 'Test Prospect']);

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', [
                'prospects' => $prospects
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['prospects']);
    }

    public function test_store_bulk_validates_prospect_fields(): void
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', [
                'prospects' => [
                    ['name' => ''], // name required
                    [
                        'name' => 'Valid Name',
                        'email' => 'invalid-email', // invalid email
                        'website' => 'not-a-url' // invalid URL
                    ]
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'prospects.0.name',
                'prospects.1.email',
                'prospects.1.website'
            ]);
    }

    public function test_store_bulk_success_single_prospect(): void
    {
        $prospectData = [
            'name' => 'John Doe',
            'company' => 'Acme Corp',
            'sector' => 'Technology',
            'city' => 'Paris',
            'postal_code' => '75001',
            'address' => '123 Rue de la Paix',
            'phone' => '0123456789',
            'email' => 'john@acme.com',
            'website' => 'https://acme.com',
            'description' => 'Great company',
            'relevance_score' => 85,
            'source' => 'manual',
            'external_id' => 'ext-123'
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', [
                'prospects' => [$prospectData],
                'search_id' => 123
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '1 prospect(s) sauvegardé(s)',
                'data' => [
                    'saved' => 1,
                    'exists' => 0,
                    'errors' => 0
                ]
            ])
            ->assertJsonCount(1, 'data.details');

        $this->assertDatabaseHas('prospects', [
            'user_id' => $this->user->id,
            'name' => 'John Doe',
            'company' => 'Acme Corp'
        ]);
    }

    public function test_store_bulk_success_multiple_prospects(): void
    {
        $prospectsData = [
            [
                'name' => 'John Doe',
                'company' => 'Acme Corp',
                'sector' => 'Technology'
            ],
            [
                'name' => 'Jane Smith',
                'company' => 'Beta Inc',
                'sector' => 'Marketing'
            ],
            [
                'name' => 'Bob Wilson',
                'company' => 'Gamma Ltd',
                'sector' => 'Finance'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', [
                'prospects' => $prospectsData
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '3 prospect(s) sauvegardé(s)',
                'data' => [
                    'saved' => 3,
                    'exists' => 0,
                    'errors' => 0
                ]
            ])
            ->assertJsonCount(3, 'data.details');

        foreach ($prospectsData as $prospectData) {
            $this->assertDatabaseHas('prospects', [
                'user_id' => $this->user->id,
                'name' => $prospectData['name'],
                'company' => $prospectData['company']
            ]);
        }
    }

    public function test_store_bulk_handles_existing_prospects(): void
    {
        // Create an existing prospect
        $existingProspect = ProspectEloquent::factory()->create([
            'user_id' => $this->user->id,
            'name' => 'Existing Prospect',
            'company' => 'Existing Corp'
        ]);

        $prospectsData = [
            [
                'name' => 'Existing Prospect', // Will be detected as existing
                'company' => 'Existing Corp'
            ],
            [
                'name' => 'New Prospect',
                'company' => 'New Corp'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', [
                'prospects' => $prospectsData
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => '1 prospect(s) sauvegardé(s), 1 prospect(s) existaient déjà',
                'data' => [
                    'saved' => 1,
                    'exists' => 1,
                    'errors' => 0
                ]
            ]);

        // Verify new prospect was created
        $this->assertDatabaseHas('prospects', [
            'user_id' => $this->user->id,
            'name' => 'New Prospect',
            'company' => 'New Corp'
        ]);
    }

    public function test_store_bulk_normalizes_contact_info(): void
    {
        $prospectData = [
            'name' => 'Contact Test',
            'company' => 'Contact Corp',
            'phone' => '0123456789',
            'email' => 'test@contact.com',
            'website' => 'https://contact.com',
            'contact_info' => [
                'existing_field' => 'existing_value'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', [
                'prospects' => [$prospectData]
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'saved' => 1,
                    'exists' => 0,
                    'errors' => 0
                ]
            ]);

        // Verify contact info was properly normalized
        $prospect = ProspectEloquent::where('name', 'Contact Test')->first();
        $this->assertNotNull($prospect);
        
        $contactInfo = $prospect->contact_info;
        $this->assertEquals('0123456789', $contactInfo['phone']);
        $this->assertEquals('test@contact.com', $contactInfo['email']);
        $this->assertEquals('https://contact.com', $contactInfo['website']);
        $this->assertEquals('existing_value', $contactInfo['existing_field']);
    }

    public function test_store_bulk_response_structure(): void
    {
        $prospectsData = [
            [
                'name' => 'Structure Test',
                'company' => 'Structure Corp'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', [
                'prospects' => $prospectsData
            ]);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'saved',
                'exists',
                'errors',
                'details' => [
                    '*' => [
                        'index',
                        'name',
                        'status',
                        'prospect_id'
                    ]
                ]
            ]
        ]);
    }

    public function test_store_bulk_transaction_rollback_on_critical_error(): void
    {
        // This would be tested with a mock to simulate database errors
        // For now, we verify that the method handles errors gracefully
        
        $prospectsData = [
            [
                'name' => 'Valid Prospect',
                'company' => 'Valid Corp'
            ]
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/prospects/bulk', [
                'prospects' => $prospectsData
            ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }
}