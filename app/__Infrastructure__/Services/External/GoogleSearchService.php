<?php

namespace App\__Infrastructure__\Services\External;

use App\__Domain__\Data\Enrichment\WebScrapingResult;
use App\__Domain__\Data\Enrichment\ContactData;
use App\__Domain__\Data\Enrichment\ValidationResult;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverWait;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Illuminate\Support\Facades\Log;

class GoogleSearchService
{
    private string $seleniumHost;
    private int $timeout;
    private array $userAgents;
    private array $emailRegexPatterns;

    public function __construct()
    {
        $this->seleniumHost = config('services.google_search.selenium_host', 'http://localhost:4444');
        $this->timeout = config('services.google_search.timeout', 30);
        $this->userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        $this->emailRegexPatterns = [
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            '/mailto:([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,})/i'
        ];
    }

    public function searchProspectContacts(
        string $prospectName,
        string $prospectCompany,
        array $options = []
    ): WebScrapingResult {
        $startTime = microtime(true);
        $driver = null;

        try {
            $driver = $this->createWebDriver();
            
            // Construire les requêtes de recherche avec opérateurs Google
            $searchQueries = $this->buildGoogleSearchQueries($prospectName, $prospectCompany, $options);
            
            $allContacts = [];
            $searchMetadata = [];

            foreach ($searchQueries as $index => $query) {
                try {
                    $results = $this->performGoogleSearch($driver, $query);
                    $contacts = $this->extractContactsFromResults($results, [
                        'prospect_name' => $prospectName,
                        'prospect_company' => $prospectCompany,
                        'query_index' => $index
                    ]);
                    
                    $allContacts = array_merge($allContacts, $contacts);
                    $searchMetadata["query_{$index}"] = [
                        'query' => $query,
                        'results_count' => count($results),
                        'contacts_found' => count($contacts)
                    ];
                    
                    // Délai respectueux entre les recherches
                    if ($index < count($searchQueries) - 1) {
                        sleep(rand(2, 4));
                    }
                    
                } catch (\Exception $e) {
                    Log::warning('Google search query failed', [
                        'query' => $query,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Dédupliquer les contacts
            $uniqueContacts = $this->deduplicateContacts($allContacts);
            
            // Validation des contacts
            $validation = $this->validateContacts($uniqueContacts);

            $executionTime = (microtime(true) - $startTime) * 1000;

            return WebScrapingResult::success(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'google_search',
                contacts: $uniqueContacts,
                validation: $validation,
                metadata: array_merge($searchMetadata, [
                    'total_queries' => count($searchQueries),
                    'unique_contacts' => count($uniqueContacts),
                    'selenium_host' => $this->seleniumHost
                ]),
                executionTimeMs: $executionTime
            );

        } catch (\Exception $e) {
            Log::error('Google search error', [
                'prospect_name' => $prospectName,
                'prospect_company' => $prospectCompany,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return WebScrapingResult::failure(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'google_search',
                errorMessage: $e->getMessage(),
                executionTimeMs: (microtime(true) - $startTime) * 1000
            );
        } finally {
            if ($driver) {
                $driver->quit();
            }
        }
    }

    private function createWebDriver(): RemoteWebDriver
    {
        $chromeOptions = new ChromeOptions();
        $chromeOptions->addArguments([
            '--headless',
            '--no-sandbox',
            '--disable-dev-shm-usage',
            '--disable-gpu',
            '--window-size=1920,1080',
            '--disable-web-security',
            '--disable-features=VizDisplayCompositor'
        ]);
        
        $userAgent = $this->userAgents[array_rand($this->userAgents)];
        $chromeOptions->addArguments(["--user-agent={$userAgent}"]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $chromeOptions);

        return RemoteWebDriver::create($this->seleniumHost, $capabilities, 5000, 5000);
    }

    private function buildGoogleSearchQueries(string $prospectName, string $prospectCompany, array $options): array
    {
        $queries = [];
        $escapedName = '"' . addslashes(trim($prospectName)) . '"';
        $escapedCompany = '"' . addslashes(trim($prospectCompany)) . '"';

        // Requête 1: Recherche exacte avec email
        if (!empty($prospectName) && !empty($prospectCompany)) {
            $queries[] = "{$escapedName} {$escapedCompany} email OR contact OR \"adresse email\"";
        }

        // Requête 2: Recherche sur LinkedIn avec email
        $queries[] = "site:linkedin.com {$escapedName} {$escapedCompany} email";

        // Requête 3: Recherche sur site de l'entreprise
        if (!empty($options['company_domain'])) {
            $queries[] = "site:{$options['company_domain']} {$escapedName} email OR contact";
        }

        // Requête 4: Recherche générale avec opérateurs
        $queries[] = "{$escapedName} AND {$escapedCompany} AND (email OR contact OR \"nous joindre\")";

        // Requête 5: Recherche sur des sites de contacts professionnels
        $queries[] = "(site:viadeo.com OR site:xing.com OR site:about.me) {$escapedName} {$escapedCompany}";

        // Requête 6: Recherche d'annuaires professionnels français
        $queries[] = "(site:pagesjaunes.fr OR site:societe.com) {$escapedCompany} {$escapedName} email";

        return array_filter($queries);
    }

    private function performGoogleSearch(RemoteWebDriver $driver, string $query): array
    {
        try {
            // Aller sur Google
            $driver->get('https://www.google.com');
            
            // Gérer les cookies si nécessaire
            $this->handleGoogleCookies($driver);
            
            // Trouver la barre de recherche
            $searchBox = $driver->findElement(WebDriverBy::name('q'));
            $searchBox->clear();
            $searchBox->sendKeys($query);
            $searchBox->submit();
            
            // Attendre que les résultats se chargent
            $wait = new WebDriverWait($driver, 10);
            $wait->until(
                WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('search'))
            );
            
            // Extraire les résultats
            $results = [];
            $resultElements = $driver->findElements(WebDriverBy::cssSelector('div.g, div[data-ved]'));
            
            foreach ($resultElements as $element) {
                try {
                    $titleElement = $element->findElement(WebDriverBy::cssSelector('h3'));
                    $linkElement = $element->findElement(WebDriverBy::cssSelector('a[href]'));
                    $snippetElements = $element->findElements(WebDriverBy::cssSelector('span, div'));
                    
                    $snippet = '';
                    foreach ($snippetElements as $snippetEl) {
                        $text = trim($snippetEl->getText());
                        if (!empty($text) && strlen($text) > 20) {
                            $snippet .= $text . ' ';
                        }
                    }
                    
                    if ($titleElement && $linkElement) {
                        $results[] = [
                            'title' => trim($titleElement->getText()),
                            'url' => $linkElement->getAttribute('href'),
                            'snippet' => trim($snippet),
                            'html' => $element->getAttribute('outerHTML')
                        ];
                    }
                } catch (\Exception $e) {
                    // Ignorer les éléments qui ne peuvent pas être traités
                    continue;
                }
            }
            
            return array_slice($results, 0, 10); // Limiter à 10 résultats
            
        } catch (\Exception $e) {
            Log::error('Google search execution failed', [
                'query' => $query,
                'message' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function handleGoogleCookies(RemoteWebDriver $driver): void
    {
        try {
            // Essayer de cliquer sur "Tout accepter" ou "Accept all"
            $acceptButtons = $driver->findElements(WebDriverBy::cssSelector(
                'button[aria-label*="Accept"], button[aria-label*="Accepter"], #L2AGLb, button:contains("Accept all")'
            ));
            
            foreach ($acceptButtons as $button) {
                if ($button->isDisplayed()) {
                    $button->click();
                    sleep(1);
                    break;
                }
            }
        } catch (\Exception $e) {
            // Pas grave si on ne peut pas gérer les cookies
        }
    }

    private function extractContactsFromResults(array $results, array $context): array
    {
        $contacts = [];

        foreach ($results as $result) {
            // Extraire les emails du snippet et du HTML
            $textToSearch = $result['snippet'] . ' ' . strip_tags($result['html']);
            
            foreach ($this->emailRegexPatterns as $pattern) {
                if (preg_match_all($pattern, $textToSearch, $matches)) {
                    foreach ($matches[0] as $email) {
                        $email = strtolower(trim($email));
                        
                        // Validation basique de l'email
                        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                            continue;
                        }

                        // Éviter les emails génériques
                        if ($this->isGenericEmail($email)) {
                            continue;
                        }

                        // Calculer le score de pertinence
                        $score = $this->calculateEmailRelevanceScore($email, $result, $context);
                        
                        $contacts[] = ContactData::email(
                            email: $email,
                            validationScore: $score,
                            confidenceLevel: $this->getConfidenceLevelFromScore($score),
                            context: [
                                'source_url' => $result['url'],
                                'source_title' => $result['title'],
                                'found_in' => 'google_search',
                                'snippet' => substr($result['snippet'], 0, 200),
                                'query_index' => $context['query_index'] ?? 0
                            ],
                            validationDetails: [
                                'regex_pattern' => $pattern,
                                'email_domain' => substr(strrchr($email, "@"), 1),
                                'google_rank' => array_search($result, $results) + 1
                            ]
                        );
                    }
                }
            }
        }

        return $contacts;
    }

    private function deduplicateContacts(array $contacts): array
    {
        $unique = [];
        $seenEmails = [];

        foreach ($contacts as $contact) {
            $email = $contact->value;
            
            if (!in_array($email, $seenEmails)) {
                $seenEmails[] = $email;
                $unique[] = $contact;
            } elseif ($contact->validationScore > 0) {
                // Si on a déjà cet email, garder celui avec le meilleur score
                for ($i = 0; $i < count($unique); $i++) {
                    if ($unique[$i]->value === $email && $contact->validationScore > $unique[$i]->validationScore) {
                        $unique[$i] = $contact;
                        break;
                    }
                }
            }
        }

        return $unique;
    }

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
            '/example@/',
            '/support@/',
            '/hello@/',
            '/contact@.*\.com$/', // contact générique
        ];

        foreach ($genericPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }

        return false;
    }

    private function calculateEmailRelevanceScore(string $email, array $result, array $context): float
    {
        $score = 40.0; // Score de base plus bas que DuckDuckGo

        // Bonus pour position dans les premiers résultats Google
        $googleRank = array_search($result, $context) + 1;
        if ($googleRank <= 3) {
            $score += 25;
        } elseif ($googleRank <= 7) {
            $score += 15;
        } else {
            $score += 5;
        }

        // Bonus si l'email contient le nom de l'entreprise
        $company = strtolower($context['prospect_company'] ?? '');
        $emailDomain = substr(strrchr($email, "@"), 1);
        
        if (!empty($company)) {
            $companyWords = explode(' ', $company);
            foreach ($companyWords as $word) {
                if (strlen($word) > 3 && stripos($emailDomain, $word) !== false) {
                    $score += 35;
                    break;
                }
            }
        }

        // Bonus pour les domaines professionnels
        if (!$this->isFreeEmailDomain($emailDomain)) {
            $score += 25;
        }

        // Bonus si le nom du prospect apparaît dans l'email
        $prospectName = strtolower($context['prospect_name'] ?? '');
        if (!empty($prospectName)) {
            $nameWords = explode(' ', $prospectName);
            foreach ($nameWords as $word) {
                if (strlen($word) > 2 && stripos($email, $word) !== false) {
                    $score += 30;
                    break;
                }
            }
        }

        // Bonus pour mots-clés dans le titre/snippet
        $textToCheck = strtolower($result['title'] . ' ' . $result['snippet']);
        if (stripos($textToCheck, 'contact') !== false) {
            $score += 20;
        }
        if (stripos($textToCheck, 'linkedin') !== false) {
            $score += 15;
        }

        return min(100.0, max(0.0, $score));
    }

    private function isFreeEmailDomain(string $domain): bool
    {
        $freeEmailDomains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'free.fr', 'orange.fr', 'laposte.net', 'sfr.fr',
            'wanadoo.fr', 'voila.fr', 'club-internet.fr', 'live.com',
            'msn.com', 'aol.com', 'ymail.com'
        ];

        return in_array(strtolower($domain), $freeEmailDomains);
    }

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

    private function validateContacts(array $contacts): ValidationResult
    {
        if (empty($contacts)) {
            return ValidationResult::invalid(0, ['No contacts found']);
        }

        $totalScore = 0;
        $validContacts = 0;
        $ruleScores = [];

        foreach ($contacts as $contact) {
            if ($contact->validationScore >= 50) { // Seuil ajusté pour Google
                $validContacts++;
                $totalScore += $contact->validationScore;
            }
        }

        if ($validContacts === 0) {
            return ValidationResult::invalid(0, ['No valid contacts found']);
        }

        $averageScore = $totalScore / $validContacts;
        $ruleScores['contact_quality'] = $averageScore;
        $ruleScores['contact_count'] = min(100, $validContacts * 25); // Bonus pour quantité
        $ruleScores['google_source'] = 85; // Bonus pour source Google

        $overallScore = ($averageScore * 0.7) + ($ruleScores['contact_count'] * 0.2) + ($ruleScores['google_source'] * 0.1);

        return ValidationResult::create(
            overallScore: $overallScore,
            ruleScores: $ruleScores,
            validationMessages: [
                "Found {$validContacts} valid contacts from Google search",
                "Average contact quality score: " . round($averageScore, 2),
                "Google search provides high-quality results"
            ]
        );
    }

    public function isConfigured(): bool
    {
        return !empty($this->seleniumHost);
    }

    public function getServiceInfo(): array
    {
        return [
            'name' => 'Google Search with Selenium',
            'type' => 'web_search_selenium',
            'available' => $this->isConfigured(),
            'description' => 'Recherche Google avec opérateurs et Selenium WebDriver',
            'rate_limit' => 'Respectful crawling, ~1 request/3 seconds',
            'cost' => 'Free (requires Selenium server)'
        ];
    }
}