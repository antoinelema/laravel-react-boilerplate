<?php

namespace Tests\Feature;

use App\__Infrastructure__\Eloquent\UserEloquent as User;
use Tests\Concerns\ResetsTransactions;
use Tests\TestCase;

class ImprovedSearchInterfaceTest extends TestCase
{
    use ResetsTransactions;

    public function test_search_with_unified_location_field()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        // Test avec ville seulement
        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'restaurant',
            'filters' => [
                'location' => 'Paris',
                'radius' => 5000, // 5 km en mètres
                'limit' => 10
            ],
            'sources' => ['demo']
        ]);

        $this->assertNotEquals(422, $response->getStatusCode());
    }

    public function test_search_with_postal_code_in_location()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        // Test avec code postal seulement
        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'boulangerie',
            'filters' => [
                'location' => '75001',
                'radius' => 2000, // 2 km en mètres
                'limit' => 15
            ],
            'sources' => ['demo']
        ]);

        $this->assertNotEquals(422, $response->getStatusCode());
    }

    public function test_search_with_combined_location()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        // Test avec ville + code postal
        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'coiffeur',
            'filters' => [
                'location' => 'Paris 75001',
                'radius' => 10000, // 10 km en mètres
                'limit' => 20
            ],
            'sources' => ['demo']
        ]);

        $this->assertNotEquals(422, $response->getStatusCode());
    }

    public function test_radius_validation_accepts_meters()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        // Test avec rayon valide en mètres
        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'magasin',
            'filters' => [
                'location' => 'Lyon',
                'radius' => 15000, // 15 km en mètres
                'limit' => 25
            ],
            'sources' => ['demo']
        ]);

        $this->assertNotEquals(422, $response->getStatusCode());
    }

    public function test_radius_validation_rejects_too_small_values()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        // Test avec rayon trop petit (moins de 1 km)
        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'magasin',
            'filters' => [
                'location' => 'Lyon',
                'radius' => 500, // 0.5 km en mètres - doit échouer
                'limit' => 25
            ],
            'sources' => ['demo']
        ]);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $response->json());
    }

    public function test_radius_validation_rejects_too_large_values()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        // Test avec rayon trop grand (plus de 50 km)
        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'magasin',
            'filters' => [
                'location' => 'Lyon',
                'radius' => 60000, // 60 km en mètres - doit échouer
                'limit' => 25
            ],
            'sources' => ['demo']
        ]);

        $this->assertEquals(422, $response->getStatusCode());
        $this->assertArrayHasKey('errors', $response->json());
    }

    public function test_location_field_accepts_various_formats()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        $locationFormats = [
            'Paris',
            '75001',
            'Paris 75001',
            'Lyon 69000',
            'Marseille',
            '13001'
        ];

        foreach ($locationFormats as $location) {
            $response = $this->postJson('/api/v1/prospects/search', [
                'query' => 'restaurant',
                'filters' => [
                    'location' => $location,
                    'radius' => 5000,
                    'limit' => 10
                ],
                'sources' => ['demo']
            ]);

            $this->assertNotEquals(422, $response->getStatusCode(), 
                "Location format '{$location}' should be valid but got validation error");
        }
    }

    public function test_search_without_location_still_works()
    {
        $user = User::factory()->create(['subscription_type' => 'premium']);
        
        $this->actingAs($user, 'sanctum');

        // Test recherche sans localisation (doit fonctionner)
        $response = $this->postJson('/api/v1/prospects/search', [
            'query' => 'restaurant',
            'filters' => [
                'sector' => 'alimentaire',
                'limit' => 10
            ],
            'sources' => ['demo']
        ]);

        $this->assertNotEquals(422, $response->getStatusCode());
    }
}