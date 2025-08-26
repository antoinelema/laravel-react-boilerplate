<?php

namespace App\__Infrastructure__\Services\Cache;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service de cache spécialisé pour les recherches de prospects
 * Utilise Redis pour stocker temporairement les résultats d'agrégation
 */
class ProspectSearchCacheService
{
    private const CACHE_PREFIX = 'prospect_search';
    private const DEFAULT_TTL = 3600; // 1 heure
    private const DEDUPLICATION_TTL = 1800; // 30 minutes

    /**
     * Génère une clé de cache unique pour une recherche
     */
    public function generateSearchKey(string $query, array $filters, array $sources): string
    {
        $data = [
            'query' => trim(strtolower($query)),
            'filters' => $this->normalizeFilters($filters),
            'sources' => array_values(array_unique($sources))
        ];
        
        $hash = md5(serialize($data));
        
        return self::CACHE_PREFIX . ':search:' . $hash;
    }

    /**
     * Met en cache les résultats d'une recherche agrégée
     */
    public function cacheSearchResults(string $cacheKey, array $data, ?int $ttl = null): bool
    {
        try {
            $ttl = $ttl ?? self::DEFAULT_TTL;
            
            $cacheData = [
                'timestamp' => time(),
                'query' => $data['query'] ?? '',
                'sources_data' => $data['sources_data'] ?? [],
                'aggregated_results' => $data['aggregated_results'] ?? [],
                'total_found' => $data['total_found'] ?? 0,
                'search_stats' => $data['search_stats'] ?? [],
                'deduplication_info' => $data['deduplication_info'] ?? [],
            ];

            return Cache::put($cacheKey, $cacheData, $ttl);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise en cache des résultats de recherche', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Récupère les résultats de recherche depuis le cache
     */
    public function getCachedSearchResults(string $cacheKey): ?array
    {
        try {
            $cachedData = Cache::get($cacheKey);
            
            if (!$cachedData) {
                return null;
            }
            
            // Vérifier la validité des données
            if (!isset($cachedData['timestamp']) || !isset($cachedData['aggregated_results'])) {
                $this->invalidateSearch($cacheKey);
                return null;
            }
            
            // Ajouter des métadonnées de cache
            $cachedData['cache_info'] = [
                'cached_at' => $cachedData['timestamp'],
                'age_seconds' => time() - $cachedData['timestamp'],
                'from_cache' => true
            ];
            
            return $cachedData;

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du cache de recherche', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Met en cache les données brutes d'une source spécifique
     */
    public function cacheSourceData(string $source, string $query, array $filters, array $data, ?int $ttl = null): bool
    {
        try {
            $cacheKey = $this->generateSourceKey($source, $query, $filters);
            $ttl = $ttl ?? self::DEFAULT_TTL;
            
            $sourceData = [
                'source' => $source,
                'query' => $query,
                'filters' => $filters,
                'results' => $data,
                'count' => count($data),
                'cached_at' => time()
            ];

            return Cache::put($cacheKey, $sourceData, $ttl);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise en cache des données source', [
                'source' => $source,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Récupère les données d'une source depuis le cache
     */
    public function getCachedSourceData(string $source, string $query, array $filters): ?array
    {
        try {
            $cacheKey = $this->generateSourceKey($source, $query, $filters);
            return Cache::get($cacheKey);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération du cache source', [
                'source' => $source,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Met en cache les informations de déduplication
     */
    public function cacheDeduplicationData(string $sessionId, array $duplicates, array $mergedResults): bool
    {
        try {
            $cacheKey = self::CACHE_PREFIX . ':dedup:' . $sessionId;
            
            $deduplicationData = [
                'session_id' => $sessionId,
                'duplicates' => $duplicates,
                'merged_results' => $mergedResults,
                'created_at' => time()
            ];

            return Cache::put($cacheKey, $deduplicationData, self::DEDUPLICATION_TTL);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise en cache des données de déduplication', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Récupère les données de déduplication
     */
    public function getDeduplicationData(string $sessionId): ?array
    {
        try {
            $cacheKey = self::CACHE_PREFIX . ':dedup:' . $sessionId;
            return Cache::get($cacheKey);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des données de déduplication', [
                'session_id' => $sessionId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Met en cache les statistiques de performance des sources
     */
    public function cacheSourceStats(string $source, array $stats): bool
    {
        try {
            $cacheKey = self::CACHE_PREFIX . ':stats:' . $source . ':' . date('Y-m-d-H');
            
            $existingStats = Cache::get($cacheKey, [
                'requests' => 0,
                'total_response_time' => 0,
                'total_results' => 0,
                'errors' => 0
            ]);
            
            $updatedStats = [
                'requests' => $existingStats['requests'] + 1,
                'total_response_time' => $existingStats['total_response_time'] + ($stats['response_time'] ?? 0),
                'total_results' => $existingStats['total_results'] + ($stats['results_count'] ?? 0),
                'errors' => $existingStats['errors'] + ($stats['error'] ? 1 : 0),
                'last_request' => time()
            ];
            
            // Calculer les moyennes
            $updatedStats['avg_response_time'] = $updatedStats['total_response_time'] / $updatedStats['requests'];
            $updatedStats['avg_results'] = $updatedStats['total_results'] / $updatedStats['requests'];
            $updatedStats['error_rate'] = ($updatedStats['errors'] / $updatedStats['requests']) * 100;

            return Cache::put($cacheKey, $updatedStats, 86400); // 24h

        } catch (\Exception $e) {
            Log::error('Erreur lors de la mise en cache des statistiques source', [
                'source' => $source,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Récupère les statistiques de performance d'une source
     */
    public function getSourceStats(string $source): ?array
    {
        try {
            $cacheKey = self::CACHE_PREFIX . ':stats:' . $source . ':' . date('Y-m-d-H');
            return Cache::get($cacheKey);

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques source', [
                'source' => $source,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Invalide le cache d'une recherche spécifique
     */
    public function invalidateSearch(string $cacheKey): bool
    {
        try {
            return Cache::forget($cacheKey);

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'invalidation du cache de recherche', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Invalide tous les caches de recherche pour un utilisateur
     */
    public function invalidateUserSearches(int $userId): bool
    {
        try {
            $pattern = self::CACHE_PREFIX . ':search:*';
            $keys = [];
            
            // Récupérer toutes les clés correspondantes (attention: coûteux sur Redis)
            $cursor = 0;
            do {
                $scan = Cache::getRedis()->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);
                $cursor = $scan[0];
                $keys = array_merge($keys, $scan[1]);
            } while ($cursor != 0);
            
            if (!empty($keys)) {
                foreach ($keys as $key) {
                    Cache::forget($key);
                }
            }
            
            return true;

        } catch (\Exception $e) {
            Log::error('Erreur lors de l\'invalidation des caches utilisateur', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Nettoie le cache (supprime les entrées expirées)
     */
    public function cleanupCache(): int
    {
        try {
            $cleaned = 0;
            $pattern = self::CACHE_PREFIX . ':*';
            $keys = [];
            
            // Récupérer toutes les clés
            $cursor = 0;
            do {
                $scan = Cache::getRedis()->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);
                $cursor = $scan[0];
                $keys = array_merge($keys, $scan[1]);
            } while ($cursor != 0);
            
            foreach ($keys as $key) {
                $ttl = Cache::getRedis()->ttl($key);
                
                // Si la clé est expirée ou va expirer dans moins d'une minute
                if ($ttl <= 60) {
                    Cache::forget($key);
                    $cleaned++;
                }
            }
            
            Log::info('Nettoyage du cache de recherche terminé', [
                'keys_cleaned' => $cleaned
            ]);
            
            return $cleaned;

        } catch (\Exception $e) {
            Log::error('Erreur lors du nettoyage du cache', [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Obtient les statistiques générales du cache
     */
    public function getCacheStats(): array
    {
        try {
            $stats = [
                'total_keys' => 0,
                'search_keys' => 0,
                'source_keys' => 0,
                'dedup_keys' => 0,
                'stats_keys' => 0,
                'memory_usage' => 0,
                'hit_rate' => 0
            ];
            
            $pattern = self::CACHE_PREFIX . ':*';
            $keys = [];
            
            $cursor = 0;
            do {
                $scan = Cache::getRedis()->scan($cursor, ['MATCH' => $pattern, 'COUNT' => 100]);
                $cursor = $scan[0];
                $keys = array_merge($keys, $scan[1]);
            } while ($cursor != 0);
            
            $stats['total_keys'] = count($keys);
            
            foreach ($keys as $key) {
                if (strpos($key, ':search:') !== false) {
                    $stats['search_keys']++;
                } elseif (strpos($key, ':source:') !== false) {
                    $stats['source_keys']++;
                } elseif (strpos($key, ':dedup:') !== false) {
                    $stats['dedup_keys']++;
                } elseif (strpos($key, ':stats:') !== false) {
                    $stats['stats_keys']++;
                }
            }
            
            // Obtenir les informations Redis
            $info = Cache::getRedis()->info('memory');
            $stats['memory_usage'] = $info['used_memory'] ?? 0;
            
            return $stats;

        } catch (\Exception $e) {
            Log::error('Erreur lors de la récupération des statistiques de cache', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'total_keys' => 0,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Normalise les filtres pour la génération de clés cohérentes
     */
    private function normalizeFilters(array $filters): array
    {
        $normalized = [];
        
        foreach ($filters as $key => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                if (is_string($value)) {
                    $normalized[$key] = trim(strtolower($value));
                } elseif (is_array($value)) {
                    $normalized[$key] = array_map('strtolower', array_filter($value));
                    sort($normalized[$key]);
                } else {
                    $normalized[$key] = $value;
                }
            }
        }
        
        ksort($normalized);
        
        return $normalized;
    }

    /**
     * Génère une clé de cache pour les données d'une source spécifique
     */
    private function generateSourceKey(string $source, string $query, array $filters): string
    {
        $data = [
            'source' => $source,
            'query' => trim(strtolower($query)),
            'filters' => $this->normalizeFilters($filters)
        ];
        
        $hash = md5(serialize($data));
        
        return self::CACHE_PREFIX . ':source:' . $source . ':' . $hash;
    }
}