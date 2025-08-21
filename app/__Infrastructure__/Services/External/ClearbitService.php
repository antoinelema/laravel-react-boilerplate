<?php

namespace App\__Infrastructure__\Services\External;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration avec Clearbit API
 * Pour l'enrichissement légal de données d'entreprises
 */
class ClearbitService
{
    private ?string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.clearbit.api_key');
        $this->baseUrl = config('services.clearbit.base_url', 'https://company.clearbit.com/v2');
    }

    /**
     * Enrichit les informations d'une entreprise via son nom ou domaine
     */
    public function enrichCompany(string $identifier, string $type = 'domain'): ?array
    {
        // Mode démo si activé ou pas de clé API configurée
        if ($this->isDemoMode() || !$this->isConfigured()) {
            return $this->getDemoCompanyData($identifier);
        }

        try {
            $params = $this->buildEnrichParams($identifier, $type);
            
            $response = Http::timeout(30)
                          ->withHeaders([
                              'Authorization' => 'Bearer ' . $this->apiKey,
                              'Accept' => 'application/json',
                          ])
                          ->get($this->baseUrl . '/companies/find', $params);

            if (!$response->successful()) {
                Log::warning('Clearbit API error', [
                    'status' => $response->status(),
                    'identifier' => $identifier,
                    'type' => $type
                ]);
                return $this->getDemoCompanyData($identifier);
            }

            return $this->formatCompanyResponse($response);

        } catch (\Exception $e) {
            Log::error('Clearbit service error', [
                'message' => $e->getMessage(),
                'identifier' => $identifier,
                'type' => $type
            ]);
            return $this->getDemoCompanyData($identifier);
        }
    }

    /**
     * Recherche d'entreprises selon des critères
     */
    public function searchCompanies(string $query, array $filters = []): array
    {
        // Mode démo si activé ou pas de clé API configurée
        if ($this->isDemoMode() || !$this->isConfigured()) {
            return $this->getDemoSearchResults($query, $filters);
        }

        try {
            $params = $this->buildSearchParams($query, $filters);
            
            $response = Http::timeout(30)
                          ->withHeaders([
                              'Authorization' => 'Bearer ' . $this->apiKey,
                              'Accept' => 'application/json',
                          ])
                          ->get($this->baseUrl . '/companies/search', $params);

            if (!$response->successful()) {
                Log::warning('Clearbit search API error', [
                    'status' => $response->status(),
                    'query' => $query
                ]);
                return $this->getDemoSearchResults($query, $filters);
            }

            return $this->formatSearchResponse($response);

        } catch (\Exception $e) {
            Log::error('Clearbit search service error', [
                'message' => $e->getMessage(),
                'query' => $query,
                'filters' => $filters
            ]);
            return $this->getDemoSearchResults($query, $filters);
        }
    }

    /**
     * Obtient le logo d'une entreprise
     */
    public function getCompanyLogo(string $domain): ?string
    {
        if ($this->isDemoMode() || !$this->isConfigured()) {
            return "https://logo.clearbit.com/{$domain}";
        }

        return "https://logo.clearbit.com/{$domain}";
    }

    private function buildEnrichParams(string $identifier, string $type): array
    {
        $params = [];
        
        if ($type === 'domain') {
            $params['domain'] = $identifier;
        } elseif ($type === 'name') {
            $params['company_name'] = $identifier;
        }
        
        return $params;
    }

    private function buildSearchParams(string $query, array $filters): array
    {
        $params = [
            'query' => $query,
            'limit' => $filters['limit'] ?? 20,
        ];

        if (!empty($filters['location'])) {
            $params['location'] = $filters['location'];
        }

        if (!empty($filters['sector'])) {
            $params['industry'] = $filters['sector'];
        }

        if (!empty($filters['company_size'])) {
            $params['employees'] = $filters['company_size'];
        }

        return $params;
    }

    private function formatCompanyResponse(Response $response): ?array
    {
        $data = $response->json();

        if (empty($data)) {
            return null;
        }

        return $this->formatCompanyData($data);
    }

    private function formatSearchResponse(Response $response): array
    {
        $data = $response->json();
        $results = [];

        if (empty($data['results'])) {
            return [];
        }

        foreach ($data['results'] as $company) {
            $results[] = $this->formatCompanyData($company);
        }

        return $results;
    }

    private function formatCompanyData(array $data): array
    {
        $geo = $data['geo'] ?? [];
        $metrics = $data['metrics'] ?? [];
        
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['name'] ?? 'Unknown Company',
            'company' => $data['legalName'] ?? $data['name'] ?? null,
            'sector' => $this->formatIndustry($data),
            'description' => $data['description'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $this->extractEmail($data),
            'website' => $data['domain'] ? "https://{$data['domain']}" : null,
            'domain' => $data['domain'] ?? null,
            'address' => [
                'full' => $this->buildFullAddress($geo),
                'street' => $geo['streetName'] ?? null,
                'city' => $geo['city'] ?? null,
                'postal_code' => $geo['postalCode'] ?? null,
                'country' => $geo['country'] ?? null,
            ],
            'city' => $geo['city'] ?? null,
            'postal_code' => $geo['postalCode'] ?? null,
            'coordinates' => [
                'lat' => $geo['lat'] ?? null,
                'lng' => $geo['lng'] ?? null,
            ],
            'founded_year' => $data['foundedYear'] ?? null,
            'employees_count' => $metrics['employees'] ?? null,
            'annual_revenue' => $metrics['annualRevenue'] ?? null,
            'funding_total' => $metrics['raised'] ?? null,
            'logo_url' => $data['logo'] ?? null,
            'linkedin_url' => $data['linkedin']['handle'] ? "https://linkedin.com/company/{$data['linkedin']['handle']}" : null,
            'twitter_url' => $data['twitter']['handle'] ? "https://twitter.com/{$data['twitter']['handle']}" : null,
            'facebook_url' => $data['facebook']['handle'] ? "https://facebook.com/{$data['facebook']['handle']}" : null,
            'tags' => $data['tags'] ?? [],
            'source' => 'clearbit',
            'external_id' => $data['id'] ?? null,
            'raw_data' => $data,
        ];
    }

    private function formatIndustry(array $data): ?string
    {
        if (!empty($data['category']['industry'])) {
            return $data['category']['industry'];
        }
        
        if (!empty($data['category']['sector'])) {
            return $data['category']['sector'];
        }
        
        return $data['category']['industryGroup'] ?? null;
    }

    private function extractEmail(array $data): ?string
    {
        // Clearbit ne fournit généralement pas d'emails directement
        // mais pourrait avoir des patterns d'email
        if (!empty($data['emailProvider'])) {
            return null; // Ne pas deviner les emails
        }
        
        return null;
    }

    private function buildFullAddress(array $geo): ?string
    {
        $parts = [];
        
        if (!empty($geo['streetNumber'])) {
            $parts[] = $geo['streetNumber'];
        }
        
        if (!empty($geo['streetName'])) {
            $parts[] = $geo['streetName'];
        }
        
        if (!empty($geo['city'])) {
            $parts[] = $geo['city'];
        }
        
        if (!empty($geo['postalCode'])) {
            $parts[] = $geo['postalCode'];
        }
        
        if (!empty($geo['country'])) {
            $parts[] = $geo['country'];
        }
        
        return !empty($parts) ? implode(', ', $parts) : null;
    }

    /**
     * Vérifie si le service est configuré et opérationnel
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'your-clearbit-api-key';
    }

    /**
     * Vérifie si le mode démo est activé
     */
    private function isDemoMode(): bool
    {
        return config('app.external_services_demo_mode', true);
    }

    /**
     * Génère des données de démonstration pour une entreprise
     */
    private function getDemoCompanyData(string $identifier): ?array
    {
        // Données de démo basées sur l'identifiant
        $demoCompanies = [
            'restaurant-le-gourmet.fr' => [
                'id' => 'clearbit_demo_1',
                'name' => 'Restaurant Le Gourmet',
                'legalName' => 'Le Gourmet SARL',
                'domain' => 'restaurant-le-gourmet.fr',
                'description' => 'Restaurant français haut de gamme proposant une cuisine traditionnelle revisitée',
                'category' => [
                    'industry' => 'Restaurants',
                    'sector' => 'Food & Beverages'
                ],
                'geo' => [
                    'streetName' => 'Rue de la Paix',
                    'streetNumber' => '42',
                    'city' => 'Paris',
                    'postalCode' => '75002',
                    'country' => 'France',
                    'lat' => 48.8698,
                    'lng' => 2.3314
                ],
                'metrics' => [
                    'employees' => 25,
                    'annualRevenue' => 850000
                ],
                'foundedYear' => 2015,
                'logo' => 'https://logo.clearbit.com/restaurant-le-gourmet.fr'
            ],
            'default' => [
                'id' => 'clearbit_demo_generic',
                'name' => 'Entreprise Démo',
                'legalName' => 'Entreprise Démo SAS',
                'domain' => 'demo-company.fr',
                'description' => 'Entreprise de démonstration pour tests',
                'category' => [
                    'industry' => 'Services',
                    'sector' => 'Business Services'
                ],
                'geo' => [
                    'city' => 'Paris',
                    'country' => 'France'
                ],
                'metrics' => [
                    'employees' => 50
                ]
            ]
        ];

        $companyData = $demoCompanies[$identifier] ?? $demoCompanies['default'];
        
        return $this->formatCompanyData($companyData);
    }

    /**
     * Génère des résultats de recherche de démonstration
     */
    private function getDemoSearchResults(string $query, array $filters = []): array
    {
        $demoCompanies = [
            [
                'id' => 'clearbit_search_1',
                'name' => 'Tech Innovation SAS',
                'domain' => 'tech-innovation.fr',
                'category' => [
                    'industry' => 'Software',
                    'sector' => 'Technology'
                ],
                'geo' => [
                    'city' => 'Lyon',
                    'country' => 'France'
                ],
                'metrics' => [
                    'employees' => 120,
                    'annualRevenue' => 5000000
                ]
            ],
            [
                'id' => 'clearbit_search_2',
                'name' => 'Consulting & Partners',
                'domain' => 'consulting-partners.com',
                'category' => [
                    'industry' => 'Management Consulting',
                    'sector' => 'Business Services'
                ],
                'geo' => [
                    'city' => 'Paris',
                    'country' => 'France'
                ],
                'metrics' => [
                    'employees' => 80
                ]
            ]
        ];

        // Filtrer selon la requête
        $filtered = array_filter($demoCompanies, function($company) use ($query) {
            $searchIn = strtolower($company['name'] . ' ' . ($company['category']['industry'] ?? ''));
            return strpos($searchIn, strtolower($query)) !== false;
        });

        // Formater les résultats
        $results = [];
        foreach (array_slice($filtered, 0, intval($filters['limit'] ?? 5)) as $company) {
            $results[] = $this->formatCompanyData($company);
        }

        return $results;
    }
}