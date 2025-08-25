<?php

namespace App\__Infrastructure__\Services\ProspectEnrichment;

use App\__Domain__\Data\Prospect\Factory as ProspectFactory;
use App\__Domain__\Data\Prospect\Model as ProspectModel;
use App\__Infrastructure__\Services\External\GoogleMapsService;
use App\__Infrastructure__\Services\WebEnrichmentService;
use App\__Infrastructure__\Services\Enrichment\EnrichmentEligibilityService;
use App\__Infrastructure__\Eloquent\ProspectEloquent;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/**
 * Service d'orchestration pour l'enrichissement des prospects
 */
class ProspectEnrichmentService
{
    private GoogleMapsService $googleMapsService;
    private WebEnrichmentService $webEnrichmentService;
    private EnrichmentEligibilityService $eligibilityService;

    public function __construct(
        GoogleMapsService $googleMapsService,
        WebEnrichmentService $webEnrichmentService,
        EnrichmentEligibilityService $eligibilityService
    ) {
        $this->googleMapsService = $googleMapsService;
        $this->webEnrichmentService = $webEnrichmentService;
        $this->eligibilityService = $eligibilityService;
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

    /**
     * Enrichit les contacts web d'un prospect (emails, téléphones, sites web)
     */
    public function enrichProspectWebContacts(ProspectModel $prospect, array $options = []): array
    {
        $forceMode = $options['force'] ?? false;
        $triggeredBy = $options['triggered_by'] ?? 'user';
        $userId = $options['user_id'] ?? null;

        try {
            // Vérification d'éligibilité
            if (!$forceMode) {
                $eligibility = $this->eligibilityService->isEligibleForEnrichment($prospect, $options);
                if (!$eligibility['is_eligible']) {
                    Log::info('Prospect not eligible for enrichment', [
                        'prospect_id' => $prospect->id,
                        'reason' => $eligibility['reason'],
                        'details' => $eligibility['reason_details']
                    ]);
                    
                    return [
                        'success' => false,
                        'reason' => 'not_eligible',
                        'eligibility' => $eligibility
                    ];
                }
            }

            $prospectName = $prospect->name;
            $prospectCompany = $prospect->company ?: '';

            // Si on n'a pas les deux informations principales, essayer d'utiliser d'autres données
            if (empty($prospectName) && empty($prospectCompany)) {
                Log::warning('Cannot enrich web contacts: missing prospect name and company', [
                    'prospect_id' => $prospect->id
                ]);
                return [
                    'success' => false,
                    'reason' => 'insufficient_data',
                    'message' => 'Missing prospect name and company'
                ];
            }

            // Démarrer l'historique d'enrichissement
            $historyId = $this->startEnrichmentHistory($prospect->id, $triggeredBy, $userId);
            
            // Marquer le prospect comme en cours d'enrichissement
            $this->updateProspectEnrichmentStatus($prospect->id, 'pending');

            // Ajouter des options basées sur les données du prospect
            $enrichmentOptions = array_merge($options, [
                'company_website' => $prospect->contactInfo['website'] ?? null,
                'urls_to_scrape' => $this->buildScrapeUrls($prospect),
                'max_contacts' => $options['max_contacts'] ?? 10
            ]);

            Log::info('Starting web contact enrichment', [
                'prospect_id' => $prospect->id,
                'prospect_name' => $prospectName,
                'prospect_company' => $prospectCompany,
                'history_id' => $historyId,
                'forced' => $forceMode,
                'triggered_by' => $triggeredBy
            ]);

            $webResult = $this->webEnrichmentService->enrichProspectContacts(
                $prospectName,
                $prospectCompany,
                $enrichmentOptions
            );

            if ($webResult->success && $webResult->hasValidContacts()) {
                $formattedContacts = $this->formatWebContactsForProspect($webResult->contacts);
                
                // Finaliser l'enrichissement avec succès
                $this->completeEnrichmentHistory($historyId, $formattedContacts, $webResult);
                $this->updateProspectAfterEnrichment($prospect->id, 'completed', $formattedContacts, $webResult);

                Log::info('Web contact enrichment successful', [
                    'prospect_id' => $prospect->id,
                    'contacts_found' => count($webResult->contacts),
                    'validation_score' => $webResult->validation->overallScore,
                    'history_id' => $historyId
                ]);

                return [
                    'success' => true,
                    'contacts' => $formattedContacts,
                    'metadata' => [
                        'execution_time_ms' => $webResult->executionTimeMs,
                        'services_used' => array_keys($webResult->metadata['services_results'] ?? []),
                        'validation_score' => $webResult->validation->overallScore
                    ]
                ];
                
            } else {
                // Finaliser l'enrichissement avec échec
                $errorMessage = $webResult->errorMessage ?? 'No valid contacts found';
                $this->failEnrichmentHistory($historyId, $errorMessage, $webResult);
                $this->updateProspectAfterEnrichment($prospect->id, 'failed', [], $webResult, $errorMessage);

                Log::info('Web contact enrichment returned no valid contacts', [
                    'prospect_id' => $prospect->id,
                    'success' => $webResult->success,
                    'error_message' => $errorMessage,
                    'history_id' => $historyId
                ]);

                return [
                    'success' => false,
                    'reason' => 'no_contacts_found',
                    'message' => $errorMessage
                ];
            }

        } catch (\Exception $e) {
            // Gestion des erreurs
            if (isset($historyId)) {
                $this->failEnrichmentHistory($historyId, $e->getMessage());
            }
            
            if (isset($prospect->id)) {
                $this->updateProspectAfterEnrichment($prospect->id, 'failed', [], null, $e->getMessage());
            }

            Log::error('Error enriching prospect web contacts', [
                'prospect_id' => $prospect->id ?? null,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'history_id' => $historyId ?? null
            ]);

            return [
                'success' => false,
                'reason' => 'enrichment_error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Construit une liste d'URLs à scraper basée sur les données du prospect
     */
    private function buildScrapeUrls(ProspectModel $prospect): array
    {
        $urls = [];

        // Site web de l'entreprise si disponible
        if (!empty($prospect->contactInfo['website'])) {
            $website = $prospect->contactInfo['website'];
            $urls[] = $website;
            
            // Essayer aussi la page contact
            $contactUrls = [
                rtrim($website, '/') . '/contact',
                rtrim($website, '/') . '/contact-us',
                rtrim($website, '/') . '/nous-contacter',
                rtrim($website, '/') . '/about',
                rtrim($website, '/') . '/about-us',
                rtrim($website, '/') . '/equipe',
                rtrim($website, '/') . '/team'
            ];
            
            $urls = array_merge($urls, array_slice($contactUrls, 0, 3)); // Limiter à 3 URLs supplémentaires
        }

        return array_unique($urls);
    }

    /**
     * Formate les contacts web pour l'intégration avec le prospect
     */
    private function formatWebContactsForProspect(array $webContacts): array
    {
        $formattedContacts = [
            'emails' => [],
            'phones' => [],
            'websites' => [],
            'social_media' => []
        ];

        foreach ($webContacts as $contact) {
            $contactData = [
                'value' => $contact->value,
                'confidence' => $contact->confidenceLevel,
                'score' => $contact->validationScore,
                'source' => $contact->context['source_url'] ?? 'web_enrichment',
                'found_via' => $contact->context['enrichment_service'] ?? 'unknown'
            ];

            switch ($contact->type) {
                case 'email':
                    $formattedContacts['emails'][] = $contactData;
                    break;
                case 'phone':
                    $formattedContacts['phones'][] = $contactData;
                    break;
                case 'website':
                    // Distinguer les réseaux sociaux des sites web
                    if (isset($contact->context['platform'])) {
                        $contactData['platform'] = $contact->context['platform'];
                        $formattedContacts['social_media'][] = $contactData;
                    } else {
                        $formattedContacts['websites'][] = $contactData;
                    }
                    break;
            }
        }

        // Trier par score décroissant
        foreach ($formattedContacts as $type => &$contacts) {
            usort($contacts, fn($a, $b) => $b['score'] <=> $a['score']);
        }

        return $formattedContacts;
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
            'google_maps' => [
                'name' => 'Google Maps',
                'available' => $demoMode || $this->googleMapsService->isConfigured(),
                'description' => 'Établissements référencés sur Google Maps' . ($demoMode ? ' (Mode démo)' : '')
            ],
            'web_enrichment' => [
                'name' => 'Web Contact Enrichment',
                'available' => $this->webEnrichmentService->isConfigured(),
                'description' => 'Enrichissement contacts via web scraping multi-sources (DuckDuckGo, Google Search, scraping direct)',
                'details' => $this->webEnrichmentService->getAvailableServices()
            ],
        ];
    }

    /**
     * Obtient les informations sur les services d'enrichissement web disponibles
     */
    public function getWebEnrichmentInfo(): array
    {
        return $this->webEnrichmentService->getServiceInfo();
    }

    /**
     * Teste les services d'enrichissement web
     */
    public function testWebEnrichmentServices(): array
    {
        return $this->webEnrichmentService->testServices();
    }

    /**
     * Enrichit plusieurs prospects par lot avec vérification d'éligibilité
     */
    public function bulkEnrichProspectWebContacts(array $prospectIds, array $options = []): array
    {
        $triggeredBy = $options['triggered_by'] ?? 'bulk';
        $userId = $options['user_id'] ?? null;
        $maxProcessing = $options['max_processing'] ?? 10;
        
        // Filtrer les prospects éligibles
        $eligibleProspects = $this->eligibilityService->getEligibleProspects($prospectIds, $options);
        
        $results = [
            'total_requested' => count($prospectIds),
            'eligible_count' => $eligibleProspects->count(),
            'processed' => [],
            'skipped' => [],
            'errors' => []
        ];

        Log::info('Starting bulk enrichment', [
            'total_requested' => $results['total_requested'],
            'eligible_count' => $results['eligible_count'],
            'triggered_by' => $triggeredBy
        ]);

        $processed = 0;
        foreach ($eligibleProspects as $prospectEloquent) {
            if ($processed >= $maxProcessing) {
                break;
            }

            try {
                $prospect = $prospectEloquent->toDomainModel();
                
                $enrichmentResult = $this->enrichProspectWebContacts($prospect, [
                    'triggered_by' => $triggeredBy,
                    'user_id' => $userId,
                    'force' => $options['force'] ?? false
                ]);

                if ($enrichmentResult['success']) {
                    $results['processed'][] = [
                        'prospect_id' => $prospect->id,
                        'contacts_found' => count($enrichmentResult['contacts'] ?? [])
                    ];
                } else {
                    $results['skipped'][] = [
                        'prospect_id' => $prospect->id,
                        'reason' => $enrichmentResult['reason'] ?? 'unknown'
                    ];
                }

                $processed++;
                
                // Délai entre les traitements pour éviter la surcharge
                if ($processed < $eligibleProspects->count()) {
                    sleep(1);
                }

            } catch (\Exception $e) {
                $results['errors'][] = [
                    'prospect_id' => $prospect->id ?? null,
                    'error' => $e->getMessage()
                ];
                Log::error('Bulk enrichment error', [
                    'prospect_id' => $prospect->id ?? null,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Bulk enrichment completed', $results);
        
        return $results;
    }

    /**
     * Obtient l'éligibilité d'enrichissement pour un prospect
     */
    public function getProspectEnrichmentEligibility(ProspectModel $prospect, array $options = []): array
    {
        return $this->eligibilityService->isEligibleForEnrichment($prospect, $options);
    }

    /**
     * Obtient l'historique d'enrichissement d'un prospect
     */
    public function getProspectEnrichmentHistory(int $prospectId, int $limit = 10): array
    {
        return DB::table('prospect_enrichment_history')
            ->where('prospect_id', $prospectId)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Blacklist un prospect pour l'enrichissement automatique
     */
    public function blacklistProspectEnrichment(int $prospectId, string $reason = null): bool
    {
        try {
            DB::table('prospects')
                ->where('id', $prospectId)
                ->update([
                    'enrichment_blacklisted_at' => now(),
                    'auto_enrich_enabled' => false,
                    'updated_at' => now()
                ]);

            Log::info('Prospect blacklisted for enrichment', [
                'prospect_id' => $prospectId,
                'reason' => $reason
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to blacklist prospect', [
                'prospect_id' => $prospectId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Active/désactive l'enrichissement automatique pour un prospect
     */
    public function toggleAutoEnrichment(int $prospectId, bool $enabled): bool
    {
        try {
            $updates = ['auto_enrich_enabled' => $enabled, 'updated_at' => now()];
            
            // Si on réactive, supprimer le blacklist
            if ($enabled) {
                $updates['enrichment_blacklisted_at'] = null;
            }

            DB::table('prospects')
                ->where('id', $prospectId)
                ->update($updates);

            Log::info('Auto-enrichment toggled', [
                'prospect_id' => $prospectId,
                'enabled' => $enabled
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to toggle auto-enrichment', [
                'prospect_id' => $prospectId,
                'enabled' => $enabled,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Obtient les statistiques d'éligibilité pour l'enrichissement
     */
    public function getEnrichmentEligibilityStats(int $userId = null): array
    {
        return $this->eligibilityService->getEligibilityStats($userId);
    }

    /**
     * Démarre l'historique d'enrichissement
     */
    private function startEnrichmentHistory(int $prospectId, string $triggeredBy, int $userId = null): int
    {
        return DB::table('prospect_enrichment_history')->insertGetId([
            'prospect_id' => $prospectId,
            'enrichment_type' => 'web',
            'status' => 'started',
            'triggered_by' => $triggeredBy,
            'triggered_by_user_id' => $userId,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    /**
     * Complète l'historique d'enrichissement avec succès
     */
    private function completeEnrichmentHistory(int $historyId, array $contacts, $webResult = null): void
    {
        $servicesUsed = [];
        if ($webResult && isset($webResult->metadata['services_results'])) {
            $servicesUsed = array_keys($webResult->metadata['services_results']);
        }

        DB::table('prospect_enrichment_history')
            ->where('id', $historyId)
            ->update([
                'status' => 'completed',
                'contacts_found' => json_encode($contacts),
                'execution_time_ms' => $webResult->executionTimeMs ?? null,
                'services_used' => json_encode($servicesUsed),
                'updated_at' => now()
            ]);
    }

    /**
     * Marque l'historique d'enrichissement comme échoué
     */
    private function failEnrichmentHistory(int $historyId, string $errorMessage, $webResult = null): void
    {
        $servicesUsed = [];
        if ($webResult && isset($webResult->metadata['services_results'])) {
            $servicesUsed = array_keys($webResult->metadata['services_results']);
        }

        DB::table('prospect_enrichment_history')
            ->where('id', $historyId)
            ->update([
                'status' => 'failed',
                'error_message' => $errorMessage,
                'execution_time_ms' => $webResult->executionTimeMs ?? null,
                'services_used' => json_encode($servicesUsed),
                'updated_at' => now()
            ]);
    }

    /**
     * Met à jour le statut d'enrichissement du prospect
     */
    private function updateProspectEnrichmentStatus(int $prospectId, string $status): void
    {
        $updates = [
            'enrichment_status' => $status,
            'updated_at' => now()
        ];

        if ($status === 'pending') {
            // Incrémenter le compteur de tentatives
            DB::table('prospects')
                ->where('id', $prospectId)
                ->increment('enrichment_attempts');
        }

        DB::table('prospects')
            ->where('id', $prospectId)
            ->update($updates);
    }

    /**
     * Met à jour le prospect après enrichissement
     */
    private function updateProspectAfterEnrichment(
        int $prospectId, 
        string $status, 
        array $enrichmentData = [], 
        $webResult = null,
        string $errorMessage = null
    ): void {
        $updates = [
            'enrichment_status' => $status,
            'last_enrichment_at' => now(),
            'updated_at' => now()
        ];

        if (!empty($enrichmentData)) {
            $updates['enrichment_data'] = json_encode($enrichmentData);
        }

        if ($webResult && $webResult->validation) {
            $updates['enrichment_score'] = $webResult->validation->overallScore;
        }

        // Recalculer et mettre à jour le score de complétude
        $completenessScore = $this->eligibilityService->updateCompletenessScore($prospectId);
        $updates['data_completeness_score'] = $completenessScore;

        DB::table('prospects')
            ->where('id', $prospectId)
            ->update($updates);
    }
}