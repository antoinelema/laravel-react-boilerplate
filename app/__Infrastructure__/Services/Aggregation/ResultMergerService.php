<?php

namespace App\__Infrastructure__\Services\Aggregation;

use Illuminate\Support\Facades\Log;

/**
 * Service de fusion et déduplication des résultats de recherche multi-source
 * Implémente des algorithmes avancés de détection de doublons et de scoring
 */
class ResultMergerService
{
    // Seuils de similarité pour la détection de doublons
    private const NAME_SIMILARITY_THRESHOLD = 0.85;
    private const ADDRESS_SIMILARITY_THRESHOLD = 0.80;
    private const PHONE_SIMILARITY_THRESHOLD = 0.95;
    private const EMAIL_EXACT_MATCH = true;
    private const GEOGRAPHIC_DISTANCE_THRESHOLD = 100; // mètres

    // Poids des sources pour le scoring de confiance
    private const SOURCE_WEIGHTS = [
        'google_maps' => 40,    // Très fiable pour la localisation
        'clearbit' => 30,       // Excellent pour les données entreprise
        'nominatim' => 20,      // Bonnes données publiques
        'hunter' => 10          // Spécialisé emails
    ];

    /**
     * Fusionne et déduplique les résultats de toutes les sources
     */
    public function mergeAndDeduplicate(array $sourcesData): array
    {
        $startTime = microtime(true);
        
        try {
            Log::info('Début de fusion et déduplication');
            
            // Étape 1: Collecter tous les résultats
            $allProspects = $this->collectAllProspects($sourcesData);
            
            // Étape 2: Détecter les doublons potentiels
            $duplicateGroups = $this->detectDuplicates($allProspects);
            
            // Étape 3: Fusionner les doublons
            $mergedProspects = $this->mergeDuplicates($duplicateGroups, $allProspects);
            
            // Étape 4: Calculer les scores de confiance
            $scoredProspects = $this->calculateConfidenceScores($mergedProspects);
            
            // Étape 5: Trier par pertinence
            $sortedProspects = $this->sortByRelevance($scoredProspects);
            
            $duration = round((microtime(true) - $startTime) * 1000, 2);
            
            Log::info('Fusion et déduplication terminée', [
                'original_count' => count($allProspects),
                'merged_count' => count($sortedProspects),
                'duplicates_found' => count($duplicateGroups),
                'duration_ms' => $duration
            ]);
            
            return [
                'merged' => $sortedProspects,
                'duplicates' => $duplicateGroups,
                'deduplication_info' => [
                    'original_count' => count($allProspects),
                    'final_count' => count($sortedProspects),
                    'duplicates_removed' => count($allProspects) - count($sortedProspects),
                    'duplicate_groups' => count($duplicateGroups),
                    'processing_time_ms' => $duration
                ]
            ];
            
        } catch (\Exception $e) {
            Log::error('Erreur lors de la fusion/déduplication', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // En cas d'erreur, retourner les données brutes
            $allProspects = $this->collectAllProspects($sourcesData);
            
            return [
                'merged' => $allProspects,
                'duplicates' => [],
                'deduplication_info' => [
                    'original_count' => count($allProspects),
                    'final_count' => count($allProspects),
                    'error' => $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Collecte tous les prospects de toutes les sources
     */
    private function collectAllProspects(array $sourcesData): array
    {
        $allProspects = [];
        $index = 0;
        
        foreach ($sourcesData as $source => $data) {
            if (empty($data['results']) || !($data['success'] ?? false)) {
                continue;
            }
            
            foreach ($data['results'] as $prospect) {
                $prospect['_internal_id'] = $index++;
                $prospect['_source'] = $source;
                $prospect['_source_index'] = count($allProspects);
                
                $allProspects[] = $prospect;
            }
        }
        
        return $allProspects;
    }

    /**
     * Détecte les doublons potentiels entre les prospects
     */
    private function detectDuplicates(array $prospects): array
    {
        $duplicateGroups = [];
        $processed = [];
        
        for ($i = 0; $i < count($prospects); $i++) {
            if (in_array($i, $processed)) {
                continue;
            }
            
            $currentGroup = [$i];
            $processed[] = $i;
            
            // Comparer avec les prospects suivants
            for ($j = $i + 1; $j < count($prospects); $j++) {
                if (in_array($j, $processed)) {
                    continue;
                }
                
                if ($this->areProspectsDuplicates($prospects[$i], $prospects[$j])) {
                    $currentGroup[] = $j;
                    $processed[] = $j;
                }
            }
            
            // Si le groupe contient plus d'un élément, c'est un doublon
            if (count($currentGroup) > 1) {
                $duplicateGroups[] = [
                    'prospects' => array_map(function($index) use ($prospects) {
                        return $prospects[$index];
                    }, $currentGroup),
                    'indices' => $currentGroup,
                    'similarity_score' => $this->calculateGroupSimilarity($currentGroup, $prospects)
                ];
            }
        }
        
        return $duplicateGroups;
    }

    /**
     * Détermine si deux prospects sont des doublons
     */
    private function areProspectsDuplicates(array $prospect1, array $prospect2): bool
    {
        $similarities = $this->calculateSimilarities($prospect1, $prospect2);
        
        // Email exact match = doublon certain
        if ($similarities['email_exact']) {
            return true;
        }
        
        // Téléphone identique = doublon certain
        if ($similarities['phone'] >= self::PHONE_SIMILARITY_THRESHOLD) {
            return true;
        }
        
        // Nom très similaire + adresse proche = doublon probable
        if ($similarities['name'] >= self::NAME_SIMILARITY_THRESHOLD && 
            ($similarities['address'] >= self::ADDRESS_SIMILARITY_THRESHOLD || 
             $similarities['geographic_distance'] <= self::GEOGRAPHIC_DISTANCE_THRESHOLD)) {
            return true;
        }
        
        // Nom identique + coordonnées proches = doublon probable
        if ($similarities['name'] >= 0.95 && 
            $similarities['geographic_distance'] <= self::GEOGRAPHIC_DISTANCE_THRESHOLD) {
            return true;
        }
        
        return false;
    }

    /**
     * Calcule les similarités entre deux prospects
     */
    private function calculateSimilarities(array $prospect1, array $prospect2): array
    {
        return [
            'name' => $this->calculateStringSimilarity(
                $prospect1['name'] ?? '', 
                $prospect2['name'] ?? ''
            ),
            'address' => $this->calculateStringSimilarity(
                $this->normalizeAddress($prospect1),
                $this->normalizeAddress($prospect2)
            ),
            'phone' => $this->calculatePhoneSimilarity(
                $prospect1['phone'] ?? '', 
                $prospect2['phone'] ?? ''
            ),
            'email_exact' => $this->isExactEmailMatch(
                $prospect1['email'] ?? '', 
                $prospect2['email'] ?? ''
            ),
            'geographic_distance' => $this->calculateGeographicDistance($prospect1, $prospect2)
        ];
    }

    /**
     * Calcule la similarité entre deux chaînes de caractères
     */
    private function calculateStringSimilarity(string $str1, string $str2): float
    {
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }
        
        $str1 = $this->normalizeString($str1);
        $str2 = $this->normalizeString($str2);
        
        if ($str1 === $str2) {
            return 1.0;
        }
        
        // Utiliser similar_text pour calculer la similarité
        $similarity = 0;
        similar_text($str1, $str2, $similarity);
        
        return $similarity / 100.0;
    }

    /**
     * Normalise une chaîne pour la comparaison
     */
    private function normalizeString(string $str): string
    {
        $str = strtolower(trim($str));
        
        // Supprimer les accents
        $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        
        // Supprimer les caractères spéciaux
        $str = preg_replace('/[^a-z0-9\s]/', '', $str);
        
        // Normaliser les espaces
        $str = preg_replace('/\s+/', ' ', $str);
        
        return trim($str);
    }

    /**
     * Normalise une adresse pour la comparaison
     */
    private function normalizeAddress(array $prospect): string
    {
        $addressParts = [];
        
        if (!empty($prospect['address']['street'])) {
            $addressParts[] = $prospect['address']['street'];
        } elseif (!empty($prospect['address'])) {
            $addressParts[] = $prospect['address'];
        }
        
        if (!empty($prospect['city'])) {
            $addressParts[] = $prospect['city'];
        }
        
        if (!empty($prospect['postal_code'])) {
            $addressParts[] = $prospect['postal_code'];
        }
        
        return implode(' ', $addressParts);
    }

    /**
     * Calcule la similarité entre deux numéros de téléphone
     */
    private function calculatePhoneSimilarity(string $phone1, string $phone2): float
    {
        if (empty($phone1) || empty($phone2)) {
            return 0.0;
        }
        
        // Normaliser les numéros (supprimer espaces, tirets, points)
        $phone1 = preg_replace('/[^\d+]/', '', $phone1);
        $phone2 = preg_replace('/[^\d+]/', '', $phone2);
        
        // Si identiques après normalisation
        if ($phone1 === $phone2) {
            return 1.0;
        }
        
        // Comparer les 8 derniers chiffres (numéro local)
        $suffix1 = substr($phone1, -8);
        $suffix2 = substr($phone2, -8);
        
        if (strlen($suffix1) >= 8 && strlen($suffix2) >= 8 && $suffix1 === $suffix2) {
            return 0.95;
        }
        
        return 0.0;
    }

    /**
     * Vérifie si deux emails sont identiques
     */
    private function isExactEmailMatch(string $email1, string $email2): bool
    {
        if (empty($email1) || empty($email2)) {
            return false;
        }
        
        return strtolower(trim($email1)) === strtolower(trim($email2));
    }

    /**
     * Calcule la distance géographique entre deux prospects
     */
    private function calculateGeographicDistance(array $prospect1, array $prospect2): float
    {
        $lat1 = $prospect1['coordinates']['lat'] ?? null;
        $lon1 = $prospect1['coordinates']['lng'] ?? null;
        $lat2 = $prospect2['coordinates']['lat'] ?? null;
        $lon2 = $prospect2['coordinates']['lng'] ?? null;
        
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return PHP_FLOAT_MAX;
        }
        
        // Formule de Haversine pour calculer la distance
        $earthRadius = 6371000; // Rayon de la Terre en mètres
        
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        
        $a = sin($dLat/2) * sin($dLat/2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon/2) * sin($dLon/2);
             
        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
        
        return $earthRadius * $c;
    }

    /**
     * Fusionne les doublons détectés
     */
    private function mergeDuplicates(array $duplicateGroups, array $allProspects): array
    {
        $merged = [];
        $usedIndices = [];
        
        // Fusionner chaque groupe de doublons
        foreach ($duplicateGroups as $group) {
            $mergedProspect = $this->mergeProspectGroup($group['prospects']);
            $merged[] = $mergedProspect;
            
            foreach ($group['indices'] as $index) {
                $usedIndices[] = $index;
            }
        }
        
        // Ajouter les prospects uniques (non dupliqués)
        for ($i = 0; $i < count($allProspects); $i++) {
            if (!in_array($i, $usedIndices)) {
                $merged[] = $allProspects[$i];
            }
        }
        
        return $merged;
    }

    /**
     * Fusionne un groupe de prospects dupliqués
     */
    private function mergeProspectGroup(array $prospects): array
    {
        $merged = array_shift($prospects); // Commencer avec le premier
        
        foreach ($prospects as $prospect) {
            $merged = $this->mergeTwoProspects($merged, $prospect);
        }
        
        // Ajouter les métadonnées de fusion
        $merged['_merged_from_sources'] = array_unique(
            array_column($prospects + [$merged], '_source')
        );
        $merged['_is_merged'] = true;
        
        return $merged;
    }

    /**
     * Fusionne deux prospects en privilégiant la meilleure donnée
     */
    private function mergeTwoProspects(array $prospect1, array $prospect2): array
    {
        $merged = $prospect1;
        
        foreach ($prospect2 as $key => $value) {
            if (empty($merged[$key]) && !empty($value)) {
                $merged[$key] = $value;
            } elseif ($this->isBetterValue($merged[$key] ?? null, $value, $key)) {
                $merged[$key] = $value;
            }
        }
        
        return $merged;
    }

    /**
     * Détermine si une valeur est meilleure qu'une autre pour un champ donné
     */
    private function isBetterValue($current, $new, string $field): bool
    {
        if (empty($new)) {
            return false;
        }
        
        if (empty($current)) {
            return true;
        }
        
        // Règles spécifiques par champ
        switch ($field) {
            case 'description':
                return strlen($new) > strlen($current);
                
            case 'phone':
                // Préférer les numéros français formatés
                return $this->isFormattedFrenchPhone($new) && !$this->isFormattedFrenchPhone($current);
                
            case 'website':
                // Préférer les URLs HTTPS
                return strpos($new, 'https://') === 0 && strpos($current, 'https://') !== 0;
                
            case 'coordinates':
                // Préférer les coordonnées plus précises (plus de décimales)
                $newPrecision = $this->getCoordinatePrecision($new);
                $currentPrecision = $this->getCoordinatePrecision($current);
                return $newPrecision > $currentPrecision;
                
            default:
                return false;
        }
    }

    /**
     * Calcule les scores de confiance pour chaque prospect
     */
    private function calculateConfidenceScores(array $prospects): array
    {
        foreach ($prospects as &$prospect) {
            $prospect['confidence_score'] = $this->calculateProspectConfidence($prospect);
        }
        
        return $prospects;
    }

    /**
     * Calcule le score de confiance d'un prospect
     */
    private function calculateProspectConfidence(array $prospect): array
    {
        $score = 0;
        $details = [];
        
        // Score basé sur la source
        $sourceScore = self::SOURCE_WEIGHTS[$prospect['_source']] ?? 0;
        $score += $sourceScore;
        $details['source_score'] = $sourceScore;
        
        // Score basé sur la complétude des données
        $completenessScore = $this->calculateCompletenessScore($prospect);
        $score += $completenessScore;
        $details['completeness_score'] = $completenessScore;
        
        // Score basé sur la qualité des données
        $qualityScore = $this->calculateQualityScore($prospect);
        $score += $qualityScore;
        $details['quality_score'] = $qualityScore;
        
        // Bonus pour les prospects fusionnés (plus de données)
        if (!empty($prospect['_is_merged'])) {
            $mergedBonus = 10;
            $score += $mergedBonus;
            $details['merged_bonus'] = $mergedBonus;
        }
        
        return [
            'total' => min(100, $score), // Plafonner à 100
            'details' => $details
        ];
    }

    /**
     * Calcule le score de complétude des données
     */
    private function calculateCompletenessScore(array $prospect): int
    {
        $fields = [
            'name' => 5,
            'company' => 3,
            'address' => 4,
            'city' => 2,
            'postal_code' => 2,
            'phone' => 4,
            'email' => 4,
            'website' => 3,
            'description' => 2,
            'coordinates' => 3
        ];
        
        $score = 0;
        
        foreach ($fields as $field => $weight) {
            if (!empty($prospect[$field])) {
                $score += $weight;
            }
        }
        
        return $score;
    }

    /**
     * Calcule le score de qualité des données
     */
    private function calculateQualityScore(array $prospect): int
    {
        $score = 0;
        
        // Qualité du nom
        if (!empty($prospect['name']) && strlen($prospect['name']) > 3) {
            $score += 3;
        }
        
        // Qualité de l'email
        if (!empty($prospect['email']) && filter_var($prospect['email'], FILTER_VALIDATE_EMAIL)) {
            $score += 4;
        }
        
        // Qualité du téléphone
        if (!empty($prospect['phone']) && $this->isValidPhone($prospect['phone'])) {
            $score += 3;
        }
        
        // Qualité du site web
        if (!empty($prospect['website']) && filter_var($prospect['website'], FILTER_VALIDATE_URL)) {
            $score += 2;
        }
        
        // Qualité des coordonnées
        if (!empty($prospect['coordinates']['lat']) && !empty($prospect['coordinates']['lng'])) {
            $score += 2;
        }
        
        return $score;
    }

    /**
     * Trie les prospects par pertinence (score de confiance décroissant)
     */
    private function sortByRelevance(array $prospects): array
    {
        usort($prospects, function ($a, $b) {
            $scoreA = $a['confidence_score']['total'] ?? 0;
            $scoreB = $b['confidence_score']['total'] ?? 0;
            
            return $scoreB <=> $scoreA;
        });
        
        return $prospects;
    }

    /**
     * Calcule la similarité globale d'un groupe de doublons
     */
    private function calculateGroupSimilarity(array $indices, array $prospects): float
    {
        if (count($indices) < 2) {
            return 1.0;
        }
        
        $totalSimilarity = 0;
        $comparisons = 0;
        
        for ($i = 0; $i < count($indices); $i++) {
            for ($j = $i + 1; $j < count($indices); $j++) {
                $similarities = $this->calculateSimilarities(
                    $prospects[$indices[$i]], 
                    $prospects[$indices[$j]]
                );
                
                $avgSimilarity = (
                    $similarities['name'] + 
                    $similarities['address'] + 
                    ($similarities['phone'] > 0 ? $similarities['phone'] : 0)
                ) / 3;
                
                $totalSimilarity += $avgSimilarity;
                $comparisons++;
            }
        }
        
        return $comparisons > 0 ? $totalSimilarity / $comparisons : 0;
    }

    /**
     * Vérifie si un numéro de téléphone est formaté français
     */
    private function isFormattedFrenchPhone(string $phone): bool
    {
        return preg_match('/^0[1-9](\s?\d{2}){4}$/', $phone) === 1;
    }

    /**
     * Calcule la précision des coordonnées GPS
     */
    private function getCoordinatePrecision(array $coordinates): int
    {
        if (empty($coordinates['lat']) || empty($coordinates['lng'])) {
            return 0;
        }
        
        $latDecimals = strlen(substr(strrchr($coordinates['lat'], "."), 1));
        $lngDecimals = strlen(substr(strrchr($coordinates['lng'], "."), 1));
        
        return min($latDecimals, $lngDecimals);
    }

    /**
     * Valide un numéro de téléphone
     */
    private function isValidPhone(string $phone): bool
    {
        $normalized = preg_replace('/[^\d+]/', '', $phone);
        
        // Au moins 10 chiffres pour un numéro valide
        return strlen($normalized) >= 10;
    }
}