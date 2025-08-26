<?php

namespace App\__Infrastructure__\Services\Aggregation;

use App\__Infrastructure__\Services\External\GoogleMapsService;
use App\__Infrastructure__\Services\External\NominatimService;
use App\__Infrastructure__\Services\External\HunterService;
use App\__Infrastructure__\Services\Cache\ProspectSearchCacheService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Parallel;

/**
 * Service d'agrégation pour la recherche multi-source de prospects
 * Coordonne les appels aux différentes APIs et gère le cache
 */
class SearchAggregatorService
{
    private GoogleMapsService $googleMapsService;
    private NominatimService $nominatimService;
    private HunterService $hunterService;
    private ProspectSearchCacheService $cacheService;
    private ResultMergerService $mergerService;

    public function __construct(
        GoogleMapsService $googleMapsService,
        NominatimService $nominatimService,
        HunterService $hunterService,
        ProspectSearchCacheService $cacheService,
        ResultMergerService $mergerService
    ) {
        $this->googleMapsService = $googleMapsService;
        $this->nominatimService = $nominatimService;
        $this->hunterService = $hunterService;
        $this->cacheService = $cacheService;
        $this->mergerService = $mergerService;
    }

    /**
     * Effectue une recherche agrégée multi-source
     */
    public function search(string $query, array $filters = [], array $sources = []): array
    {
        $startTime = microtime(true);
        
        try {
            // Générer la clé de cache
            $cacheKey = $this->cacheService->generateSearchKey($query, $filters, $sources);
            
            // Vérifier le cache d'abord
            $cachedResults = $this->cacheService->getCachedSearchResults($cacheKey);
            if ($cachedResults) {
                Log::info('Résultats de recherche récupérés depuis le cache', [
                    'cache_key' => $cacheKey,
                    'age_seconds' => $cachedResults['cache_info']['age_seconds'] ?? 0
                ]);
                
                return $cachedResults;
            }
            
            // Sources par défaut si aucune spécifiée
            if (empty($sources)) {
                $sources = ['google_maps', 'nominatim'];
            }
            
            Log::info('Début de recherche agrégée', [
                'query' => $query,
                'sources' => $sources,
                'filters' => $filters
            ]);
            
            // Effectuer les recherches en parallèle
            $sourcesData = $this->performParallelSearches($query, $filters, $sources);
            
            // Calculer les statistiques par source
            $searchStats = $this->calculateSearchStats($sourcesData, microtime(true) - $startTime);
            
            // Fusionner et dédupliquer les résultats
            $aggregationResult = $this->mergerService->mergeAndDeduplicate($sourcesData);
            
            // Préparer les données de retour
            $result = [
                'query' => $query,
                'filters' => $filters,
                'sources_requested' => $sources,
                'sources_data' => $sourcesData,
                'aggregated_results' => $aggregationResult['merged'],
                'duplicates_found' => $aggregationResult['duplicates'],
                'deduplication_info' => $aggregationResult['deduplication_info'],
                'total_found' => count($aggregationResult['merged']),
                'search_stats' => $searchStats,
                'cache_info' => [
                    'cached_at' => time(),
                    'from_cache' => false
                ]
            ];
            
            // Mettre en cache le résultat
            $this->cacheService->cacheSearchResults($cacheKey, $result);
            
            // Enregistrer les statistiques par source
            $this->recordSourceStatistics($searchStats);
            
            Log::info('Recherche agrégée terminée', [
                'total_results' => $result['total_found'],
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
                'cache_key' => $cacheKey
            ]);
            
            return $result;
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la recherche agrégée', [
                'query' => $query,
                'sources' => $sources,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Retourner une réponse d'erreur structurée
            return [
                'query' => $query,
                'sources_requested' => $sources,
                'aggregated_results' => [],
                'total_found' => 0,
                'error' => $e->getMessage(),
                'search_stats' => []
            ];
        }
    }

    /**
     * Effectue les recherches sur toutes les sources en parallèle
     */
    private function performParallelSearches(string $query, array $filters, array $sources): array
    {
        $sourcesData = [];
        $tasks = [];
        
        // Préparer les tâches de recherche
        foreach ($sources as $source) {
            $tasks[$source] = function () use ($source, $query, $filters) {
                return $this->searchSingleSource($source, $query, $filters);
            };
        }
        
        try {
            // Exécuter en parallèle (si Laravel Octane est disponible)
            if (function_exists('\\Laravel\\Octane\\Facades\\Octane')) {
                $results = Parallel::run($tasks);
                
                foreach ($results as $source => $result) {
                    $sourcesData[$source] = $result;
                }
            } else {
                // Exécution séquentielle si pas de support parallèle
                foreach ($tasks as $source => $task) {
                    $sourcesData[$source] = $task();
                }
            }
            
        } catch (\Exception $e) {
            Log::error('Erreur lors des recherches parallèles', [
                'error' => $e->getMessage()
            ]);
            
            // Fallback : exécution séquentielle
            foreach ($tasks as $source => $task) {
                try {
                    $sourcesData[$source] = $task();
                } catch (\Exception $sourceError) {
                    Log::error("Erreur recherche source {$source}", [
                        'error' => $sourceError->getMessage()
                    ]);
                    
                    $sourcesData[$source] = [
                        'results' => [],
                        'count' => 0,
                        'error' => $sourceError->getMessage(),
                        'success' => false,
                        'response_time_ms' => 0
                    ];
                }
            }
        }
        
        return $sourcesData;
    }

    /**
     * Effectue une recherche sur une source spécifique
     */
    private function searchSingleSource(string $source, string $query, array $filters): array
    {
        $startTime = microtime(true);
        
        try {
            // Vérifier le cache spécifique à la source
            $cachedData = $this->cacheService->getCachedSourceData($source, $query, $filters);
            if ($cachedData) {
                return [
                    'results' => $cachedData['results'],
                    'count' => $cachedData['count'],
                    'source' => $source,
                    'cached' => true,
                    'success' => true,
                    'response_time_ms' => 0
                ];
            }
            
            // Effectuer la recherche selon la source
            $results = [];
            
            switch ($source) {
                case 'google_maps':
                    $results = $this->googleMapsService->searchPlaces($query, $filters);
                    break;
                    
                case 'nominatim':
                    $results = $this->nominatimService->search($query, $filters);
                    break;
                    
                case 'hunter':
                    // Pour Hunter, on a besoin d'un domaine, donc on skip si pas disponible
                    if (!empty($filters['domain'])) {
                        $emailData = $this->hunterService->findEmails($filters['domain']);
                        $results = $this->convertEmailDataToProspects($emailData, $filters['domain']);
                    }
                    break;
                    
                default:
                    Log::warning("Source de recherche inconnue: {$source}");
                    break;
            }
            
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);
            
            // Mettre en cache les résultats de la source
            $this->cacheService->cacheSourceData($source, $query, $filters, $results);
            
            $sourceData = [
                'results' => $results,
                'count' => count($results),
                'source' => $source,
                'cached' => false,
                'success' => true,
                'response_time_ms' => $responseTime
            ];
            
            Log::debug("Recherche source {$source} terminée", [
                'count' => count($results),
                'response_time_ms' => $responseTime
            ]);
            
            return $sourceData;
            
        } catch (\Exception $e) {
            Log::error("Erreur recherche source {$source}", [
                'error' => $e->getMessage(),
                'query' => $query
            ]);
            
            return [
                'results' => [],
                'count' => 0,
                'source' => $source,
                'cached' => false,
                'success' => false,
                'error' => $e->getMessage(),
                'response_time_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        }
    }

    /**
     * Enrichit les résultats avec des données supplémentaires
     */
    public function enrichResults(array $prospects): array
    {
        $enriched = [];
        
        foreach ($prospects as $prospect) {
            $enrichedProspect = $prospect;
            
            try {
                // Enrichissement désactivé (Clearbit supprimé)
                
                // Enrichissement via Hunter si on a un domaine
                if (!empty($prospect['domain'])) {
                    $hunterData = $this->hunterService->findEmails($prospect['domain']);
                    if (!empty($hunterData)) {
                        $enrichedProspect['emails'] = array_slice($hunterData, 0, 3); // Limiter à 3 emails
                    }
                }
                
            } catch (\Exception $e) {
                Log::warning('Erreur lors de l\'enrichissement du prospect', [
                    'prospect_id' => $prospect['id'] ?? 'unknown',
                    'error' => $e->getMessage()
                ]);
            }
            
            $enriched[] = $enrichedProspect;
        }
        
        return $enriched;
    }

    /**
     * Obtient les sources disponibles et leur statut
     */
    public function getAvailableSources(): array
    {
        return [
            'google_maps' => [
                'name' => 'Google Maps',
                'available' => $this->googleMapsService->isConfigured(),
                'description' => 'Entreprises locales et coordonnées GPS',
                'type' => 'geographic'
            ],
            'nominatim' => [
                'name' => 'OpenStreetMap',
                'available' => $this->nominatimService->isConfigured(),
                'description' => 'Données géographiques publiques',
                'type' => 'geographic'
            ],
            'hunter' => [
                'name' => 'Hunter.io',
                'available' => $this->hunterService->isConfigured(),
                'description' => 'Emails professionnels',
                'type' => 'contact'
            ]
        ];
    }

    /**
     * Calcule les statistiques de recherche
     */
    private function calculateSearchStats(array $sourcesData, float $totalTime): array
    {
        $stats = [
            'total_time_seconds' => round($totalTime, 3),
            'sources_used' => count($sourcesData),
            'sources_successful' => 0,
            'total_raw_results' => 0,
            'by_source' => []
        ];
        
        foreach ($sourcesData as $source => $data) {
            $stats['by_source'][$source] = [
                'success' => $data['success'] ?? false,
                'count' => $data['count'] ?? 0,
                'response_time_ms' => $data['response_time_ms'] ?? 0,
                'cached' => $data['cached'] ?? false,
                'error' => $data['error'] ?? null
            ];
            
            if ($data['success'] ?? false) {
                $stats['sources_successful']++;
            }
            
            $stats['total_raw_results'] += $data['count'] ?? 0;
        }
        
        return $stats;
    }

    /**
     * Enregistre les statistiques par source pour le monitoring
     */
    private function recordSourceStatistics(array $searchStats): void
    {
        foreach ($searchStats['by_source'] as $source => $stats) {
            $this->cacheService->cacheSourceStats($source, [
                'response_time' => $stats['response_time_ms'],
                'results_count' => $stats['count'],
                'error' => !$stats['success']
            ]);
        }
    }

    /**
     * Convertit les données email Hunter en format prospect
     */
    private function convertEmailDataToProspects(array $emailData, string $domain): array
    {
        $prospects = [];
        
        foreach ($emailData as $email) {
            if ($email['type'] === 'personal' && !empty($email['first_name']) && !empty($email['last_name'])) {
                $prospects[] = [
                    'name' => trim($email['first_name'] . ' ' . $email['last_name']),
                    'company' => ucfirst(str_replace(['.com', '.fr', '.org'], '', $domain)),
                    'email' => $email['email'],
                    'position' => $email['position'],
                    'phone' => $email['phone_number'],
                    'linkedin_url' => $email['linkedin'],
                    'source' => 'hunter',
                    'external_id' => md5($email['email'])
                ];
            }
        }
        
        return $prospects;
    }

    /**
     * Fusionne les données de deux prospects
     */
    private function mergeProspectData(array $base, array $additional): array
    {
        // Logique de fusion intelligente
        $merged = $base;
        
        foreach ($additional as $key => $value) {
            if (empty($merged[$key]) && !empty($value)) {
                $merged[$key] = $value;
            }
        }
        
        return $merged;
    }
}