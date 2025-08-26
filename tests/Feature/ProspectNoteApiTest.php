<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\__Infrastructure__\Eloquent\UserEloquent;
use App\__Infrastructure__\Eloquent\ProspectEloquent;
use Tests\Concerns\ResetsTransactions;
use Illuminate\Support\Facades\DB;

class ProspectNoteApiTest extends TestCase
{
    use ResetsTransactions;

    private UserEloquent $user;
    private array $prospectData;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = UserEloquent::factory()->create(['subscription_type' => 'premium']);
        
        $this->prospectData = [
            'user_id' => $this->user->id,
            'name' => 'Test Company',
            'address' => '123 Test Street',
            'city' => 'Test City',
            'postal_code' => '12345',
            'sector' => 'Technology',
            'contact_info' => json_encode([
                'email' => 'test@example.com',
                'phone' => '123456789'
            ]),
            'status' => 'new',
            'source' => 'api',
            'relevance_score' => 85,
            'raw_data' => json_encode(['test' => 'data']),
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    public function test_list_notes_requires_authentication()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        
        $response = $this->getJson("/api/v1/prospects/{$prospectId}/notes");
        
        $response->assertStatus(401);
    }

    public function test_list_notes_for_prospect()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        
        // Créer quelques notes
        $noteIds = [
            DB::table('prospect_notes')->insertGetId([
                'prospect_id' => $prospectId,
                'user_id' => $this->user->id,
                'content' => 'First note',
                'type' => 'note',
                'created_at' => now()->subDay(),
                'updated_at' => now()->subDay()
            ]),
            DB::table('prospect_notes')->insertGetId([
                'prospect_id' => $prospectId,
                'user_id' => $this->user->id,
                'content' => 'Second note',
                'type' => 'call',
                'created_at' => now(),
                'updated_at' => now()
            ])
        ];
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/prospects/{$prospectId}/notes");
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'notes' => [
                        '*' => ['id', 'prospect_id', 'content', 'type', 'created_at', 'updated_at']
                    ]
                ]
            ])
            ->assertJsonCount(2, 'data.notes');
            
        // Vérifier l'ordre (plus récent en premier)
        $notes = $response->json('data.notes');
        $this->assertEquals('Second note', $notes[0]['content']);
        $this->assertEquals('First note', $notes[1]['content']);
    }

    public function test_list_notes_denies_access_to_other_user_prospect()
    {
        $otherUser = UserEloquent::factory()->create();
        $prospectData = $this->prospectData;
        $prospectData['user_id'] = $otherUser->id;
        $prospectId = DB::table('prospects')->insertGetId($prospectData);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/v1/prospects/{$prospectId}/notes");
        
        $response->assertStatus(404);
    }

    public function test_create_note_requires_authentication()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        
        $response = $this->postJson("/api/v1/prospects/{$prospectId}/notes", [
            'content' => 'Test note',
            'type' => 'note'
        ]);
        
        $response->assertStatus(401);
    }

    public function test_create_note_validates_required_fields()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/prospects/{$prospectId}/notes", []);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['content', 'type']);
    }

    public function test_create_note_validates_type()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/prospects/{$prospectId}/notes", [
                'content' => 'Test note',
                'type' => 'invalid_type'
            ]);
        
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_create_note_success()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/prospects/{$prospectId}/notes", [
                'content' => 'Test note content',
                'type' => 'call'
            ]);
        
        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'note' => ['id', 'prospect_id', 'content', 'type', 'created_at', 'updated_at']
                ]
            ]);
            
        $this->assertDatabaseHas('prospect_notes', [
            'prospect_id' => $prospectId,
            'content' => 'Test note content',
            'type' => 'call'
        ]);
    }

    public function test_update_note_requires_authentication()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        $noteId = DB::table('prospect_notes')->insertGetId([
            'prospect_id' => $prospectId,
            'user_id' => $this->user->id,
            'content' => 'Original content',
            'type' => 'note',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $response = $this->putJson("/api/v1/prospects/{$prospectId}/notes/{$noteId}", [
            'content' => 'Updated content',
            'type' => 'call'
        ]);
        
        $response->assertStatus(401);
    }

    public function test_update_note_success()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        $noteId = DB::table('prospect_notes')->insertGetId([
            'prospect_id' => $prospectId,
            'user_id' => $this->user->id,
            'content' => 'Original content',
            'type' => 'note',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/prospects/{$prospectId}/notes/{$noteId}", [
                'content' => 'Updated content',
                'type' => 'call'
            ]);
        
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'note' => ['id', 'prospect_id', 'content', 'type', 'created_at', 'updated_at']
                ]
            ]);
            
        $this->assertDatabaseHas('prospect_notes', [
            'id' => $noteId,
            'prospect_id' => $prospectId,
            'content' => 'Updated content',
            'type' => 'call'
        ]);
    }

    public function test_delete_note_requires_authentication()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        $noteId = DB::table('prospect_notes')->insertGetId([
            'prospect_id' => $prospectId,
            'user_id' => $this->user->id,
            'content' => 'Test content',
            'type' => 'note',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $response = $this->deleteJson("/api/v1/prospects/{$prospectId}/notes/{$noteId}");
        
        $response->assertStatus(401);
    }

    public function test_delete_note_success()
    {
        $prospectId = DB::table('prospects')->insertGetId($this->prospectData);
        $noteId = DB::table('prospect_notes')->insertGetId([
            'prospect_id' => $prospectId,
            'user_id' => $this->user->id,
            'content' => 'Test content',
            'type' => 'note',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/prospects/{$prospectId}/notes/{$noteId}");
        
        $response->assertStatus(200)
            ->assertJson(['message' => 'Note deleted successfully']);
            
        $this->assertDatabaseMissing('prospect_notes', [
            'id' => $noteId
        ]);
    }

    public function test_note_operations_deny_access_to_other_user_prospect()
    {
        $otherUser = UserEloquent::factory()->create();
        $prospectData = $this->prospectData;
        $prospectData['user_id'] = $otherUser->id;
        $prospectId = DB::table('prospects')->insertGetId($prospectData);
        
        $noteId = DB::table('prospect_notes')->insertGetId([
            'prospect_id' => $prospectId,
            'user_id' => $otherUser->id,
            'content' => 'Test content',
            'type' => 'note',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Test create
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/v1/prospects/{$prospectId}/notes", [
                'content' => 'New note',
                'type' => 'note'
            ]);
        $response->assertStatus(404);
        
        // Test update
        $response = $this->actingAs($this->user, 'sanctum')
            ->putJson("/api/v1/prospects/{$prospectId}/notes/{$noteId}", [
                'content' => 'Updated content',
                'type' => 'call'
            ]);
        $response->assertStatus(404);
        
        // Test delete
        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson("/api/v1/prospects/{$prospectId}/notes/{$noteId}");
        $response->assertStatus(404);
    }
}