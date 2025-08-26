<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent;
use App\__Infrastructure__\Eloquent\ProspectEloquent;
use App\__Infrastructure__\Eloquent\ProspectCategoryEloquent;
use Tests\Concerns\ResetsTransactions;
use Tests\TestCase;

class ProspectCategoryApiTest extends TestCase
{
    use ResetsTransactions;

    private UserEloquent $premiumUser;
    private UserEloquent $freeUser;

    protected function setUp(): void
    {
        parent::setUp();

        // Utilisateur premium
        $this->premiumUser = UserEloquent::factory()->create([
            'subscription_type' => 'premium',
            'role' => 'user',
        ]);

        // Utilisateur gratuit
        $this->freeUser = UserEloquent::factory()->create([
            'subscription_type' => 'free',
            'role' => 'user',
        ]);
    }

    public function test_premium_user_can_list_categories()
    {
        $this->actingAs($this->premiumUser);

        // Créer quelques catégories
        ProspectCategoryEloquent::factory()->count(3)->create([
            'user_id' => $this->premiumUser->id
        ]);

        $response = $this->getJson('/api/v1/prospect-categories');

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true
                ])
                ->assertJsonCount(3, 'data.categories');
    }

    public function test_free_user_cannot_access_categories()
    {
        $this->actingAs($this->freeUser);

        $response = $this->getJson('/api/v1/prospect-categories');

        $response->assertStatus(403);
    }

    public function test_premium_user_can_create_category()
    {
        $this->actingAs($this->premiumUser);

        $categoryData = [
            'name' => 'Test Category',
            'color' => '#ff0000',
            'position' => 1
        ];

        $response = $this->postJson('/api/v1/prospect-categories', $categoryData);

        $response->assertStatus(201)
                ->assertJson([
                    'success' => true,
                    'message' => 'Catégorie créée avec succès'
                ])
                ->assertJsonPath('data.category.name', 'Test Category')
                ->assertJsonPath('data.category.color', '#ff0000');

        $this->assertDatabaseHas('prospect_categories', [
            'name' => 'Test Category',
            'color' => '#ff0000',
            'user_id' => $this->premiumUser->id
        ]);
    }

    public function test_category_name_must_be_unique_per_user()
    {
        $this->actingAs($this->premiumUser);

        // Créer une première catégorie
        ProspectCategoryEloquent::factory()->create([
            'user_id' => $this->premiumUser->id,
            'name' => 'Existing Category'
        ]);

        // Essayer de créer une catégorie avec le même nom
        $response = $this->postJson('/api/v1/prospect-categories', [
            'name' => 'Existing Category',
            'color' => '#00ff00'
        ]);

        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Une catégorie avec ce nom existe déjà'
                ]);
    }

    public function test_premium_user_can_update_category()
    {
        $this->actingAs($this->premiumUser);

        $category = ProspectCategoryEloquent::factory()->create([
            'user_id' => $this->premiumUser->id,
            'name' => 'Original Name',
            'color' => '#000000'
        ]);

        $response = $this->putJson("/api/v1/prospect-categories/{$category->id}", [
            'name' => 'Updated Name',
            'color' => '#ffffff'
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Catégorie mise à jour avec succès'
                ])
                ->assertJsonPath('data.category.name', 'Updated Name');

        $this->assertDatabaseHas('prospect_categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
            'color' => '#ffffff'
        ]);
    }

    public function test_premium_user_can_delete_category()
    {
        $this->actingAs($this->premiumUser);

        $category = ProspectCategoryEloquent::factory()->create([
            'user_id' => $this->premiumUser->id
        ]);

        $response = $this->deleteJson("/api/v1/prospect-categories/{$category->id}");

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Catégorie supprimée avec succès'
                ]);

        $this->assertDatabaseMissing('prospect_categories', [
            'id' => $category->id
        ]);
    }

    public function test_premium_user_can_assign_prospect_to_categories()
    {
        $this->actingAs($this->premiumUser);

        $prospect = ProspectEloquent::factory()->create([
            'user_id' => $this->premiumUser->id
        ]);

        $categories = ProspectCategoryEloquent::factory()->count(2)->create([
            'user_id' => $this->premiumUser->id
        ]);

        $response = $this->postJson("/api/v1/prospects/{$prospect->id}/categories", [
            'category_ids' => $categories->pluck('id')->toArray()
        ]);

        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Prospect assigné aux catégories avec succès'
                ]);

        foreach ($categories as $category) {
            $this->assertDatabaseHas('prospect_category_prospect', [
                'prospect_id' => $prospect->id,
                'prospect_category_id' => $category->id
            ]);
        }
    }

    public function test_user_cannot_access_other_users_categories()
    {
        $this->actingAs($this->premiumUser);

        $otherUser = UserEloquent::factory()->create([
            'subscription_type' => 'premium'
        ]);

        $otherCategory = ProspectCategoryEloquent::factory()->create([
            'user_id' => $otherUser->id
        ]);

        // Tenter de mettre à jour la catégorie d'un autre utilisateur
        $response = $this->putJson("/api/v1/prospect-categories/{$otherCategory->id}", [
            'name' => 'Hacked Name'
        ]);

        $response->assertStatus(404);

        // Tenter de supprimer la catégorie d'un autre utilisateur
        $response = $this->deleteJson("/api/v1/prospect-categories/{$otherCategory->id}");

        $response->assertStatus(404);
    }

    public function test_validation_rules_for_category_creation()
    {
        $this->actingAs($this->premiumUser);

        // Nom requis
        $response = $this->postJson('/api/v1/prospect-categories', [
            'color' => '#ff0000'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);

        // Couleur invalide
        $response = $this->postJson('/api/v1/prospect-categories', [
            'name' => 'Test',
            'color' => 'invalid-color'
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['color']);

        // Position négative
        $response = $this->postJson('/api/v1/prospect-categories', [
            'name' => 'Test',
            'position' => -1
        ]);
        $response->assertStatus(422)
                ->assertJsonValidationErrors(['position']);
    }
}