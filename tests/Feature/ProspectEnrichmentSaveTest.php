<?php

namespace Tests\Feature;

use Tests\TestCase;
use Tests\Concerns\ResetsTransactions;
use App\__Infrastructure__\Eloquent\ProspectEloquent;
use App\__Infrastructure__\Eloquent\UserEloquent;
use Illuminate\Support\Facades\Log;

class ProspectEnrichmentSaveTest extends TestCase
{
    use ResetsTransactions;

    /** @test */
    public function it_can_manually_update_prospect_contact_info()
    {
        // Créer un utilisateur premium pour éviter les limitations
        $user = UserEloquent::factory()->create([
            'subscription_type' => 'premium'
        ]);

        // Créer un prospect de test sans informations de contact
        $prospect = ProspectEloquent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Restaurant Test',
            'company' => 'Ma Pizzeria',
            'contact_info' => [],
            'enrichment_status' => 'never',
        ]);

        // Simuler une mise à jour manuelle des informations de contact
        $enrichedContactInfo = [
            'email' => 'contact@ma-pizzeria.fr',
            'phone' => '+33142345678',
            'website' => 'https://ma-pizzeria.fr'
        ];

        // Mettre à jour le prospect avec les nouvelles informations
        $prospect->update([
            'contact_info' => $enrichedContactInfo,
            'enrichment_status' => 'completed',
            'enrichment_score' => 85.5,
            'data_completeness_score' => 90,
            'last_enrichment_at' => now()
        ]);

        // Vérifier que la mise à jour a réussi
        $prospect->refresh();
        
        $this->assertEquals('contact@ma-pizzeria.fr', $prospect->contact_info['email']);
        $this->assertEquals('+33142345678', $prospect->contact_info['phone']);
        $this->assertEquals('https://ma-pizzeria.fr', $prospect->contact_info['website']);
        
        $this->assertEquals('completed', $prospect->enrichment_status);
        $this->assertEquals(85.5, $prospect->enrichment_score);
        $this->assertEquals(90, $prospect->data_completeness_score);
        $this->assertNotNull($prospect->last_enrichment_at);
        
        Log::info('Test passed: Prospect contact info updated successfully', [
            'prospect_id' => $prospect->id,
            'contact_info' => $prospect->contact_info
        ]);
    }

    /** @test */
    public function it_preserves_existing_contact_info_when_updating()
    {
        $user = UserEloquent::factory()->create([
            'subscription_type' => 'premium'
        ]);

        // Créer un prospect avec des informations de contact existantes
        $prospect = ProspectEloquent::factory()->create([
            'user_id' => $user->id,
            'name' => 'Restaurant Test',
            'company' => 'Ma Pizzeria',
            'contact_info' => [
                'email' => 'direction@ma-pizzeria.fr',
                'phone' => '+33987654321'
            ],
        ]);

        // Ajouter seulement le site web sans écraser l'existant
        $currentContactInfo = $prospect->contact_info;
        $currentContactInfo['website'] = 'https://ma-pizzeria.fr';

        $prospect->update([
            'contact_info' => $currentContactInfo,
        ]);

        $prospect->refresh();
        
        // Vérifier que les informations existantes sont préservées
        $this->assertEquals('direction@ma-pizzeria.fr', $prospect->contact_info['email']);
        $this->assertEquals('+33987654321', $prospect->contact_info['phone']);
        $this->assertEquals('https://ma-pizzeria.fr', $prospect->contact_info['website']);
    }
}