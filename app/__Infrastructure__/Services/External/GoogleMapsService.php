<?php

namespace App\__Infrastructure__\Services\External;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration avec Google Maps Places API
 */
class GoogleMapsService
{
    private string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.google_maps.api_key');
        $this->baseUrl = 'https://maps.googleapis.com/maps/api/place';
    }

    /**
     * Recherche des lieux d'entreprise selon des critères
     */
    public function searchPlaces(string $query, array $filters = []): array
    {
        // Mode démo si activé ou pas de clé API configurée
        if ($this->isDemoMode() || !$this->isConfigured()) {
            return $this->getDemoResults($query, $filters);
        }

        try {
            $params = $this->buildSearchParams($query, $filters);
            
            $response = Http::timeout(30)
                          ->get($this->baseUrl . '/textsearch/json', $params);

            if (!$response->successful()) {
                Log::error('Google Maps API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $this->getDemoResults($query, $filters);
            }

            return $this->formatSearchResponse($response);

        } catch (\Exception $e) {
            Log::error('Google Maps service error', [
                'message' => $e->getMessage(),
                'query' => $query,
                'filters' => $filters
            ]);
            return $this->getDemoResults($query, $filters);
        }
    }

    /**
     * Obtient les détails d'un lieu spécifique avec plus d'informations
     */
    public function getPlaceDetails(string $placeId): ?array
    {
        try {
            $params = [
                'place_id' => $placeId,
                'key' => $this->apiKey,
                'fields' => 'place_id,name,formatted_address,formatted_phone_number,website,opening_hours,business_status,price_level,rating,user_ratings_total,types,geometry',
            ];

            $response = Http::timeout(30)
                          ->get($this->baseUrl . '/details/json', $params);

            if (!$response->successful()) {
                Log::error('Google Maps place details error', [
                    'place_id' => $placeId,
                    'status' => $response->status()
                ]);
                return null;
            }

            $data = $response->json();
            if (empty($data['result'])) {
                return null;
            }

            return $this->formatPlaceDetails($data['result']);

        } catch (\Exception $e) {
            Log::error('Google Maps place details service error', [
                'message' => $e->getMessage(),
                'place_id' => $placeId
            ]);
            return null;
        }
    }

    /**
     * Recherche à proximité d'une localisation
     */
    public function searchNearby(float $lat, float $lng, array $filters = []): array
    {
        try {
            $params = [
                'location' => "{$lat},{$lng}",
                'radius' => $filters['radius'] ?? 5000,
                'key' => $this->apiKey,
            ];

            if (!empty($filters['type'])) {
                $params['type'] = $filters['type'];
            }

            if (!empty($filters['keyword'])) {
                $params['keyword'] = $filters['keyword'];
            }

            $response = Http::timeout(30)
                          ->get($this->baseUrl . '/nearbysearch/json', $params);

            if (!$response->successful()) {
                Log::error('Google Maps nearby search error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return [];
            }

            return $this->formatSearchResponse($response);

        } catch (\Exception $e) {
            Log::error('Google Maps nearby search error', [
                'message' => $e->getMessage(),
                'lat' => $lat,
                'lng' => $lng,
                'filters' => $filters
            ]);
            return [];
        }
    }

    private function buildSearchParams(string $query, array $filters): array
    {
        $params = [
            'query' => $query,
            'key' => $this->apiKey,
        ];

        if (!empty($filters['location'])) {
            $params['location'] = $filters['location'];
        }

        if (!empty($filters['radius'])) {
            $params['radius'] = $filters['radius'];
        }

        if (!empty($filters['type'])) {
            $params['type'] = $filters['type'];
        }

        return $params;
    }

    private function formatSearchResponse(Response $response): array
    {
        $data = $response->json();
        $results = [];

        if (empty($data['results'])) {
            return [];
        }

        foreach ($data['results'] as $place) {
            $results[] = $this->formatPlace($place);
        }

        return $results;
    }

    private function formatPlace(array $place): array
    {
        $address = $this->parseAddress($place['formatted_address'] ?? '');
        
        return [
            'id' => $place['place_id'] ?? null,
            'name' => $place['name'] ?? 'Unknown',
            'company' => $place['name'] ?? null,
            'sector' => $this->extractSector($place['types'] ?? []),
            'description' => null,
            'phone' => $place['formatted_phone_number'] ?? null,
            'email' => null, // Google Maps API doesn't provide emails in search results
            'website' => $place['website'] ?? null,
            'address' => [
                'full' => $place['formatted_address'] ?? null,
                'street' => $address['street'] ?? null,
                'city' => $address['city'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
            ],
            'city' => $address['city'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'coordinates' => [
                'lat' => $place['geometry']['location']['lat'] ?? null,
                'lng' => $place['geometry']['location']['lng'] ?? null,
            ],
            'rating' => $place['rating'] ?? null,
            'user_ratings_total' => $place['user_ratings_total'] ?? null,
            'business_status' => $place['business_status'] ?? null,
            'price_level' => $place['price_level'] ?? null,
            'source' => 'google_maps',
            'external_id' => $place['place_id'] ?? null,
            'raw_data' => $place,
        ];
    }

    private function formatPlaceDetails(array $place): array
    {
        $address = $this->parseAddress($place['formatted_address'] ?? '');
        
        return [
            'id' => $place['place_id'] ?? null,
            'name' => $place['name'] ?? 'Unknown',
            'company' => $place['name'] ?? null,
            'sector' => $this->extractSector($place['types'] ?? []),
            'description' => null,
            'phone' => $place['formatted_phone_number'] ?? null,
            'email' => null,
            'website' => $place['website'] ?? null,
            'address' => [
                'full' => $place['formatted_address'] ?? null,
                'street' => $address['street'] ?? null,
                'city' => $address['city'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
            ],
            'city' => $address['city'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'coordinates' => [
                'lat' => $place['geometry']['location']['lat'] ?? null,
                'lng' => $place['geometry']['location']['lng'] ?? null,
            ],
            'rating' => $place['rating'] ?? null,
            'user_ratings_total' => $place['user_ratings_total'] ?? null,
            'business_status' => $place['business_status'] ?? null,
            'price_level' => $place['price_level'] ?? null,
            'opening_hours' => $place['opening_hours']['weekday_text'] ?? null,
            'source' => 'google_maps',
            'external_id' => $place['place_id'] ?? null,
            'raw_data' => $place,
        ];
    }

    private function parseAddress(string $address): array
    {
        $parts = explode(', ', $address);
        $result = [
            'street' => null,
            'city' => null,
            'postal_code' => null,
        ];

        // Simple parsing - can be enhanced based on address formats
        foreach ($parts as $part) {
            if (preg_match('/^\d{5}/', $part)) {
                // Likely contains postal code
                $result['postal_code'] = substr($part, 0, 5);
                $result['city'] = trim(substr($part, 6));
            } elseif (empty($result['street']) && !preg_match('/France|Frankreich/', $part)) {
                $result['street'] = $part;
            }
        }

        return $result;
    }

    private function extractSector(array $types): ?string
    {
        $sectorMapping = [
            'restaurant' => 'Restauration',
            'store' => 'Commerce',
            'health' => 'Santé',
            'lawyer' => 'Services juridiques',
            'dentist' => 'Santé dentaire',
            'doctor' => 'Santé',
            'hospital' => 'Santé',
            'pharmacy' => 'Pharmacie',
            'bank' => 'Banque',
            'insurance_agency' => 'Assurance',
            'real_estate_agency' => 'Immobilier',
            'beauty_salon' => 'Beauté',
            'gym' => 'Sport et fitness',
            'car_dealer' => 'Automobile',
            'gas_station' => 'Station-service',
        ];

        foreach ($types as $type) {
            if (isset($sectorMapping[$type])) {
                return $sectorMapping[$type];
            }
        }

        return $types[0] ?? null;
    }

    /**
     * Vérifie si le service est configuré et opérationnel
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'your-google-maps-api-key';
    }

    /**
     * Vérifie si le mode démo est activé
     */
    private function isDemoMode(): bool
    {
        return config('app.env') === 'local' && config('app.external_services_demo_mode', true);
    }

    /**
     * Génère des résultats de démonstration pour les tests
     */
    private function getDemoResults(string $query, array $filters = []): array
    {
        $demoPlaces = [
            [
                'place_id' => 'demo_place_1',
                'name' => 'Restaurant Le Gourmet Parisien',
                'types' => ['restaurant', 'food', 'establishment'],
                'formatted_address' => '45 Rue de Rivoli, 75001 Paris, France',
                'rating' => 4.5,
                'user_ratings_total' => 127,
                'business_status' => 'OPERATIONAL',
                'price_level' => 3,
                'geometry' => [
                    'location' => ['lat' => 48.8566, 'lng' => 2.3522]
                ],
                'website' => 'https://legourmetparisien.fr',
                'formatted_phone_number' => '01 42 97 48 23'
            ],
            [
                'place_id' => 'demo_place_2',
                'name' => 'Boulangerie Artisanale Dupont',
                'types' => ['bakery', 'food', 'store'],
                'formatted_address' => '12 Boulevard Saint-Germain, 75005 Paris, France',
                'rating' => 4.8,
                'user_ratings_total' => 89,
                'business_status' => 'OPERATIONAL',
                'price_level' => 2,
                'geometry' => [
                    'location' => ['lat' => 48.8534, 'lng' => 2.3488]
                ],
                'formatted_phone_number' => '01 43 26 14 67'
            ],
            [
                'place_id' => 'demo_place_3',
                'name' => 'Garage Auto Expert',
                'types' => ['car_repair', 'establishment'],
                'formatted_address' => '67 Avenue de la République, 75011 Paris, France',
                'rating' => 4.2,
                'user_ratings_total' => 156,
                'business_status' => 'OPERATIONAL',
                'geometry' => [
                    'location' => ['lat' => 48.8674, 'lng' => 2.3765]
                ],
                'website' => 'https://autoexpert.fr',
                'formatted_phone_number' => '01 48 05 77 88'
            ],
            [
                'place_id' => 'demo_place_4',
                'name' => 'Cabinet Médical Dr. Martin',
                'types' => ['doctor', 'health', 'establishment'],
                'formatted_address' => '23 Rue de la Santé, 75014 Paris, France',
                'rating' => 4.6,
                'user_ratings_total' => 94,
                'business_status' => 'OPERATIONAL',
                'geometry' => [
                    'location' => ['lat' => 48.8314, 'lng' => 2.3435]
                ],
                'formatted_phone_number' => '01 45 89 23 44'
            ],
            [
                'place_id' => 'demo_place_5',
                'name' => 'Librairie Moderne',
                'types' => ['book_store', 'store', 'establishment'],
                'formatted_address' => '18 Rue des Écoles, 75005 Paris, France',
                'rating' => 4.3,
                'user_ratings_total' => 67,
                'business_status' => 'OPERATIONAL',
                'geometry' => [
                    'location' => ['lat' => 48.8506, 'lng' => 2.3444]
                ],
                'website' => 'https://librairie-moderne.fr',
                'formatted_phone_number' => '01 43 54 11 22'
            ]
        ];

        // Filtrer les résultats selon la requête
        $filtered = array_filter($demoPlaces, function($place) use ($query) {
            $searchIn = strtolower($place['name'] . ' ' . implode(' ', $place['types']) . ' ' . $place['formatted_address']);
            return strpos($searchIn, strtolower($query)) !== false;
        });

        // Appliquer le filtre de localisation si spécifié
        if (!empty($filters['location'])) {
            $filtered = array_filter($filtered, function($place) use ($filters) {
                return strpos(strtolower($place['formatted_address']), strtolower($filters['location'])) !== false;
            });
        }

        // Formater les résultats
        $results = [];
        foreach (array_slice($filtered, 0, intval($filters['limit'] ?? 5)) as $place) {
            $results[] = $this->formatPlace($place);
        }

        return $results;
    }
}