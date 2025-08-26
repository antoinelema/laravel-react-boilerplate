<?php

namespace App\__Infrastructure__\Services\External;

use App\__Domain__\Data\Enrichment\WebScrapingResult;
use App\__Domain__\Data\Enrichment\ContactData;
use App\__Domain__\Data\Enrichment\ValidationResult;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration avec DuckDuckGo pour recherche web gratuite
 * Utilise l'API HTML de DuckDuckGo pour récupérer des résultats de recherche
 */
class DuckDuckGoService
{
    private string $baseUrl;
    private int $timeout;
    private array $emailRegexPatterns;

    public function __construct()
    {
        $this->baseUrl = config('services.duckduckgo.base_url', 'https://html.duckduckgo.com');
        $this->timeout = config('services.duckduckgo.timeout', 30);
        $this->emailRegexPatterns = [
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            '/mailto:([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,})/i'
        ];
    }

    /**
     * Recherche des informations de contact pour un prospect
     */
    public function searchProspectContacts(
        string $prospectName, 
        string $prospectCompany, 
        array $options = []
    ): WebScrapingResult {
        $startTime = microtime(true);
        
        try {
            // Construire la requête de recherche
            $searchQuery = $this->buildSearchQuery($prospectName, $prospectCompany, $options);
            
            // Effectuer la recherche
            $searchResults = $this->performSearch($searchQuery);
            
            if (empty($searchResults)) {
                return WebScrapingResult::failure(
                    prospectName: $prospectName,
                    prospectCompany: $prospectCompany,
                    source: 'duckduckgo',
                    errorMessage: 'No search results found',
                    executionTimeMs: (microtime(true) - $startTime) * 1000
                );
            }

            // Extraire les contacts des résultats
            $contacts = $this->extractContactsFromResults($searchResults, [
                'prospect_name' => $prospectName,
                'prospect_company' => $prospectCompany
            ]);

            // Validation basique
            $validation = $this->validateContacts($contacts);

            $executionTime = (microtime(true) - $startTime) * 1000;

            return WebScrapingResult::success(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'duckduckgo',
                contacts: $contacts,
                validation: $validation,
                metadata: [
                    'search_query' => $searchQuery,
                    'results_count' => count($searchResults),
                    'contacts_found' => count($contacts)
                ],
                executionTimeMs: $executionTime
            );

        } catch (\Exception $e) {
            Log::error('DuckDuckGo search error', [
                'prospect_name' => $prospectName,
                'prospect_company' => $prospectCompany,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return WebScrapingResult::failure(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'duckduckgo',
                errorMessage: $e->getMessage(),
                executionTimeMs: (microtime(true) - $startTime) * 1000
            );
        }
    }

    /**
     * Construit la requête de recherche optimisée
     */
    private function buildSearchQuery(string $prospectName, string $prospectCompany, array $options): string
    {
        $queryParts = [];

        // Nom du prospect (entre guillemets pour recherche exacte)
        if (!empty(trim($prospectName))) {
            $queryParts[] = '"' . trim($prospectName) . '"';
        }

        // Entreprise (entre guillemets pour recherche exacte)
        if (!empty(trim($prospectCompany))) {
            $queryParts[] = '"' . trim($prospectCompany) . '"';
        }

        // Mots-clés pour trouver des contacts
        $contactKeywords = $options['contact_keywords'] ?? ['email', 'contact', 'adresse email'];
        $keywordQuery = '(' . implode(' OR ', $contactKeywords) . ')';
        $queryParts[] = $keywordQuery;

        // Site spécifique si fourni
        if (!empty($options['site'])) {
            $queryParts[] = 'site:' . $options['site'];
        }

        return implode(' ', $queryParts);
    }

    /**
     * Effectue la recherche sur DuckDuckGo
     */
    private function performSearch(string $query): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                ])
                ->get($this->baseUrl . '/html/', [
                    'q' => $query,
                    'kl' => 'fr-fr', // Région française
                ]);

            if (!$response->successful()) {
                Log::warning('DuckDuckGo API returned error status', [
                    'status' => $response->status(),
                    'query' => $query
                ]);
                return [];
            }

            return $this->parseSearchResults($response->body());

        } catch (\Exception $e) {
            Log::error('DuckDuckGo search request failed', [
                'query' => $query,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Parse les résultats de recherche HTML de DuckDuckGo
     */
    private function parseSearchResults(string $html): array
    {
        $results = [];
        
        try {
            $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
            
            // Sélecteur pour les résultats de recherche DuckDuckGo
            $crawler->filter('.result__body')->each(function ($node) use (&$results) {
                $titleNode = $node->filter('.result__title a')->first();
                $snippetNode = $node->filter('.result__snippet')->first();
                
                if ($titleNode->count() && $snippetNode->count()) {
                    $results[] = [
                        'title' => trim($titleNode->text()),
                        'url' => $titleNode->attr('href'),
                        'snippet' => trim($snippetNode->text()),
                        'html' => $node->html()
                    ];
                }
            });

        } catch (\Exception $e) {
            Log::error('Error parsing DuckDuckGo results', [
                'message' => $e->getMessage()
            ]);
        }

        return $results;
    }

    /**
     * Extrait les contacts des résultats de recherche
     */
    private function extractContactsFromResults(array $results, array $context): array
    {
        $contacts = [];
        $foundEmails = [];

        foreach ($results as $result) {
            // Extraire les emails du snippet et du HTML
            $textToSearch = $result['snippet'] . ' ' . strip_tags($result['html']);
            
            foreach ($this->emailRegexPatterns as $pattern) {
                if (preg_match_all($pattern, $textToSearch, $matches)) {
                    foreach ($matches[0] as $email) {
                        $email = strtolower(trim($email));
                        
                        // Éviter les doublons
                        if (in_array($email, $foundEmails)) {
                            continue;
                        }
                        
                        // Validation basique de l'email
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            continue;
                        }

                        // Éviter les emails génériques sans valeur
                        if ($this->isGenericEmail($email)) {
                            continue;
                        }

                        $foundEmails[] = $email;
                        
                        // Calculer un score basique basé sur la pertinence
                        $score = $this->calculateEmailRelevanceScore($email, $result, $context);
                        
                        $contacts[] = ContactData::email(
                            email: $email,
                            validationScore: $score,
                            confidenceLevel: $this->getConfidenceLevelFromScore($score),
                            context: [
                                'source_url' => $result['url'],
                                'source_title' => $result['title'],
                                'found_in' => 'search_result',
                                'snippet' => substr($result['snippet'], 0, 200),
                            ],
                            validationDetails: [
                                'regex_pattern' => $pattern,
                                'email_domain' => substr(strrchr($email, "@"), 1),
                                'proximity_score' => $this->calculateProximityScore($email, $textToSearch, $context)
                            ]
                        );
                    }
                }
            }
        }

        return $contacts;
    }

    /**
     * Vérifie si un email est trop générique pour être utile
     */
    private function isGenericEmail(string $email): bool
    {
        $genericPatterns = [
            '/noreply@/',
            '/no-reply@/',
            '/donotreply@/',
            '/postmaster@/',
            '/admin@/',
            '/webmaster@/',
            '/info@example/',
            '/test@/',
            '/example@/'
        ];

        foreach ($genericPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Calcule un score de pertinence pour un email
     */
    private function calculateEmailRelevanceScore(string $email, array $result, array $context): float
    {
        $score = 50.0; // Score de base

        // Bonus si l'email contient le nom de l'entreprise
        $company = strtolower($context['prospect_company'] ?? '');
        $emailDomain = substr(strrchr($email, "@"), 1);
        
        if (!empty($company)) {
            $companyWords = explode(' ', $company);
            foreach ($companyWords as $word) {
                if (strlen($word) > 3 && stripos($emailDomain, $word) !== false) {
                    $score += 30;
                    break;
                }
            }
        }

        // Bonus pour les domaines professionnels (non gratuits)
        if (!$this->isFreeEmailDomain($emailDomain)) {
            $score += 20;
        }

        // Bonus si le nom du prospect apparaît dans l'email
        $prospectName = strtolower($context['prospect_name'] ?? '');
        if (!empty($prospectName)) {
            $nameWords = explode(' ', $prospectName);
            foreach ($nameWords as $word) {
                if (strlen($word) > 2 && stripos($email, $word) !== false) {
                    $score += 25;
                    break;
                }
            }
        }

        // Bonus pour position dans le titre ou snippet
        $textToCheck = strtolower($result['title'] . ' ' . $result['snippet']);
        if (stripos($textToCheck, 'contact') !== false) {
            $score += 15;
        }

        return min(100.0, max(0.0, $score));
    }

    /**
     * Calcule un score de proximité entre l'email et les mots-clés du contexte
     */
    private function calculateProximityScore(string $email, string $text, array $context): float
    {
        $proximityScore = 0;
        $searchTerms = [
            $context['prospect_name'] ?? '',
            $context['prospect_company'] ?? '',
            'contact', 'email', 'adresse'
        ];

        $emailPosition = stripos($text, $email);
        if ($emailPosition === false) {
            return 0;
        }

        foreach ($searchTerms as $term) {
            if (empty($term)) continue;
            
            $termPosition = stripos($text, $term);
            if ($termPosition !== false) {
                $distance = abs($emailPosition - $termPosition);
                if ($distance < 100) { // Mots proches
                    $proximityScore += (100 - $distance) / 10;
                }
            }
        }

        return min(100.0, $proximityScore);
    }

    /**
     * Vérifie si un domaine email est gratuit/générique
     */
    private function isFreeEmailDomain(string $domain): bool
    {
        $freeEmailDomains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'free.fr', 'orange.fr', 'laposte.net', 'sfr.fr',
            'wanadoo.fr', 'voila.fr', 'club-internet.fr'
        ];

        return in_array(strtolower($domain), $freeEmailDomains);
    }

    /**
     * Détermine le niveau de confiance basé sur le score
     */
    private function getConfidenceLevelFromScore(float $score): string
    {
        if ($score >= 80) {
            return 'high';
        } elseif ($score >= 60) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    /**
     * Valide l'ensemble des contacts trouvés
     */
    private function validateContacts(array $contacts): ValidationResult
    {
        if (empty($contacts)) {
            return ValidationResult::invalid(0, ['No contacts found']);
        }

        $totalScore = 0;
        $validContacts = 0;
        $ruleScores = [];

        foreach ($contacts as $contact) {
            if ($contact->validationScore >= 60) {
                $validContacts++;
                $totalScore += $contact->validationScore;
            }
        }

        if ($validContacts === 0) {
            return ValidationResult::invalid(0, ['No valid contacts found']);
        }

        $averageScore = $totalScore / $validContacts;
        $ruleScores['contact_quality'] = $averageScore;
        $ruleScores['contact_count'] = min(100, $validContacts * 20); // Bonus pour quantité

        $overallScore = ($averageScore * 0.8) + ($ruleScores['contact_count'] * 0.2);

        return ValidationResult::create(
            overallScore: $overallScore,
            ruleScores: $ruleScores,
            validationMessages: [
                "Found {$validContacts} valid contacts out of " . count($contacts) . " total",
                "Average contact quality score: " . round($averageScore, 2)
            ]
        );
    }

    /**
     * Vérifie si le service est configuré
     */
    public function isConfigured(): bool
    {
        return !empty($this->baseUrl);
    }

    /**
     * Obtient les informations du service
     */
    public function getServiceInfo(): array
    {
        return [
            'name' => 'DuckDuckGo Search',
            'type' => 'web_search',
            'available' => $this->isConfigured(),
            'description' => 'Recherche web gratuite via DuckDuckGo HTML',
            'rate_limit' => 'Respectful crawling, ~1 request/second',
            'cost' => 'Free'
        ];
    }
}