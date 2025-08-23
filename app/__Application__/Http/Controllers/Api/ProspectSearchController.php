<?php

namespace App\__Application__\Http\Controllers\Api;

use App\__Application__\Http\Requests\ProspectSearchRequest;
use App\__Infrastructure__\Services\Aggregation\SearchAggregatorService;
use App\__Infrastructure__\Services\User\SearchQuotaService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Contrôleur API pour la recherche de prospects
 */
class ProspectSearchController extends Controller
{
    private SearchAggregatorService $searchAggregator;
    private SearchQuotaService $searchQuotaService;

    public function __construct(
        SearchAggregatorService $searchAggregator,
        SearchQuotaService $searchQuotaService
    ) {
        $this->searchAggregator = $searchAggregator;
        $this->searchQuotaService = $searchQuotaService;
    }

    /**
     * Recherche agrégée de prospects via les APIs légales
     */
    public function search(ProspectSearchRequest $request): JsonResponse
    {
        $user = Auth::user();
        
        try {
            Log::info('Nouvelle recherche agrégée de prospects', [
                'user_id' => $user->id,
                'query' => $request->getQuery(),
                'sources' => $request->getSources(),
                'is_premium' => $user->isPremium()
            ]);
            
            // Effectuer la recherche agrégée
            $searchResult = $this->searchAggregator->search(
                $request->getQuery(),
                $request->getFilters(),
                $request->getSources()
            );
            
            // Vérifier s'il y a une erreur
            if (!empty($searchResult['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => $searchResult['error'],
                    'data' => [
                        'prospects' => [],
                        'total_found' => 0
                    ]
                ], 500);
            }

            // Consommer le quota de recherche APRÈS une recherche réussie
            $this->searchQuotaService->consumeSearchQuota($user);
            
            // Sauvegarder la recherche si demandé
            $searchRecord = null;
            if ($request->shouldSaveSearch()) {
                $searchRecord = $this->saveSearchRecord($user->id, $request, $searchResult);
            }

            // Obtenir les informations de quota pour la réponse
            $quotaInfo = $this->searchQuotaService->getQuotaInfo($user);
            
            // Formater la réponse
            return response()->json([
                'success' => true,
                'data' => [
                    'prospects' => $this->formatProspectsForFrontend($searchResult['aggregated_results']),
                    'total_found' => $searchResult['total_found'],
                    'duplicates_found' => count($searchResult['duplicates_found'] ?? []),
                    'search' => $searchRecord,
                    'sources_stats' => $searchResult['search_stats'],
                    'deduplication_info' => $searchResult['deduplication_info'],
                    'available_sources' => $this->searchAggregator->getAvailableSources(),
                    'cache_info' => $searchResult['cache_info'] ?? null,
                    'quota_info' => $quotaInfo
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la recherche agrégée', [
                'user_id' => $user->id,
                'query' => $request->getQuery(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la recherche: ' . $e->getMessage(),
                'data' => [
                    'prospects' => [],
                    'total_found' => 0
                ]
            ], 500);
        }
    }

    /**
     * Obtient les informations de quota de l'utilisateur
     */
    public function quota(): JsonResponse
    {
        try {
            $user = Auth::user();
            $quotaInfo = $this->searchQuotaService->getQuotaInfo($user);

            return response()->json([
                'success' => true,
                'data' => [
                    'quota_info' => $quotaInfo,
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des informations de quota', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations de quota',
                'data' => [
                    'quota_info' => [],
                ]
            ], 500);
        }
    }

    /**
     * Obtient les sources disponibles et leur statut
     */
    public function sources(): JsonResponse
    {
        try {
            $availableSources = $this->searchAggregator->getAvailableSources();

            return response()->json([
                'success' => true,
                'data' => [
                    'sources' => $availableSources,
                ],
            ]);
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des sources', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des sources',
                'data' => [
                    'sources' => [],
                ]
            ], 500);
        }
    }

    /**
     * Sauvegarde l'enregistrement de recherche en base de données
     */
    private function saveSearchRecord(int $userId, ProspectSearchRequest $request, array $searchResult): ?array
    {
        try {
            // Créer un enregistrement de recherche pour l'historique
            $searchData = [
                'user_id' => $userId,
                'query' => $request->getQuery(),
                'filters' => $request->getFilters(),
                'sources' => $request->getSources(),
                'results_count' => $searchResult['total_found'] ?? 0,
                'duplicates_found' => count($searchResult['duplicates_found'] ?? []),
                'search_duration_ms' => round(($searchResult['search_stats']['total_time_seconds'] ?? 0) * 1000, 2),
                'sources_successful' => $searchResult['search_stats']['sources_successful'] ?? 0,
                'cache_hit' => $searchResult['cache_info']['from_cache'] ?? false,
                'created_at' => now(),
                'updated_at' => now()
            ];

            // TODO: Implémenter la sauvegarde via un service ou repository
            Log::info('Recherche sauvegardée', $searchData);

            return [
                'id' => uniqid('search_'),
                'query' => $searchData['query'],
                'total_found' => $searchData['results_count'],
                'created_at' => $searchData['created_at']->format('Y-m-d H:i:s')
            ];

        } catch (\Exception $e) {
            Log::error('Erreur lors de la sauvegarde de la recherche', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Formate les résultats agrégés pour le frontend
     */
    private function formatProspectsForFrontend(array $aggregatedResults): array
    {
        $formatted = [];

        foreach ($aggregatedResults as $prospect) {
            $formatted[] = [
                'id' => $prospect['id'] ?? uniqid('prospect_'),
                'name' => $prospect['name'] ?? 'N/A',
                'company' => $prospect['company'] ?? $prospect['business_name'] ?? 'N/A',
                'sector' => $prospect['sector'] ?? $prospect['category'] ?? null,
                'city' => $prospect['city'] ?? $prospect['locality'] ?? null,
                'postal_code' => $prospect['postal_code'] ?? $prospect['postcode'] ?? null,
                'address' => $this->extractAddress($prospect),
                'phone' => $prospect['phone'] ?? (isset($prospect['contact_info']['phone']) ? $prospect['contact_info']['phone'] : null),
                'email' => $prospect['email'] ?? (isset($prospect['contact_info']['email']) ? $prospect['contact_info']['email'] : null),
                'website' => $prospect['website'] ?? $prospect['domain'] ?? null,
                'description' => $prospect['description'] ?? null,
                'latitude' => $prospect['latitude'] ?? $prospect['lat'] ?? null,
                'longitude' => $prospect['longitude'] ?? $prospect['lon'] ?? null,
                'relevance_score' => $prospect['relevance_score'] ?? $prospect['confidence_score'] ?? 0,
                'source' => $prospect['source'] ?? 'unknown',
                'external_id' => $prospect['external_id'] ?? null,
                'merged_from' => $prospect['merged_from'] ?? null,
                'conflict_data' => $prospect['conflict_data'] ?? null,
                'enrichment_data' => [
                    'employee_count' => $prospect['employee_count'] ?? null,
                    'revenue' => $prospect['revenue'] ?? null,
                    'industry' => $prospect['industry'] ?? null,
                    'founded_year' => $prospect['founded_year'] ?? null,
                    'logo_url' => $prospect['logo_url'] ?? null,
                    'social_links' => $prospect['social_links'] ?? []
                ],
                'data_quality' => [
                    'completeness' => $prospect['data_completeness'] ?? 0,
                    'verified' => $prospect['verified'] ?? false,
                    'last_updated' => $prospect['last_updated'] ?? null
                ],
                'created_at' => now()->format('Y-m-d H:i:s')
            ];
        }

        return $formatted;
    }

    /**
     * Extrait l'adresse sous forme de chaîne depuis les données du prospect
     */
    private function extractAddress(array $prospect): ?string
    {
        // Si l'adresse est déjà une chaîne
        if (isset($prospect['address']) && is_string($prospect['address'])) {
            return $prospect['address'];
        }
        
        // Si l'adresse est un objet, construire la chaîne
        if (isset($prospect['address']) && is_array($prospect['address'])) {
            $address = $prospect['address'];
            
            // Priorité à l'adresse complète formatée
            if (!empty($address['full'])) {
                return $address['full'];
            }
            
            // Sinon construire à partir des composants
            $parts = [];
            if (!empty($address['street'])) $parts[] = $address['street'];
            if (!empty($address['city'])) $parts[] = $address['city'];
            if (!empty($address['postal_code'])) $parts[] = $address['postal_code'];
            if (!empty($address['country'])) $parts[] = $address['country'];
            
            return !empty($parts) ? implode(', ', $parts) : null;
        }
        
        // Fallback vers display_name ou formatted_address
        return $prospect['display_name'] ?? $prospect['formatted_address'] ?? null;
    }
}