<?php

namespace App\__Infrastructure__\Services;

use App\__Domain__\Data\Enrichment\WebScrapingResult;
use App\__Domain__\Data\Enrichment\ValidationResult;
use App\__Infrastructure__\Services\External\DuckDuckGoService;
use App\__Infrastructure__\Services\External\GoogleSearchService;
use App\__Infrastructure__\Services\External\UniversalScraperService;
use App\__Infrastructure__\Services\Validation\RuleBasedValidationStrategy;
use Illuminate\Support\Facades\Log;

class WebEnrichmentService
{
    private DuckDuckGoService $duckDuckGoService;
    private GoogleSearchService $googleSearchService;
    private UniversalScraperService $universalScraperService;
    private RuleBasedValidationStrategy $validationStrategy;
    
    private array $config;
    private array $enabledServices;

    public function __construct(
        DuckDuckGoService $duckDuckGoService,
        GoogleSearchService $googleSearchService,
        UniversalScraperService $universalScraperService,
        RuleBasedValidationStrategy $validationStrategy
    ) {
        $this->duckDuckGoService = $duckDuckGoService;
        $this->googleSearchService = $googleSearchService;
        $this->universalScraperService = $universalScraperService;
        $this->validationStrategy = $validationStrategy;
        
        $this->config = config('services.web_enrichment', []);
        $this->initializeEnabledServices();
    }

    private function initializeEnabledServices(): void
    {
        $this->enabledServices = [
            'duckduckgo' => $this->config['enable_duckduckgo'] ?? true,
            'google_search' => $this->config['enable_google_search'] ?? false, // Nécessite Selenium
            'universal_scraper' => $this->config['enable_universal_scraper'] ?? true,
        ];
    }

    public function enrichProspectContacts(
        string $prospectName,
        string $prospectCompany,
        array $options = []
    ): WebScrapingResult {
        $startTime = microtime(true);
        
        try {
            Log::info('Starting web enrichment for prospect', [
                'prospect_name' => $prospectName,
                'prospect_company' => $prospectCompany,
                'enabled_services' => array_keys(array_filter($this->enabledServices))
            ]);

            // Étape 1: Recherche avec les moteurs de recherche
            $searchResults = $this->performSearches($prospectName, $prospectCompany, $options);
            
            // Étape 2: Scraping direct des URLs si fourni
            $scrapingResults = $this->performDirectScraping($prospectName, $prospectCompany, $options);
            
            // Étape 3: Combiner et dédupliquer tous les contacts
            $allContacts = $this->combineAndDeduplicateContacts($searchResults, $scrapingResults);
            
            // Étape 4: Validation finale avec la stratégie rule-based
            $finalValidation = $this->performFinalValidation($allContacts, [
                'prospect_name' => $prospectName,
                'prospect_company' => $prospectCompany
            ]);
            
            // Étape 5: Sélectionner les meilleurs contacts
            $bestContacts = $this->selectBestContacts($allContacts, $options['max_contacts'] ?? 10);

            $executionTime = (microtime(true) - $startTime) * 1000;
            
            // Construire les métadonnées consolidées
            $metadata = $this->buildConsolidatedMetadata($searchResults, $scrapingResults, [
                'execution_time_ms' => $executionTime,
                'total_services_used' => count(array_filter($this->enabledServices)),
                'contacts_before_deduplication' => count($allContacts),
                'contacts_after_deduplication' => count($bestContacts)
            ]);

            Log::info('Web enrichment completed', [
                'prospect_name' => $prospectName,
                'contacts_found' => count($bestContacts),
                'execution_time_ms' => $executionTime,
                'validation_score' => $finalValidation->overallScore
            ]);

            return WebScrapingResult::success(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'web_enrichment_combined',
                contacts: $bestContacts,
                validation: $finalValidation,
                metadata: $metadata,
                executionTimeMs: $executionTime
            );

        } catch (\Exception $e) {
            Log::error('Web enrichment failed', [
                'prospect_name' => $prospectName,
                'prospect_company' => $prospectCompany,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return WebScrapingResult::failure(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'web_enrichment_combined',
                errorMessage: $e->getMessage(),
                executionTimeMs: (microtime(true) - $startTime) * 1000
            );
        }
    }

    private function performSearches(string $prospectName, string $prospectCompany, array $options): array
    {
        $searchResults = [];
        
        // DuckDuckGo Search (gratuit, toujours disponible)
        if ($this->enabledServices['duckduckgo'] && $this->duckDuckGoService->isConfigured()) {
            try {
                $duckduckgoResult = $this->duckDuckGoService->searchProspectContacts(
                    $prospectName, 
                    $prospectCompany, 
                    $options
                );
                $searchResults['duckduckgo'] = $duckduckgoResult;
                
                Log::info('DuckDuckGo search completed', [
                    'success' => $duckduckgoResult->success,
                    'contacts_found' => count($duckduckgoResult->contacts)
                ]);
            } catch (\Exception $e) {
                Log::warning('DuckDuckGo search failed', ['error' => $e->getMessage()]);
            }
        }

        // Google Search avec Selenium (si configuré)
        if ($this->enabledServices['google_search'] && $this->googleSearchService->isConfigured()) {
            try {
                $googleResult = $this->googleSearchService->searchProspectContacts(
                    $prospectName, 
                    $prospectCompany, 
                    array_merge($options, [
                        'company_domain' => $options['company_website'] ?? null
                    ])
                );
                $searchResults['google'] = $googleResult;
                
                Log::info('Google search completed', [
                    'success' => $googleResult->success,
                    'contacts_found' => count($googleResult->contacts)
                ]);
            } catch (\Exception $e) {
                Log::warning('Google search failed', ['error' => $e->getMessage()]);
            }
        }

        return $searchResults;
    }

    private function performDirectScraping(string $prospectName, string $prospectCompany, array $options): array
    {
        $scrapingResults = [];
        
        // Si des URLs spécifiques sont fournies, les scraper directement
        if (!empty($options['urls_to_scrape']) && $this->enabledServices['universal_scraper']) {
            try {
                $scrapingResult = $this->universalScraperService->scrapeUrls(
                    $options['urls_to_scrape'],
                    $prospectName,
                    $prospectCompany,
                    $options
                );
                $scrapingResults['direct_scraping'] = $scrapingResult;
                
                Log::info('Direct scraping completed', [
                    'success' => $scrapingResult->success,
                    'urls_scraped' => count($options['urls_to_scrape']),
                    'contacts_found' => count($scrapingResult->contacts)
                ]);
            } catch (\Exception $e) {
                Log::warning('Direct scraping failed', ['error' => $e->getMessage()]);
            }
        }

        return $scrapingResults;
    }

    private function combineAndDeduplicateContacts(array $searchResults, array $scrapingResults): array
    {
        $allContacts = [];
        $allResults = array_merge($searchResults, $scrapingResults);

        // Collecter tous les contacts de tous les services
        foreach ($allResults as $serviceName => $result) {
            if ($result->success && !empty($result->contacts)) {
                foreach ($result->contacts as $contact) {
                    // Ajouter l'information du service source
                    $contactArray = $contact->toArray();
                    $contactArray['context']['enrichment_service'] = $serviceName;
                    
                    $allContacts[] = $contact;
                }
            }
        }

        // Déduplication basée sur la valeur du contact
        return $this->deduplicateContactsByValue($allContacts);
    }

    private function deduplicateContactsByValue(array $contacts): array
    {
        $unique = [];
        $seenValues = [];

        foreach ($contacts as $contact) {
            $key = strtolower($contact->type . ':' . $contact->value);
            
            if (!in_array($key, $seenValues)) {
                $seenValues[] = $key;
                $unique[] = $contact;
            } else {
                // Si on a déjà ce contact, garder celui avec le meilleur score
                for ($i = 0; $i < count($unique); $i++) {
                    $existingKey = strtolower($unique[$i]->type . ':' . $unique[$i]->value);
                    if ($existingKey === $key && $contact->validationScore > $unique[$i]->validationScore) {
                        $unique[$i] = $contact;
                        break;
                    }
                }
            }
        }

        return $unique;
    }

    private function performFinalValidation(array $contacts, array $context): ValidationResult
    {
        return $this->validationStrategy->validateContacts($contacts, $context);
    }

    private function selectBestContacts(array $contacts, int $maxContacts): array
    {
        // Trier par score de validation décroissant
        usort($contacts, function($a, $b) {
            return $b->validationScore <=> $a->validationScore;
        });

        // Sélectionner les meilleurs en gardant la diversité des types
        $selectedContacts = [];
        $contactTypes = [];

        foreach ($contacts as $contact) {
            if (count($selectedContacts) >= $maxContacts) {
                break;
            }

            // Favoriser la diversité des types de contacts
            if (!isset($contactTypes[$contact->type]) || $contactTypes[$contact->type] < 3) {
                $selectedContacts[] = $contact;
                $contactTypes[$contact->type] = ($contactTypes[$contact->type] ?? 0) + 1;
            } elseif (count($selectedContacts) < $maxContacts && $contact->validationScore > 70) {
                // Accepter quand même si le score est très élevé
                $selectedContacts[] = $contact;
            }
        }

        return $selectedContacts;
    }

    private function buildConsolidatedMetadata(array $searchResults, array $scrapingResults, array $additionalMeta): array
    {
        $metadata = $additionalMeta;
        $allResults = array_merge($searchResults, $scrapingResults);

        // Statistiques par service
        $metadata['services_results'] = [];
        foreach ($allResults as $serviceName => $result) {
            $metadata['services_results'][$serviceName] = [
                'success' => $result->success,
                'contacts_found' => count($result->contacts),
                'execution_time_ms' => $result->executionTimeMs,
                'validation_score' => $result->validation->overallScore,
                'error_message' => $result->errorMessage
            ];
        }

        // Statistiques globales
        $metadata['global_stats'] = [
            'total_services_attempted' => count($allResults),
            'successful_services' => count(array_filter($allResults, fn($r) => $r->success)),
            'total_raw_contacts' => array_sum(array_map(fn($r) => count($r->contacts), $allResults)),
            'services_enabled' => $this->enabledServices
        ];

        return $metadata;
    }

    public function getAvailableServices(): array
    {
        return [
            'duckduckgo' => [
                'enabled' => $this->enabledServices['duckduckgo'],
                'configured' => $this->duckDuckGoService->isConfigured(),
                'info' => $this->duckDuckGoService->getServiceInfo()
            ],
            'google_search' => [
                'enabled' => $this->enabledServices['google_search'],
                'configured' => $this->googleSearchService->isConfigured(),
                'info' => $this->googleSearchService->getServiceInfo()
            ],
            'universal_scraper' => [
                'enabled' => $this->enabledServices['universal_scraper'],
                'configured' => $this->universalScraperService->isConfigured(),
                'info' => $this->universalScraperService->getServiceInfo()
            ],
            'validation_strategy' => [
                'enabled' => true,
                'configured' => $this->validationStrategy->isConfigured(),
                'info' => $this->validationStrategy->getServiceInfo()
            ]
        ];
    }

    public function updateServiceConfiguration(array $config): void
    {
        $this->config = array_merge($this->config, $config);
        $this->initializeEnabledServices();
        
        Log::info('Web enrichment service configuration updated', [
            'new_config' => $this->config,
            'enabled_services' => $this->enabledServices
        ]);
    }

    public function testServices(): array
    {
        $testResults = [];
        
        // Test simple avec des données fictives
        $testName = "Test Prospect";
        $testCompany = "Test Company";
        
        foreach ($this->getAvailableServices() as $serviceName => $serviceConfig) {
            if (!$serviceConfig['enabled'] || !$serviceConfig['configured']) {
                $testResults[$serviceName] = [
                    'status' => 'skipped',
                    'reason' => !$serviceConfig['enabled'] ? 'disabled' : 'not_configured'
                ];
                continue;
            }

            try {
                $startTime = microtime(true);
                
                switch ($serviceName) {
                    case 'duckduckgo':
                        $this->duckDuckGoService->searchProspectContacts($testName, $testCompany);
                        break;
                    case 'google_search':
                        $this->googleSearchService->searchProspectContacts($testName, $testCompany);
                        break;
                    case 'universal_scraper':
                        $this->universalScraperService->scrapeUrls(['https://example.com'], $testName, $testCompany);
                        break;
                    case 'validation_strategy':
                        $this->validationStrategy->validateContacts([], ['prospect_name' => $testName, 'prospect_company' => $testCompany]);
                        break;
                }
                
                $executionTime = (microtime(true) - $startTime) * 1000;
                
                $testResults[$serviceName] = [
                    'status' => 'success',
                    'execution_time_ms' => $executionTime,
                    'service_responsive' => true
                ];
                
            } catch (\Exception $e) {
                $testResults[$serviceName] = [
                    'status' => 'error',
                    'error_message' => $e->getMessage(),
                    'service_responsive' => false
                ];
            }
        }

        return $testResults;
    }

    public function isConfigured(): bool
    {
        // Au moins un service de recherche doit être configuré
        return ($this->enabledServices['duckduckgo'] && $this->duckDuckGoService->isConfigured()) ||
               ($this->enabledServices['google_search'] && $this->googleSearchService->isConfigured()) ||
               ($this->enabledServices['universal_scraper'] && $this->universalScraperService->isConfigured());
    }

    public function getServiceInfo(): array
    {
        return [
            'name' => 'Web Enrichment Service',
            'type' => 'prospect_enrichment',
            'available' => $this->isConfigured(),
            'description' => 'Service d\'enrichissement web multi-sources sans dépendance IA',
            'ai_dependency' => false,
            'enabled_services' => array_keys(array_filter($this->enabledServices)),
            'cost' => 'Free (except Google Search with Selenium if enabled)'
        ];
    }
}