<?php

namespace App\__Infrastructure__\Services\External;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration avec Nominatim/OpenStreetMap
 * Pour les données géographiques publiques légales
 */
class NominatimService
{
    private string $baseUrl;
    private string $userAgent;

    public function __construct()
    {
        $this->baseUrl = config('services.nominatim.base_url');
        $this->userAgent = config('services.nominatim.user_agent');
    }

    /**
     * Recherche des lieux/entreprises selon des critères géographiques
     */
    public function search(string $query, array $filters = []): array
    {
        // Mode démo si activé
        if ($this->isDemoMode()) {
            return $this->getDemoResults($query, $filters);
        }

        try {
            $params = $this->buildSearchParams($query, $filters);
            
            $response = Http::timeout(30)
                          ->withHeaders([
                              'User-Agent' => $this->userAgent,
                              'Accept' => 'application/json',
                          ])
                          ->get($this->baseUrl . '/search', $params);

            if (!$response->successful()) {
                Log::error('Nominatim API error', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return $this->getDemoResults($query, $filters);
            }

            return $this->formatResponse($response);

        } catch (\Exception $e) {
            Log::error('Nominatim service error', [
                'message' => $e->getMessage(),
                'query' => $query,
                'filters' => $filters
            ]);
            return $this->getDemoResults($query, $filters);
        }
    }

    /**
     * Recherche inverse - obtenir détails d'un lieu par coordonnées
     */
    public function reverseGeocode(float $lat, float $lon): ?array
    {
        try {
            $response = Http::timeout(30)
                          ->withHeaders([
                              'User-Agent' => $this->userAgent,
                              'Accept' => 'application/json',
                          ])
                          ->get($this->baseUrl . '/reverse', [
                              'lat' => $lat,
                              'lon' => $lon,
                              'format' => 'json',
                              'addressdetails' => 1,
                              'extratags' => 1,
                              'namedetails' => 1,
                          ]);

            if (!$response->successful()) {
                return null;
            }

            return $this->formatReverseResult($response->json());

        } catch (\Exception $e) {
            Log::error('Nominatim reverse geocoding error', [
                'message' => $e->getMessage(),
                'lat' => $lat,
                'lon' => $lon
            ]);
            return null;
        }
    }

    private function buildSearchParams(string $query, array $filters): array
    {
        $params = [
            'q' => $query,
            'format' => 'json',
            'addressdetails' => 1,
            'extratags' => 1,
            'namedetails' => 1,
            'limit' => $filters['limit'] ?? 20,
        ];

        // Filtrer par localisation si spécifiée
        if (!empty($filters['location'])) {
            $params['q'] .= ', ' . $filters['location'];
        }

        // Filtrer par pays (France par défaut)
        if (!empty($filters['country'])) {
            $params['countrycodes'] = $filters['country'];
        } else {
            $params['countrycodes'] = 'fr';
        }

        // Filtrer par type d'établissement commercial
        $params['q'] .= ' shop OR amenity OR office OR craft';

        return $params;
    }

    private function formatResponse(Response $response): array
    {
        $data = $response->json();
        $results = [];

        if (empty($data)) {
            return [];
        }

        foreach ($data as $place) {
            // Ne garder que les lieux qui ressemblent à des entreprises
            if ($this->isCommercialPlace($place)) {
                $results[] = $this->formatPlace($place);
            }
        }

        return $results;
    }

    private function isCommercialPlace(array $place): bool
    {
        $type = $place['type'] ?? '';
        $class = $place['class'] ?? '';
        
        // Filtrer pour ne garder que les établissements commerciaux
        $commercialTypes = ['shop', 'amenity', 'office', 'craft', 'tourism', 'leisure'];
        $commercialAmenities = ['restaurant', 'cafe', 'bar', 'bank', 'pharmacy', 'hospital', 'school'];
        
        return in_array($class, $commercialTypes) || 
               in_array($type, $commercialAmenities) ||
               !empty($place['extratags']['opening_hours'] ?? null);
    }

    private function formatPlace(array $place): array
    {
        $address = $place['address'] ?? [];
        
        return [
            'id' => $place['place_id'] ?? null,
            'name' => $this->extractName($place),
            'company' => $this->extractName($place),
            'sector' => $this->extractSector($place),
            'description' => $this->buildDescription($place),
            'phone' => $place['extratags']['phone'] ?? null,
            'email' => $place['extratags']['email'] ?? null,
            'website' => $place['extratags']['website'] ?? null,
            'address' => [
                'full' => $place['display_name'] ?? null,
                'street' => $this->buildStreetAddress($address),
                'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
                'postal_code' => $address['postcode'] ?? null,
            ],
            'city' => $address['city'] ?? $address['town'] ?? $address['village'] ?? null,
            'postal_code' => $address['postcode'] ?? null,
            'coordinates' => [
                'lat' => floatval($place['lat'] ?? 0),
                'lng' => floatval($place['lon'] ?? 0),
            ],
            'opening_hours' => $place['extratags']['opening_hours'] ?? null,
            'source' => 'nominatim',
            'external_id' => $place['place_id'] ?? null,
            'raw_data' => $place,
        ];
    }

    private function formatReverseResult(array $data): array
    {
        return $this->formatPlace($data);
    }

    private function extractName(array $place): string
    {
        // Priorité aux noms commerciaux
        if (!empty($place['namedetails']['name:fr'])) {
            return $place['namedetails']['name:fr'];
        }
        
        if (!empty($place['name'])) {
            return $place['name'];
        }
        
        if (!empty($place['display_name'])) {
            $parts = explode(',', $place['display_name']);
            return trim($parts[0]);
        }
        
        return 'Établissement ' . ($place['type'] ?? 'inconnu');
    }

    private function extractSector(array $place): ?string
    {
        $type = $place['type'] ?? '';
        $class = $place['class'] ?? '';
        
        // Mapping des types OSM vers secteurs d'activité
        $sectorMapping = [
            'restaurant' => 'Restauration',
            'cafe' => 'Restauration',
            'bar' => 'Restauration',
            'bakery' => 'Alimentation',
            'shop' => 'Commerce',
            'supermarket' => 'Grande distribution',
            'pharmacy' => 'Santé',
            'hospital' => 'Santé',
            'clinic' => 'Santé',
            'dentist' => 'Santé',
            'bank' => 'Services financiers',
            'office' => 'Services',
            'hotel' => 'Hôtellerie',
            'school' => 'Éducation',
            'university' => 'Éducation',
            'garage' => 'Automobile',
            'fuel' => 'Automobile',
        ];
        
        return $sectorMapping[$type] ?? $sectorMapping[$class] ?? ucfirst($type);
    }

    private function buildDescription(array $place): ?string
    {
        $parts = [];
        
        if (!empty($place['type'])) {
            $parts[] = ucfirst($place['type']);
        }
        
        if (!empty($place['extratags']['cuisine'])) {
            $parts[] = 'Cuisine: ' . $place['extratags']['cuisine'];
        }
        
        if (!empty($place['extratags']['opening_hours'])) {
            $parts[] = 'Horaires: ' . $place['extratags']['opening_hours'];
        }
        
        return !empty($parts) ? implode(' • ', $parts) : null;
    }

    private function buildStreetAddress(array $address): ?string
    {
        $parts = [];
        
        if (!empty($address['house_number'])) {
            $parts[] = $address['house_number'];
        }
        
        if (!empty($address['road'])) {
            $parts[] = $address['road'];
        }
        
        return !empty($parts) ? implode(' ', $parts) : null;
    }

    /**
     * Vérifie si le service est configuré et opérationnel
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl) && !empty($this->userAgent);
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
        $demoPlaces = [
            [
                'place_id' => 'osm_demo_1',
                'name' => 'Café de la Place',
                'type' => 'cafe',
                'class' => 'amenity',
                'lat' => '48.8566',
                'lon' => '2.3522',
                'display_name' => 'Café de la Place, 15 Place de la République, 75003 Paris, France',
                'address' => [
                    'house_number' => '15',
                    'road' => 'Place de la République',
                    'postcode' => '75003',
                    'city' => 'Paris',
                ],
                'extratags' => [
                    'opening_hours' => 'Mo-Fr 07:00-19:00; Sa 08:00-18:00',
                    'phone' => '01 42 78 15 22',
                    'cuisine' => 'french'
                ]
            ],
            [
                'place_id' => 'osm_demo_2',
                'name' => 'Pharmacie Centrale',
                'type' => 'pharmacy',
                'class' => 'amenity',
                'lat' => '48.8534',
                'lon' => '2.3488',
                'display_name' => 'Pharmacie Centrale, 67 Rue de Rivoli, 75001 Paris, France',
                'address' => [
                    'house_number' => '67',
                    'road' => 'Rue de Rivoli',
                    'postcode' => '75001',
                    'city' => 'Paris',
                ],
                'extratags' => [
                    'opening_hours' => 'Mo-Sa 09:00-19:30',
                    'phone' => '01 44 82 44 67'
                ]
            ],
            [
                'place_id' => 'osm_demo_3',
                'name' => 'Bureau de Tabac du Coin',
                'type' => 'tobacco',
                'class' => 'shop',
                'lat' => '48.8606',
                'lon' => '2.3376',
                'display_name' => 'Bureau de Tabac du Coin, 23 Rue Saint-Martin, 75004 Paris, France',
                'address' => [
                    'house_number' => '23',
                    'road' => 'Rue Saint-Martin',
                    'postcode' => '75004',
                    'city' => 'Paris',
                ],
                'extratags' => [
                    'opening_hours' => 'Mo-Sa 07:00-20:00; Su 08:00-13:00'
                ]
            ]
        ];

        // Filtrer les résultats selon la requête
        $filtered = array_filter($demoPlaces, function($place) use ($query) {
            $searchIn = strtolower($place['name'] . ' ' . $place['type'] . ' ' . $place['display_name']);
            return strpos($searchIn, strtolower($query)) !== false;
        });

        // Formater les résultats
        $results = [];
        foreach (array_slice($filtered, 0, intval($filters['limit'] ?? 5)) as $place) {
            $results[] = $this->formatPlace($place);
        }

        return $results;
    }
}