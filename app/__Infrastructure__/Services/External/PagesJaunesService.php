<?php

namespace App\__Infrastructure__\Services\External;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration avec l'API Pages Jaunes
 */
class PagesJaunesService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.pages_jaunes.api_key');
        $this->baseUrl = config('services.pages_jaunes.base_url', 'https://api.pagesjaunes.fr');
    }

    /**
     * Recherche des entreprises selon des critères
     */
    public function search(string $query, array $filters = []): array
    {
        // Mode démo si activé ou pas de clé API configurée
        if ($this->isDemoMode() || !$this->isConfigured()) {
            return $this->getDemoResults($query, $filters);
        }

        try {
            $params = $this->buildSearchParams($query, $filters);
            
            $response = Http::timeout(30)
                          ->withHeaders([
                              'Authorization' => 'Bearer ' . $this->apiKey,
                              'Accept' => 'application/json',
                          ])
                          ->get($this->baseUrl . '/search/entreprises', $params);

            if (!$response->successful()) {
                Log::error('Pages Jaunes API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $this->getDemoResults($query, $filters);
            }

            return $this->formatResponse($response);

        } catch (\Exception $e) {
            Log::error('Pages Jaunes service error', [
                'message' => $e->getMessage(),
                'query' => $query,
                'filters' => $filters
            ]);
            return $this->getDemoResults($query, $filters);
        }
    }

    /**
     * Obtient les détails d'une entreprise spécifique
     */
    public function getCompanyDetails(string $companyId): ?array
    {
        try {
            $response = Http::timeout(30)
                          ->withHeaders([
                              'Authorization' => 'Bearer ' . $this->apiKey,
                              'Accept' => 'application/json',
                          ])
                          ->get($this->baseUrl . "/entreprises/{$companyId}");

            if (!$response->successful()) {
                Log::error('Pages Jaunes company details error', [
                    'company_id' => $companyId,
                    'status' => $response->status()
                ]);
                return null;
            }

            return $this->formatCompanyDetails($response->json());

        } catch (\Exception $e) {
            Log::error('Pages Jaunes company details service error', [
                'message' => $e->getMessage(),
                'company_id' => $companyId
            ]);
            return null;
        }
    }

    private function buildSearchParams(string $query, array $filters): array
    {
        $params = [
            'q' => $query,
            'limit' => $filters['limit'] ?? 20,
        ];

        if (!empty($filters['location'])) {
            $params['location'] = $filters['location'];
        }

        if (!empty($filters['sector'])) {
            $params['secteur'] = $filters['sector'];
        }

        if (!empty($filters['radius'])) {
            $params['rayon'] = $filters['radius'];
        }

        if (!empty($filters['postal_code'])) {
            $params['code_postal'] = $filters['postal_code'];
        }

        return $params;
    }

    private function formatResponse(Response $response): array
    {
        $data = $response->json();
        $results = [];

        if (empty($data['entreprises'])) {
            return [];
        }

        foreach ($data['entreprises'] as $company) {
            $results[] = [
                'id' => $company['id'] ?? null,
                'name' => $company['nom'] ?? 'Unknown',
                'company' => $company['raison_sociale'] ?? $company['nom'] ?? null,
                'sector' => $company['activite'] ?? $company['secteur'] ?? null,
                'description' => $company['description'] ?? null,
                'phone' => $company['telephone'] ?? null,
                'email' => $company['email'] ?? null,
                'website' => $company['site_web'] ?? null,
                'address' => [
                    'full' => $company['adresse_complete'] ?? null,
                    'street' => $company['adresse'] ?? null,
                    'city' => $company['ville'] ?? null,
                    'postal_code' => $company['code_postal'] ?? null,
                ],
                'city' => $company['ville'] ?? null,
                'postal_code' => $company['code_postal'] ?? null,
                'coordinates' => [
                    'lat' => $company['latitude'] ?? null,
                    'lng' => $company['longitude'] ?? null,
                ],
                'source' => 'pages_jaunes',
                'external_id' => $company['id'] ?? null,
                'raw_data' => $company,
            ];
        }

        return $results;
    }

    private function formatCompanyDetails(array $data): array
    {
        return [
            'id' => $data['id'] ?? null,
            'name' => $data['nom'] ?? 'Unknown',
            'company' => $data['raison_sociale'] ?? $data['nom'] ?? null,
            'sector' => $data['activite'] ?? $data['secteur'] ?? null,
            'description' => $data['description'] ?? null,
            'phone' => $data['telephone'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['site_web'] ?? null,
            'address' => [
                'full' => $data['adresse_complete'] ?? null,
                'street' => $data['adresse'] ?? null,
                'city' => $data['ville'] ?? null,
                'postal_code' => $data['code_postal'] ?? null,
            ],
            'city' => $data['ville'] ?? null,
            'postal_code' => $data['code_postal'] ?? null,
            'coordinates' => [
                'lat' => $data['latitude'] ?? null,
                'lng' => $data['longitude'] ?? null,
            ],
            'opening_hours' => $data['horaires'] ?? null,
            'social_networks' => $data['reseaux_sociaux'] ?? [],
            'source' => 'pages_jaunes',
            'external_id' => $data['id'] ?? null,
            'raw_data' => $data,
        ];
    }

    /**
     * Vérifie si le service est configuré et opérationnel
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'your-pages-jaunes-api-key';
    }

    /**
     * Vérifie si le mode démo est activé
     */
    private function isDemoMode(): bool
    {
        return config('app.external_services_demo_mode', true);
    }

    /**
     * Génère des résultats de démonstration pour les tests
     */
    private function getDemoResults(string $query, array $filters = []): array
    {
        $demoCompanies = [
            [
                'id' => 'pj_demo_1',
                'nom' => 'Restaurant La Belle Époque',
                'raison_sociale' => 'La Belle Époque SARL',
                'activite' => 'Restaurant traditionnel',
                'secteur' => 'Restauration',
                'description' => 'Restaurant traditionnel français proposant une cuisine raffinée dans un cadre authentique',
                'telephone' => '01 42 86 17 22',
                'email' => 'contact@belleepoque.fr',
                'site_web' => 'https://restaurant-belle-epoque.fr',
                'adresse' => '34 Rue de la Paix',
                'ville' => 'Paris',
                'code_postal' => '75002',
                'adresse_complete' => '34 Rue de la Paix, 75002 Paris',
                'latitude' => 48.8692,
                'longitude' => 2.3316,
                'horaires' => 'Lun-Dim: 12h-14h30, 19h-22h30'
            ],
            [
                'id' => 'pj_demo_2',
                'nom' => 'Boulangerie Pâtisserie Moreau',
                'raison_sociale' => 'Boulangerie Moreau & Fils',
                'activite' => 'Boulangerie pâtisserie artisanale',
                'secteur' => 'Alimentation',
                'description' => 'Boulangerie artisanale familiale depuis 1952',
                'telephone' => '01 47 05 22 87',
                'adresse' => '89 Avenue Parmentier',
                'ville' => 'Paris',
                'code_postal' => '75011',
                'adresse_complete' => '89 Avenue Parmentier, 75011 Paris',
                'latitude' => 48.8644,
                'longitude' => 2.3767,
                'horaires' => 'Mar-Sam: 7h-19h30, Dim: 7h-13h'
            ],
            [
                'id' => 'pj_demo_3',
                'nom' => 'Garage Mécanique Centrale',
                'raison_sociale' => 'Mécanique Centrale EURL',
                'activite' => 'Réparation automobile toutes marques',
                'secteur' => 'Automobile',
                'description' => 'Garage spécialisé en mécanique générale et carrosserie depuis 1987',
                'telephone' => '01 48 28 76 45',
                'email' => 'info@mecanique-centrale.fr',
                'site_web' => 'https://mecanique-centrale.fr',
                'adresse' => '156 Boulevard Voltaire',
                'ville' => 'Paris',
                'code_postal' => '75011',
                'adresse_complete' => '156 Boulevard Voltaire, 75011 Paris',
                'latitude' => 48.8556,
                'longitude' => 2.3730,
                'horaires' => 'Lun-Ven: 8h-18h, Sam: 9h-12h'
            ],
            [
                'id' => 'pj_demo_4',
                'nom' => 'Cabinet Dentaire Dr. Rousseau',
                'raison_sociale' => 'Dr. Rousseau Marie - Chirurgien Dentiste',
                'activite' => 'Chirurgie dentaire',
                'secteur' => 'Santé',
                'description' => 'Cabinet dentaire moderne spécialisé en implantologie et esthétique dentaire',
                'telephone' => '01 45 67 89 12',
                'email' => 'cabinet@dr-rousseau.fr',
                'adresse' => '45 Rue de la Convention',
                'ville' => 'Paris',
                'code_postal' => '75015',
                'adresse_complete' => '45 Rue de la Convention, 75015 Paris',
                'latitude' => 48.8417,
                'longitude' => 2.2965,
                'horaires' => 'Lun-Ven: 9h-19h sur RDV'
            ],
            [
                'id' => 'pj_demo_5',
                'nom' => 'Librairie Le Livre Ouvert',
                'raison_sociale' => 'Le Livre Ouvert SARL',
                'activite' => 'Librairie généraliste',
                'secteur' => 'Culture et loisirs',
                'description' => 'Librairie indépendante proposant un large choix de livres et conseils personnalisés',
                'telephone' => '01 43 31 28 95',
                'email' => 'contact@livre-ouvert.fr',
                'site_web' => 'https://librairie-livre-ouvert.fr',
                'adresse' => '27 Rue Mouffetard',
                'ville' => 'Paris',
                'code_postal' => '75005',
                'adresse_complete' => '27 Rue Mouffetard, 75005 Paris',
                'latitude' => 48.8434,
                'longitude' => 2.3508,
                'horaires' => 'Mar-Sam: 10h-19h, Dim: 10h-13h'
            ]
        ];

        // Filtrer les résultats selon la requête
        $filtered = array_filter($demoCompanies, function($company) use ($query) {
            $searchIn = strtolower($company['nom'] . ' ' . $company['activite'] . ' ' . $company['secteur'] . ' ' . $company['adresse_complete']);
            return strpos($searchIn, strtolower($query)) !== false;
        });

        // Appliquer le filtre de localisation si spécifié
        if (!empty($filters['location'])) {
            $filtered = array_filter($filtered, function($company) use ($filters) {
                return strpos(strtolower($company['adresse_complete']), strtolower($filters['location'])) !== false;
            });
        }

        // Formater les résultats
        $results = [];
        foreach (array_slice($filtered, 0, intval($filters['limit'] ?? 5)) as $company) {
            $results[] = $this->formatCompany($company);
        }

        return $results;
    }

    private function formatCompany(array $company): array
    {
        return [
            'id' => $company['id'],
            'name' => $company['nom'],
            'company' => $company['raison_sociale'] ?? $company['nom'],
            'sector' => $company['secteur'],
            'description' => $company['description'],
            'phone' => $company['telephone'],
            'email' => $company['email'] ?? null,
            'website' => $company['site_web'] ?? null,
            'address' => [
                'full' => $company['adresse_complete'],
                'street' => $company['adresse'],
                'city' => $company['ville'],
                'postal_code' => $company['code_postal'],
            ],
            'city' => $company['ville'],
            'postal_code' => $company['code_postal'],
            'coordinates' => [
                'lat' => $company['latitude'],
                'lng' => $company['longitude'],
            ],
            'opening_hours' => $company['horaires'] ?? null,
            'source' => 'pages_jaunes',
            'external_id' => $company['id'],
            'raw_data' => $company,
        ];
    }
}