<?php

namespace App\__Infrastructure__\Services\External;

use App\__Domain__\Data\Enrichment\WebScrapingResult;
use App\__Domain__\Data\Enrichment\ContactData;
use App\__Domain__\Data\Enrichment\ValidationResult;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UniversalScraperService
{
    private int $timeout;
    private array $userAgents;
    private array $emailRegexPatterns;
    private array $phoneRegexPatterns;
    private array $socialMediaPatterns;

    public function __construct()
    {
        $this->timeout = config('services.universal_scraper.timeout', 30);
        $this->userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        $this->emailRegexPatterns = [
            '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
            '/mailto:([A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,})/i'
        ];
        $this->phoneRegexPatterns = [
            '/(?:\+33|0)[1-9](?:[0-9]{8})/',
            '/(?:\+33\s?|0)(?:[1-9]\s?)(?:(?:[0-9]{2}\s?){4})/',
            '/(?:\+33|0)[1-9](?:\.[0-9]{2}){4}/',
            '/(?:\+33|0)[1-9](?:-[0-9]{2}){4}/',
        ];
        $this->socialMediaPatterns = [
            '/linkedin\.com\/in\/([a-zA-Z0-9-]+)/',
            '/twitter\.com\/([a-zA-Z0-9_]+)/',
            '/facebook\.com\/([a-zA-Z0-9.]+)/'
        ];
    }

    public function scrapeUrls(
        array $urls,
        string $prospectName,
        string $prospectCompany,
        array $options = []
    ): WebScrapingResult {
        $startTime = microtime(true);
        
        try {
            $allContacts = [];
            $urlMetadata = [];

            foreach ($urls as $index => $url) {
                try {
                    $pageContent = $this->fetchPageContent($url);
                    if (empty($pageContent)) {
                        continue;
                    }

                    $contacts = $this->extractContactsFromPage($pageContent, $url, [
                        'prospect_name' => $prospectName,
                        'prospect_company' => $prospectCompany,
                        'url_index' => $index
                    ]);

                    $allContacts = array_merge($allContacts, $contacts);
                    $urlMetadata["url_{$index}"] = [
                        'url' => $url,
                        'contacts_found' => count($contacts),
                        'page_size' => strlen($pageContent)
                    ];

                    // Délai respectueux entre les requêtes
                    if ($index < count($urls) - 1) {
                        sleep(rand(1, 3));
                    }

                } catch (\Exception $e) {
                    Log::warning('URL scraping failed', [
                        'url' => $url,
                        'error' => $e->getMessage()
                    ]);
                    $urlMetadata["url_{$index}"] = [
                        'url' => $url,
                        'error' => $e->getMessage(),
                        'contacts_found' => 0
                    ];
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
                source: 'universal_scraper',
                contacts: $uniqueContacts,
                validation: $validation,
                metadata: array_merge($urlMetadata, [
                    'total_urls' => count($urls),
                    'successful_scrapes' => count(array_filter($urlMetadata, fn($meta) => !isset($meta['error']))),
                    'unique_contacts' => count($uniqueContacts)
                ]),
                executionTimeMs: $executionTime
            );

        } catch (\Exception $e) {
            Log::error('Universal scraper error', [
                'prospect_name' => $prospectName,
                'prospect_company' => $prospectCompany,
                'urls' => $urls,
                'message' => $e->getMessage()
            ]);

            return WebScrapingResult::failure(
                prospectName: $prospectName,
                prospectCompany: $prospectCompany,
                source: 'universal_scraper',
                errorMessage: $e->getMessage(),
                executionTimeMs: (microtime(true) - $startTime) * 1000
            );
        }
    }

    private function fetchPageContent(string $url): ?string
    {
        try {
            $userAgent = $this->userAgents[array_rand($this->userAgents)];
            
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'User-Agent' => $userAgent,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                    'Accept-Language' => 'fr-FR,fr;q=0.9,en;q=0.8',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'DNT' => '1',
                    'Connection' => 'keep-alive',
                    'Upgrade-Insecure-Requests' => '1'
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('Failed to fetch URL', [
                    'url' => $url,
                    'status' => $response->status()
                ]);
                return null;
            }

            return $response->body();

        } catch (\Exception $e) {
            Log::error('Error fetching URL', [
                'url' => $url,
                'message' => $e->getMessage()
            ]);
            return null;
        }
    }

    private function extractContactsFromPage(string $html, string $url, array $context): array
    {
        $contacts = [];
        
        try {
            $crawler = new Crawler($html);
            
            // Extraire le texte de la page pour la recherche
            $pageText = $crawler->text();
            
            // Recherche d'emails
            $emails = $this->extractEmails($pageText, $crawler, $url, $context);
            $contacts = array_merge($contacts, $emails);
            
            // Recherche de téléphones
            $phones = $this->extractPhones($pageText, $url, $context);
            $contacts = array_merge($contacts, $phones);
            
            // Recherche de réseaux sociaux
            $socialMedia = $this->extractSocialMedia($pageText, $url, $context);
            $contacts = array_merge($contacts, $socialMedia);
            
            // Recherche de sites web
            $websites = $this->extractWebsites($crawler, $url, $context);
            $contacts = array_merge($contacts, $websites);

        } catch (\Exception $e) {
            Log::error('Error extracting contacts from page', [
                'url' => $url,
                'message' => $e->getMessage()
            ]);
        }

        return $contacts;
    }

    private function extractEmails(string $text, Crawler $crawler, string $sourceUrl, array $context): array
    {
        $contacts = [];
        $foundEmails = [];

        foreach ($this->emailRegexPatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $email) {
                    $email = strtolower(trim($email));
                    
                    if (in_array($email, $foundEmails) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        continue;
                    }

                    if ($this->isGenericEmail($email)) {
                        continue;
                    }

                    $foundEmails[] = $email;
                    
                    // Analyser le contexte de l'email dans la page
                    $emailContext = $this->analyzeEmailContext($email, $text, $crawler);
                    $score = $this->calculateEmailScore($email, $emailContext, $context, $sourceUrl);
                    
                    $contacts[] = ContactData::email(
                        email: $email,
                        validationScore: $score,
                        confidenceLevel: $this->getConfidenceLevelFromScore($score),
                        context: array_merge([
                            'source_url' => $sourceUrl,
                            'found_in' => 'page_content',
                            'url_index' => $context['url_index'] ?? 0
                        ], $emailContext),
                        validationDetails: [
                            'regex_pattern' => $pattern,
                            'email_domain' => substr(strrchr($email, "@"), 1),
                            'page_relevance' => $this->calculatePageRelevance($sourceUrl, $context)
                        ]
                    );
                }
            }
        }

        return $contacts;
    }

    private function extractPhones(string $text, string $sourceUrl, array $context): array
    {
        $contacts = [];
        $foundPhones = [];

        foreach ($this->phoneRegexPatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches)) {
                foreach ($matches[0] as $phone) {
                    $phone = preg_replace('/[^\d+]/', '', $phone);
                    
                    if (in_array($phone, $foundPhones) || strlen($phone) < 10) {
                        continue;
                    }

                    $foundPhones[] = $phone;
                    $score = $this->calculatePhoneScore($phone, $context, $sourceUrl);
                    
                    $contacts[] = ContactData::phone(
                        phone: $phone,
                        validationScore: $score,
                        confidenceLevel: $this->getConfidenceLevelFromScore($score),
                        context: [
                            'source_url' => $sourceUrl,
                            'found_in' => 'page_content',
                            'url_index' => $context['url_index'] ?? 0
                        ],
                        validationDetails: [
                            'regex_pattern' => $pattern,
                            'phone_format' => $this->detectPhoneFormat($phone),
                            'page_relevance' => $this->calculatePageRelevance($sourceUrl, $context)
                        ]
                    );
                }
            }
        }

        return $contacts;
    }

    private function extractSocialMedia(string $text, string $sourceUrl, array $context): array
    {
        $contacts = [];

        foreach ($this->socialMediaPatterns as $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $fullUrl = $match[0];
                    $score = $this->calculateSocialMediaScore($fullUrl, $context, $sourceUrl);
                    
                    $contacts[] = ContactData::website(
                        website: $fullUrl,
                        validationScore: $score,
                        confidenceLevel: $this->getConfidenceLevelFromScore($score),
                        context: [
                            'source_url' => $sourceUrl,
                            'found_in' => 'social_media_link',
                            'platform' => $this->detectSocialPlatform($fullUrl),
                            'url_index' => $context['url_index'] ?? 0
                        ],
                        validationDetails: [
                            'regex_pattern' => $pattern,
                            'profile_username' => $match[1] ?? null,
                            'page_relevance' => $this->calculatePageRelevance($sourceUrl, $context)
                        ]
                    );
                }
            }
        }

        return $contacts;
    }

    private function extractWebsites(Crawler $crawler, string $sourceUrl, array $context): array
    {
        $contacts = [];

        try {
            // Rechercher des liens vers des sites web pertinents
            $crawler->filter('a[href]')->each(function (Crawler $node) use (&$contacts, $sourceUrl, $context) {
                $href = $node->attr('href');
                $linkText = trim($node->text());
                
                if (empty($href) || $this->isInternalLink($href, $sourceUrl)) {
                    return;
                }

                // Filtrer les liens pertinents
                if ($this->isRelevantWebsite($href, $linkText, $context)) {
                    $score = $this->calculateWebsiteScore($href, $linkText, $context, $sourceUrl);
                    
                    $contacts[] = ContactData::website(
                        website: $href,
                        validationScore: $score,
                        confidenceLevel: $this->getConfidenceLevelFromScore($score),
                        context: [
                            'source_url' => $sourceUrl,
                            'found_in' => 'website_link',
                            'link_text' => substr($linkText, 0, 100),
                            'url_index' => $context['url_index'] ?? 0
                        ],
                        validationDetails: [
                            'link_context' => $linkText,
                            'is_company_website' => $this->isCompanyWebsite($href, $context),
                            'page_relevance' => $this->calculatePageRelevance($sourceUrl, $context)
                        ]
                    );
                }
            });

        } catch (\Exception $e) {
            Log::warning('Error extracting websites', [
                'source_url' => $sourceUrl,
                'message' => $e->getMessage()
            ]);
        }

        return $contacts;
    }

    private function analyzeEmailContext(string $email, string $pageText, Crawler $crawler): array
    {
        $context = [];
        
        // Rechercher des éléments de contexte autour de l'email
        $emailPosition = stripos($pageText, $email);
        if ($emailPosition !== false) {
            $before = substr($pageText, max(0, $emailPosition - 100), 100);
            $after = substr($pageText, $emailPosition + strlen($email), 100);
            
            $context['surrounding_text'] = trim($before . ' [EMAIL] ' . $after);
        }

        // Rechercher si l'email est dans un élément avec des mots-clés spécifiques
        try {
            $crawler->filter('*')->each(function (Crawler $node) use ($email, &$context) {
                if (stripos($node->text(), $email) !== false) {
                    $parentText = $node->text();
                    if (stripos($parentText, 'contact') !== false) {
                        $context['in_contact_section'] = true;
                    }
                    if (stripos($parentText, 'équipe') !== false || stripos($parentText, 'team') !== false) {
                        $context['in_team_section'] = true;
                    }
                }
            });
        } catch (\Exception $e) {
            // Ignorer les erreurs d'analyse du DOM
        }

        return $context;
    }

    private function calculateEmailScore(string $email, array $emailContext, array $context, string $sourceUrl): float
    {
        $score = 50.0;

        // Bonus pour contexte
        if (isset($emailContext['in_contact_section'])) {
            $score += 25;
        }
        if (isset($emailContext['in_team_section'])) {
            $score += 20;
        }

        // Bonus pour domaine d'entreprise
        $emailDomain = substr(strrchr($email, "@"), 1);
        $company = strtolower($context['prospect_company'] ?? '');
        if (!empty($company)) {
            $companyWords = explode(' ', $company);
            foreach ($companyWords as $word) {
                if (strlen($word) > 3 && stripos($emailDomain, $word) !== false) {
                    $score += 30;
                    break;
                }
            }
        }

        // Bonus pour nom du prospect
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

        // Bonus pour domaine professionnel
        if (!$this->isFreeEmailDomain($emailDomain)) {
            $score += 20;
        }

        // Bonus pour pertinence de la page
        $score += $this->calculatePageRelevance($sourceUrl, $context) * 0.3;

        return min(100.0, max(0.0, $score));
    }

    private function calculatePhoneScore(string $phone, array $context, string $sourceUrl): float
    {
        $score = 60.0; // Score de base plus élevé pour les téléphones

        // Bonus pour format français
        if (preg_match('/^(?:\+33|0)[1-9]/', $phone)) {
            $score += 20;
        }

        // Bonus pour pertinence de la page
        $score += $this->calculatePageRelevance($sourceUrl, $context) * 0.2;

        return min(100.0, max(0.0, $score));
    }

    private function calculateSocialMediaScore(string $url, array $context, string $sourceUrl): float
    {
        $score = 45.0;

        // Bonus selon la plateforme
        if (stripos($url, 'linkedin.com') !== false) {
            $score += 25; // LinkedIn est plus professionnel
        } elseif (stripos($url, 'twitter.com') !== false) {
            $score += 15;
        } elseif (stripos($url, 'facebook.com') !== false) {
            $score += 10;
        }

        // Bonus pour pertinence de la page
        $score += $this->calculatePageRelevance($sourceUrl, $context) * 0.2;

        return min(100.0, max(0.0, $score));
    }

    private function calculateWebsiteScore(string $url, string $linkText, array $context, string $sourceUrl): float
    {
        $score = 40.0;

        // Bonus pour texte du lien pertinent
        $relevantTexts = ['site web', 'website', 'homepage', 'accueil', 'www'];
        foreach ($relevantTexts as $text) {
            if (stripos($linkText, $text) !== false) {
                $score += 15;
                break;
            }
        }

        // Bonus si c'est le site de l'entreprise
        if ($this->isCompanyWebsite($url, $context)) {
            $score += 30;
        }

        return min(100.0, max(0.0, $score));
    }

    private function calculatePageRelevance(string $url, array $context): float
    {
        $relevance = 50.0;
        
        $company = strtolower($context['prospect_company'] ?? '');
        if (!empty($company)) {
            $companyWords = explode(' ', $company);
            foreach ($companyWords as $word) {
                if (strlen($word) > 3 && stripos($url, $word) !== false) {
                    $relevance += 30;
                    break;
                }
            }
        }

        // Bonus pour types de sites pertinents
        $relevantSites = ['linkedin.com', 'viadeo.com', 'about.me', 'societe.com'];
        foreach ($relevantSites as $site) {
            if (stripos($url, $site) !== false) {
                $relevance += 20;
                break;
            }
        }

        return min(100.0, max(0.0, $relevance));
    }

    private function detectPhoneFormat(string $phone): string
    {
        if (preg_match('/^\+33/', $phone)) {
            return 'international';
        } elseif (preg_match('/^0[1-9]/', $phone)) {
            return 'national';
        }
        return 'unknown';
    }

    private function detectSocialPlatform(string $url): string
    {
        if (stripos($url, 'linkedin.com') !== false) return 'linkedin';
        if (stripos($url, 'twitter.com') !== false) return 'twitter';
        if (stripos($url, 'facebook.com') !== false) return 'facebook';
        return 'unknown';
    }

    private function isInternalLink(string $href, string $sourceUrl): bool
    {
        $sourceDomain = parse_url($sourceUrl, PHP_URL_HOST);
        $hrefDomain = parse_url($href, PHP_URL_HOST);
        
        return $sourceDomain === $hrefDomain || empty($hrefDomain);
    }

    private function isRelevantWebsite(string $href, string $linkText, array $context): bool
    {
        // Filtrer les types de fichiers non pertinents
        $excludedExtensions = ['.pdf', '.doc', '.jpg', '.png', '.gif', '.zip'];
        foreach ($excludedExtensions as $ext) {
            if (stripos($href, $ext) !== false) {
                return false;
            }
        }

        // Inclure si le texte du lien semble pertinent
        $relevantKeywords = ['site', 'web', 'homepage', 'accueil', 'contact', 'about'];
        foreach ($relevantKeywords as $keyword) {
            if (stripos($linkText, $keyword) !== false) {
                return true;
            }
        }

        return $this->isCompanyWebsite($href, $context);
    }

    private function isCompanyWebsite(string $url, array $context): bool
    {
        $company = strtolower($context['prospect_company'] ?? '');
        if (empty($company)) {
            return false;
        }

        $companyWords = explode(' ', $company);
        $domain = parse_url($url, PHP_URL_HOST);
        
        if (!$domain) {
            return false;
        }

        foreach ($companyWords as $word) {
            if (strlen($word) > 3 && stripos($domain, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    private function isGenericEmail(string $email): bool
    {
        $genericPatterns = [
            '/noreply@/', '/no-reply@/', '/donotreply@/',
            '/postmaster@/', '/webmaster@/',
            '/info@example/', '/test@/', '/example@/',
            '/^admin@(localhost|127\.0\.0\.1|test\.|demo\.)/',
            '/^(support|hello)@(test\.|demo\.|localhost)/',
            '/contact@(test\.|demo\.|localhost|example\.)/'
        ];

        foreach ($genericPatterns as $pattern) {
            if (preg_match($pattern, $email)) {
                return true;
            }
        }

        return false;
    }

    private function isFreeEmailDomain(string $domain): bool
    {
        $freeEmailDomains = [
            'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
            'free.fr', 'orange.fr', 'laposte.net', 'sfr.fr',
            'wanadoo.fr', 'voila.fr', 'club-internet.fr'
        ];

        return in_array(strtolower($domain), $freeEmailDomains);
    }

    private function deduplicateContacts(array $contacts): array
    {
        $unique = [];
        $seenValues = [];

        foreach ($contacts as $contact) {
            $key = $contact->type . ':' . $contact->value;
            
            if (!in_array($key, $seenValues)) {
                $seenValues[] = $key;
                $unique[] = $contact;
            } elseif ($contact->validationScore > 0) {
                // Garder celui avec le meilleur score
                for ($i = 0; $i < count($unique); $i++) {
                    $existingKey = $unique[$i]->type . ':' . $unique[$i]->value;
                    if ($existingKey === $key && $contact->validationScore > $unique[$i]->validationScore) {
                        $unique[$i] = $contact;
                        break;
                    }
                }
            }
        }

        return $unique;
    }

    private function getConfidenceLevelFromScore(float $score): string
    {
        if ($score >= 75) {
            return 'high';
        } elseif ($score >= 55) {
            return 'medium';
        } else {
            return 'low';
        }
    }

    private function validateContacts(array $contacts): ValidationResult
    {
        if (empty($contacts)) {
            return ValidationResult::invalid(0, ['No contacts found during scraping']);
        }

        $emailCount = count(array_filter($contacts, fn($c) => $c->type === 'email'));
        $phoneCount = count(array_filter($contacts, fn($c) => $c->type === 'phone'));
        $websiteCount = count(array_filter($contacts, fn($c) => $c->type === 'website'));

        $totalScore = 0;
        $validContacts = 0;

        foreach ($contacts as $contact) {
            if ($contact->validationScore >= 50) {
                $validContacts++;
                $totalScore += $contact->validationScore;
            }
        }

        if ($validContacts === 0) {
            return ValidationResult::invalid(0, ['No valid contacts found during scraping']);
        }

        $averageScore = $totalScore / $validContacts;
        $ruleScores = [
            'contact_quality' => $averageScore,
            'contact_diversity' => min(100, ($emailCount > 0 ? 30 : 0) + ($phoneCount > 0 ? 30 : 0) + ($websiteCount > 0 ? 20 : 0)),
            'scraping_depth' => min(100, $validContacts * 15)
        ];

        $overallScore = ($averageScore * 0.6) + ($ruleScores['contact_diversity'] * 0.2) + ($ruleScores['scraping_depth'] * 0.2);

        return ValidationResult::create(
            overallScore: $overallScore,
            ruleScores: $ruleScores,
            validationMessages: [
                "Found {$validContacts} valid contacts through scraping",
                "Contact types: {$emailCount} emails, {$phoneCount} phones, {$websiteCount} websites",
                "Average quality score: " . round($averageScore, 2)
            ]
        );
    }

    public function isConfigured(): bool
    {
        return true; // Ce service ne nécessite pas de configuration externe
    }

    public function getServiceInfo(): array
    {
        return [
            'name' => 'Universal Web Scraper',
            'type' => 'web_scraper',
            'available' => $this->isConfigured(),
            'description' => 'Scraper universel pour extraire contacts depuis n\'importe quelle page web',
            'rate_limit' => 'Respectful crawling, ~1 request/2 seconds',
            'cost' => 'Free'
        ];
    }
}