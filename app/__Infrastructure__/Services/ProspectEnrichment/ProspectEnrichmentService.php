<?php

namespace App\__Infrastructure__\Services\ProspectEnrichment;

use App\__Domain__\Data\Prospect\Factory as ProspectFactory;
use App\__Domain__\Data\Prospect\Model as ProspectModel;
use App\__Infrastructure__\Services\External\GoogleMapsService;
use Illuminate\Support\Facades\Log;

/**
 * Service d'orchestration pour l'enrichissement des prospects
 */
class ProspectEnrichmentService
{
    private GoogleMapsService $googleMapsService;

    public function __construct(
        GoogleMapsService $googleMapsService
    ) {
        $this->googleMapsService = $googleMapsService;
    }

    /**
     * Recherche des prospects selon des critères via toutes les sources disponibles
     */
    public function searchProspects(int $userId, string $query, array $filters = [], array $sources = []): array
    {
        $allResults = [];
        $enabledSources = empty($sources) ? ['google_maps'] : $sources;

        foreach ($enabledSources as $source) {
            try {
                $results = match ($source) {
                    'google_maps' => $this->searchFromGoogleMaps($query, $filters),
                    default => []
                };

                foreach ($results as $result) {
                    $prospect = ProspectFactory::createFromApiData($result, $userId, $source);
                    $allResults[] = $prospect;
                }

            } catch (\Exception $e) {
                Log::error("Error searching from {$source}", [
                    'message' => $e->getMessage(),
                    'query' => $query,
                    'filters' => $filters
                ]);
            }
        }

        // Dédoublonnage basé sur les noms et adresses similaires
        $deduplicatedResults = $this->deduplicateProspects($allResults);

        // Tri par score de pertinence décroissant
        usort($deduplicatedResults, fn($a, $b) => $b->relevanceScore <=> $a->relevanceScore);

        return $deduplicatedResults;
    }

    /**
     * Enrichit un prospect existant avec des données complémentaires
     */
    public function enrichProspect(ProspectModel $prospect): ProspectModel
    {
        try {
            // Tentative d'enrichissement via Google Maps si on a une adresse
            if (!empty($prospect->address) || !empty($prospect->city)) {
                $enrichedData = $this->enrichFromGoogleMaps($prospect);
                if ($enrichedData) {
                    return $this->mergeEnrichmentData($prospect, $enrichedData);
                }
            }


        } catch (\Exception $e) {
            Log::error('Error enriching prospect', [
                'prospect_id' => $prospect->id,
                'message' => $e->getMessage()
            ]);
        }

        return $prospect;
    }


    private function searchFromGoogleMaps(string $query, array $filters): array
    {
        $demoMode = config('app.external_services_demo_mode', true);
        
        if (!$demoMode && !$this->googleMapsService->isConfigured()) {
            Log::warning('Google Maps service not configured and demo mode disabled');
            return [];
        }

        return $this->googleMapsService->searchPlaces($query, $filters);
    }

    private function enrichFromGoogleMaps(ProspectModel $prospect): ?array
    {
        if (!$this->googleMapsService->isConfigured()) {
            return null;
        }

        $searchQuery = $this->buildSearchQuery($prospect);
        $results = $this->googleMapsService->searchPlaces($searchQuery, [
            'location' => $prospect->city,
            'radius' => 1000
        ]);

        // Prendre le premier résultat qui correspond le mieux
        return $results[0] ?? null;
    }


    private function buildSearchQuery(ProspectModel $prospect): string
    {
        $parts = [];
        
        if (!empty($prospect->company)) {
            $parts[] = $prospect->company;
        } elseif (!empty($prospect->name)) {
            $parts[] = $prospect->name;
        }

        if (!empty($prospect->sector)) {
            $parts[] = $prospect->sector;
        }

        return implode(' ', $parts);
    }

    private function mergeEnrichmentData(ProspectModel $prospect, array $enrichedData): ProspectModel
    {
        // Fusionne les nouvelles données sans écraser les données existantes importantes
        $contactInfo = $prospect->contactInfo;
        
        if (empty($contactInfo['phone']) && !empty($enrichedData['phone'])) {
            $contactInfo['phone'] = $enrichedData['phone'];
        }

        if (empty($contactInfo['website']) && !empty($enrichedData['website'])) {
            $contactInfo['website'] = $enrichedData['website'];
        }

        if (empty($contactInfo['email']) && !empty($enrichedData['email'])) {
            $contactInfo['email'] = $enrichedData['email'];
        }

        // Création d'un nouveau prospect avec les données enrichies
        return new ProspectModel(
            id: $prospect->id,
            userId: $prospect->userId,
            name: $prospect->name,
            company: $prospect->company ?: $enrichedData['company'],
            sector: $prospect->sector ?: $enrichedData['sector'],
            city: $prospect->city ?: $enrichedData['city'],
            postalCode: $prospect->postalCode ?: $enrichedData['postal_code'],
            address: $prospect->address ?: $enrichedData['address']['full'],
            contactInfo: $contactInfo,
            description: $prospect->description ?: $enrichedData['description'],
            relevanceScore: max($prospect->relevanceScore, ProspectFactory::createFromApiData($enrichedData, $prospect->userId, $prospect->source ?? 'enrichment')->relevanceScore),
            status: $prospect->status,
            source: $prospect->source,
            externalId: $prospect->externalId,
            rawData: array_merge($prospect->rawData, ['enrichment_data' => $enrichedData]),
            createdAt: $prospect->createdAt,
            updatedAt: $prospect->updatedAt
        );
    }

    private function deduplicateProspects(array $prospects): array
    {
        $unique = [];
        $seen = [];

        foreach ($prospects as $prospect) {
            $key = $this->generateDuplicateKey($prospect);
            
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $prospect;
            }
        }

        return $unique;
    }

    private function generateDuplicateKey(ProspectModel $prospect): string
    {
        $parts = [
            strtolower(trim($prospect->name)),
            strtolower(trim($prospect->city ?? '')),
            strtolower(trim($prospect->postalCode ?? ''))
        ];

        return md5(implode('|', array_filter($parts)));
    }

    /**
     * Obtient les sources disponibles et leur statut
     */
    public function getAvailableSources(): array
    {
        $demoMode = config('app.external_services_demo_mode', true);
        
        return [
            'pages_jaunes' => [
                'name' => 'Pages Jaunes',
                'available' => $demoMode || $this->pagesJaunesService->isConfigured(),
                'description' => 'Annuaire professionnel français' . ($demoMode ? ' (Mode démo)' : '')
            ],
            'google_maps' => [
                'name' => 'Google Maps',
                'available' => $demoMode || $this->googleMapsService->isConfigured(),
                'description' => 'Établissements référencés sur Google Maps' . ($demoMode ? ' (Mode démo)' : '')
            ],
        ];
    }
}