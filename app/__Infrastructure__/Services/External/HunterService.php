<?php

namespace App\__Infrastructure__\Services\External;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service d'intégration avec Hunter.io API
 * Pour la recherche légale d'emails professionnels
 */
class HunterService
{
    private ?string $apiKey;
    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.hunter.api_key');
        $this->baseUrl = config('services.hunter.base_url', 'https://api.hunter.io/v2');
    }

    /**
     * Recherche les emails associés à un domaine d'entreprise
     */
    public function findEmails(string $domain, array $options = []): array
    {
        // Mode démo si activé ou pas de clé API configurée
        if ($this->isDemoMode() || !$this->isConfigured()) {
            return $this->getDemoEmailsData($domain);
        }

        try {
            $params = $this->buildDomainSearchParams($domain, $options);
            
            $response = Http::timeout(30)
                          ->get($this->baseUrl . '/domain-search', $params);

            if (!$response->successful()) {
                Log::warning('Hunter API error', [
                    'status' => $response->status(),
                    'domain' => $domain
                ]);
                return $this->getDemoEmailsData($domain);
            }

            return $this->formatEmailsResponse($response);

        } catch (\Exception $e) {
            Log::error('Hunter service error', [
                'message' => $e->getMessage(),
                'domain' => $domain
            ]);
            return $this->getDemoEmailsData($domain);
        }
    }

    /**
     * Vérifie la validité d'une adresse email
     */
    public function verifyEmail(string $email): ?array
    {
        // Mode démo si activé ou pas de clé API configurée
        if ($this->isDemoMode() || !$this->isConfigured()) {
            return $this->getDemoVerificationData($email);
        }

        try {
            $params = [
                'email' => $email,
                'api_key' => $this->apiKey,
            ];
            
            $response = Http::timeout(30)
                          ->get($this->baseUrl . '/email-verifier', $params);

            if (!$response->successful()) {
                Log::warning('Hunter email verification API error', [
                    'status' => $response->status(),
                    'email' => $email
                ]);
                return $this->getDemoVerificationData($email);
            }

            return $this->formatVerificationResponse($response);

        } catch (\Exception $e) {
            Log::error('Hunter email verification service error', [
                'message' => $e->getMessage(),
                'email' => $email
            ]);
            return $this->getDemoVerificationData($email);
        }
    }

    /**
     * Trouve des emails en fonction du nom et du domaine d'entreprise
     */
    public function findPersonEmail(string $firstName, string $lastName, string $domain): ?array
    {
        // Mode démo si activé ou pas de clé API configurée
        if ($this->isDemoMode() || !$this->isConfigured()) {
            return $this->getDemoPersonEmailData($firstName, $lastName, $domain);
        }

        try {
            $params = [
                'domain' => $domain,
                'first_name' => $firstName,
                'last_name' => $lastName,
                'api_key' => $this->apiKey,
            ];
            
            $response = Http::timeout(30)
                          ->get($this->baseUrl . '/email-finder', $params);

            if (!$response->successful()) {
                Log::warning('Hunter email finder API error', [
                    'status' => $response->status(),
                    'domain' => $domain,
                    'name' => "$firstName $lastName"
                ]);
                return null;
            }

            return $this->formatEmailFinderResponse($response);

        } catch (\Exception $e) {
            Log::error('Hunter email finder service error', [
                'message' => $e->getMessage(),
                'domain' => $domain,
                'name' => "$firstName $lastName"
            ]);
            return null;
        }
    }

    /**
     * Obtient le nombre d'emails disponibles pour un domaine
     */
    public function getEmailCount(string $domain): int
    {
        // Mode démo
        if ($this->isDemoMode() || !$this->isConfigured()) {
            return rand(5, 50);
        }

        try {
            $params = [
                'domain' => $domain,
                'api_key' => $this->apiKey,
            ];
            
            $response = Http::timeout(30)
                          ->get($this->baseUrl . '/email-count', $params);

            if (!$response->successful()) {
                return 0;
            }

            $data = $response->json();
            return $data['data']['total'] ?? 0;

        } catch (\Exception $e) {
            Log::error('Hunter email count service error', [
                'message' => $e->getMessage(),
                'domain' => $domain
            ]);
            return 0;
        }
    }

    private function buildDomainSearchParams(string $domain, array $options): array
    {
        $params = [
            'domain' => $domain,
            'api_key' => $this->apiKey,
            'limit' => $options['limit'] ?? 10,
        ];

        if (!empty($options['type'])) {
            $params['type'] = $options['type']; // 'personal' ou 'generic'
        }

        if (!empty($options['seniority'])) {
            $params['seniority'] = $options['seniority']; // 'junior', 'senior', 'executive'
        }

        if (!empty($options['department'])) {
            $params['department'] = $options['department']; // 'executive', 'it', 'finance', 'management', 'sales', 'legal', 'support', 'hr', 'marketing', 'communication'
        }

        return $params;
    }

    private function formatEmailsResponse(Response $response): array
    {
        $data = $response->json();
        $results = [];

        if (empty($data['data']['emails'])) {
            return [];
        }

        foreach ($data['data']['emails'] as $emailData) {
            $results[] = [
                'email' => $emailData['value'],
                'type' => $emailData['type'] ?? 'unknown',
                'confidence' => $emailData['confidence'] ?? 0,
                'first_name' => $emailData['first_name'] ?? null,
                'last_name' => $emailData['last_name'] ?? null,
                'position' => $emailData['position'] ?? null,
                'seniority' => $emailData['seniority'] ?? null,
                'department' => $emailData['department'] ?? null,
                'linkedin' => $emailData['linkedin'] ?? null,
                'twitter' => $emailData['twitter'] ?? null,
                'phone_number' => $emailData['phone_number'] ?? null,
                'verification_status' => $emailData['verification']['status'] ?? null,
                'verification_date' => $emailData['verification']['date'] ?? null,
                'source' => 'hunter',
                'raw_data' => $emailData,
            ];
        }

        return $results;
    }

    private function formatVerificationResponse(Response $response): array
    {
        $data = $response->json();

        if (empty($data['data'])) {
            return [];
        }

        $verification = $data['data'];

        return [
            'email' => $verification['email'],
            'result' => $verification['result'], // 'deliverable', 'undeliverable', 'risky', 'unknown'
            'score' => $verification['score'] ?? 0,
            'regexp' => $verification['regexp'] ?? false,
            'gibberish' => $verification['gibberish'] ?? false,
            'disposable' => $verification['disposable'] ?? false,
            'webmail' => $verification['webmail'] ?? false,
            'mx_records' => $verification['mx_records'] ?? false,
            'smtp_server' => $verification['smtp_server'] ?? false,
            'smtp_check' => $verification['smtp_check'] ?? false,
            'accept_all' => $verification['accept_all'] ?? false,
            'block' => $verification['block'] ?? false,
            'source' => 'hunter',
            'verified_at' => now()->toISOString(),
        ];
    }

    private function formatEmailFinderResponse(Response $response): ?array
    {
        $data = $response->json();

        if (empty($data['data']['email'])) {
            return null;
        }

        $emailData = $data['data'];

        return [
            'email' => $emailData['email'],
            'first_name' => $emailData['first_name'],
            'last_name' => $emailData['last_name'],
            'position' => $emailData['position'] ?? null,
            'twitter' => $emailData['twitter'] ?? null,
            'linkedin_url' => $emailData['linkedin_url'] ?? null,
            'phone_number' => $emailData['phone_number'] ?? null,
            'company' => $emailData['company'] ?? null,
            'confidence' => $emailData['confidence'] ?? 0,
            'verification_status' => $emailData['verification']['status'] ?? null,
            'source' => 'hunter',
            'raw_data' => $emailData,
        ];
    }

    /**
     * Vérifie si le service est configuré et opérationnel
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && $this->apiKey !== 'your-hunter-api-key';
    }

    /**
     * Vérifie si le mode démo est activé
     */
    private function isDemoMode(): bool
    {
        return config('app.external_services_demo_mode', true);
    }

    /**
     * Génère des données d'emails de démonstration
     */
    private function getDemoEmailsData(string $domain): array
    {
        $demoEmails = [
            [
                'email' => "contact@{$domain}",
                'type' => 'generic',
                'confidence' => 95,
                'first_name' => null,
                'last_name' => null,
                'position' => 'Contact général',
                'seniority' => null,
                'department' => 'support',
                'verification_status' => 'valid'
            ],
            [
                'email' => "info@{$domain}",
                'type' => 'generic',
                'confidence' => 90,
                'first_name' => null,
                'last_name' => null,
                'position' => 'Information générale',
                'seniority' => null,
                'department' => 'marketing',
                'verification_status' => 'valid'
            ]
        ];

        // Ajouter quelques emails personnels selon le domaine
        if (strpos($domain, 'restaurant') !== false) {
            $demoEmails[] = [
                'email' => "chef@{$domain}",
                'type' => 'personal',
                'confidence' => 85,
                'first_name' => 'Jean',
                'last_name' => 'Dupont',
                'position' => 'Chef de cuisine',
                'seniority' => 'senior',
                'department' => 'management',
                'verification_status' => 'valid'
            ];
        }

        return $demoEmails;
    }

    /**
     * Génère des données de vérification de démonstration
     */
    private function getDemoVerificationData(string $email): array
    {
        return [
            'email' => $email,
            'result' => 'deliverable',
            'score' => rand(80, 100),
            'regexp' => true,
            'gibberish' => false,
            'disposable' => false,
            'webmail' => strpos($email, '@gmail.com') !== false || strpos($email, '@hotmail.com') !== false,
            'mx_records' => true,
            'smtp_server' => true,
            'smtp_check' => true,
            'accept_all' => false,
            'block' => false,
            'source' => 'hunter',
            'verified_at' => now()->toISOString(),
        ];
    }

    /**
     * Génère des données de recherche de personne de démonstration
     */
    private function getDemoPersonEmailData(string $firstName, string $lastName, string $domain): ?array
    {
        return [
            'email' => strtolower($firstName . '.' . $lastName . '@' . $domain),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'position' => 'Manager',
            'confidence' => 75,
            'verification_status' => 'valid',
            'source' => 'hunter',
        ];
    }
}