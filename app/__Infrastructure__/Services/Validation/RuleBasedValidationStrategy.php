<?php

namespace App\__Infrastructure__\Services\Validation;

use App\__Domain__\Data\Enrichment\ContactData;
use App\__Domain__\Data\Enrichment\ValidationResult;
use Illuminate\Support\Facades\Log;

class RuleBasedValidationStrategy
{
    private array $validationRules;
    private array $emailDomainRules;
    private array $phoneValidationRules;
    private array $websiteValidationRules;

    public function __construct()
    {
        $this->initializeValidationRules();
    }

    private function initializeValidationRules(): void
    {
        $this->validationRules = [
            'email' => [
                'min_score' => 40,
                'max_score' => 100,
                'required_format' => true,
                'domain_validation' => true,
                'context_analysis' => true
            ],
            'phone' => [
                'min_score' => 50,
                'max_score' => 100,
                'required_format' => true,
                'country_validation' => true,
                'length_validation' => true
            ],
            'website' => [
                'min_score' => 30,
                'max_score' => 100,
                'url_validation' => true,
                'domain_validation' => true,
                'relevance_check' => true
            ]
        ];

        $this->emailDomainRules = [
            'free_domains' => [
                'gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com',
                'free.fr', 'orange.fr', 'laposte.net', 'sfr.fr',
                'wanadoo.fr', 'voila.fr', 'club-internet.fr'
            ],
            'business_indicators' => [
                '.com', '.fr', '.eu', '.org', '.net'
            ],
            'suspicious_patterns' => [
                'noreply', 'no-reply', 'donotreply', 'postmaster',
                'admin', 'webmaster', 'test', 'example'
            ]
        ];

        $this->phoneValidationRules = [
            'french_patterns' => [
                '/^(?:\+33|0)[1-9](?:[0-9]{8})$/',
                '/^(?:\+33\s?|0)(?:[1-9]\s?)(?:(?:[0-9]{2}\s?){4})$/'
            ],
            'international_patterns' => [
                '/^\+[1-9]\d{1,14}$/'
            ],
            'min_length' => 10,
            'max_length' => 15
        ];

        $this->websiteValidationRules = [
            'valid_schemes' => ['http', 'https'],
            'business_indicators' => ['.com', '.fr', '.org', '.net', '.eu'],
            'social_platforms' => [
                'linkedin.com', 'twitter.com', 'facebook.com',
                'instagram.com', 'youtube.com'
            ]
        ];
    }

    public function validateContacts(array $contacts, array $context = []): ValidationResult
    {
        if (empty($contacts)) {
            return ValidationResult::invalid(0, ['No contacts to validate']);
        }

        $validationResults = [];
        $totalScore = 0;
        $validContacts = 0;

        foreach ($contacts as $contact) {
            $contactValidation = $this->validateSingleContact($contact, $context);
            $validationResults[] = $contactValidation;
            
            if ($contactValidation['is_valid']) {
                $validContacts++;
                $totalScore += $contactValidation['score'];
            }
        }

        if ($validContacts === 0) {
            return ValidationResult::invalid(0, ['No valid contacts found after validation']);
        }

        $averageScore = $totalScore / $validContacts;
        
        // Calculer les scores par règle
        $ruleScores = $this->calculateRuleScores($validationResults, $context);
        
        // Score global pondéré
        $overallScore = $this->calculateOverallScore($averageScore, $ruleScores, count($contacts), $validContacts);

        // Messages de validation
        $messages = $this->generateValidationMessages($validContacts, count($contacts), $averageScore, $ruleScores);

        return ValidationResult::create(
            overallScore: $overallScore,
            ruleScores: $ruleScores,
            validationMessages: $messages
        );
    }

    private function validateSingleContact(ContactData $contact, array $context): array
    {
        $validationResult = [
            'contact' => $contact,
            'is_valid' => false,
            'score' => 0,
            'rules_passed' => [],
            'rules_failed' => [],
            'penalties' => [],
            'bonuses' => []
        ];

        switch ($contact->type) {
            case 'email':
                $validationResult = $this->validateEmail($contact, $context, $validationResult);
                break;
            case 'phone':
                $validationResult = $this->validatePhone($contact, $context, $validationResult);
                break;
            case 'website':
                $validationResult = $this->validateWebsite($contact, $context, $validationResult);
                break;
        }

        // Validation contextuelle générale
        $validationResult = $this->applyContextualValidation($contact, $context, $validationResult);

        // Déterminer si le contact est valide selon les règles
        $minScore = $this->validationRules[$contact->type]['min_score'] ?? 40;
        $validationResult['is_valid'] = $validationResult['score'] >= $minScore;

        return $validationResult;
    }

    private function validateEmail(ContactData $contact, array $context, array $validationResult): array
    {
        $email = $contact->value;
        $score = 50; // Score de base

        // Validation du format
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $validationResult['rules_passed'][] = 'valid_email_format';
            $score += 20;
        } else {
            $validationResult['rules_failed'][] = 'invalid_email_format';
            return array_merge($validationResult, ['score' => 0]);
        }

        // Analyse du domaine
        $domain = substr(strrchr($email, "@"), 1);
        $domainAnalysis = $this->analyzeEmailDomain($domain);
        
        if ($domainAnalysis['is_business']) {
            $validationResult['bonuses'][] = 'business_domain';
            $score += 25;
        }
        
        if ($domainAnalysis['is_free']) {
            $validationResult['penalties'][] = 'free_email_domain';
            $score -= 15;
        }
        
        if ($domainAnalysis['is_suspicious']) {
            $validationResult['penalties'][] = 'suspicious_domain';
            $score -= 30;
        }

        // Correspondance avec le prospect
        $prospectMatch = $this->checkProspectMatch($email, $context);
        if ($prospectMatch['name_match']) {
            $validationResult['bonuses'][] = 'name_match_in_email';
            $score += 30;
        }
        if ($prospectMatch['company_match']) {
            $validationResult['bonuses'][] = 'company_match_in_domain';
            $score += 35;
        }

        // Analyse du contexte de trouvaille
        if (isset($contact->context['in_contact_section'])) {
            $validationResult['bonuses'][] = 'found_in_contact_section';
            $score += 20;
        }

        $validationResult['score'] = min(100, max(0, $score));
        return $validationResult;
    }

    private function validatePhone(ContactData $contact, array $context, array $validationResult): array
    {
        $phone = preg_replace('/[^\d+]/', '', $contact->value);
        $score = 60; // Score de base plus élevé pour les téléphones

        // Validation de la longueur
        $phoneLength = strlen($phone);
        if ($phoneLength >= $this->phoneValidationRules['min_length'] && 
            $phoneLength <= $this->phoneValidationRules['max_length']) {
            $validationResult['rules_passed'][] = 'valid_phone_length';
            $score += 15;
        } else {
            $validationResult['rules_failed'][] = 'invalid_phone_length';
            $score -= 25;
        }

        // Validation du format français
        foreach ($this->phoneValidationRules['french_patterns'] as $pattern) {
            if (preg_match($pattern, $phone)) {
                $validationResult['rules_passed'][] = 'french_phone_format';
                $validationResult['bonuses'][] = 'french_number';
                $score += 25;
                break;
            }
        }

        // Validation internationale si pas français
        if (!in_array('french_phone_format', $validationResult['rules_passed'])) {
            foreach ($this->phoneValidationRules['international_patterns'] as $pattern) {
                if (preg_match($pattern, $phone)) {
                    $validationResult['rules_passed'][] = 'international_phone_format';
                    $score += 15;
                    break;
                }
            }
        }

        $validationResult['score'] = min(100, max(0, $score));
        return $validationResult;
    }

    private function validateWebsite(ContactData $contact, array $context, array $validationResult): array
    {
        $url = $contact->value;
        $score = 40; // Score de base

        // Validation de l'URL
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $validationResult['rules_passed'][] = 'valid_url_format';
            $score += 20;
        } else {
            $validationResult['rules_failed'][] = 'invalid_url_format';
            return array_merge($validationResult, ['score' => 0]);
        }

        // Analyse du domaine
        $parsedUrl = parse_url($url);
        $domain = $parsedUrl['host'] ?? '';
        
        // Vérification du schéma
        if (in_array($parsedUrl['scheme'] ?? '', $this->websiteValidationRules['valid_schemes'])) {
            $validationResult['rules_passed'][] = 'valid_url_scheme';
            $score += 10;
        }

        // Bonus pour plateformes sociales
        foreach ($this->websiteValidationRules['social_platforms'] as $platform) {
            if (stripos($domain, $platform) !== false) {
                $validationResult['bonuses'][] = 'social_media_platform';
                $score += 15;
                break;
            }
        }

        // Correspondance avec l'entreprise
        $companyMatch = $this->checkCompanyMatchInUrl($url, $context);
        if ($companyMatch) {
            $validationResult['bonuses'][] = 'company_website';
            $score += 30;
        }

        $validationResult['score'] = min(100, max(0, $score));
        return $validationResult;
    }

    private function applyContextualValidation(ContactData $contact, array $context, array $validationResult): array
    {
        // Bonus pour source de qualité
        $source = $contact->context['source_url'] ?? '';
        if (stripos($source, 'linkedin.com') !== false) {
            $validationResult['bonuses'][] = 'linkedin_source';
            $validationResult['score'] += 15;
        }

        // Bonus pour score de confiance original
        if ($contact->confidenceLevel === 'high') {
            $validationResult['bonuses'][] = 'high_original_confidence';
            $validationResult['score'] += 10;
        } elseif ($contact->confidenceLevel === 'medium') {
            $validationResult['bonuses'][] = 'medium_original_confidence';
            $validationResult['score'] += 5;
        }

        return $validationResult;
    }

    private function analyzeEmailDomain(string $domain): array
    {
        $analysis = [
            'is_business' => false,
            'is_free' => false,
            'is_suspicious' => false
        ];

        // Vérifier si c'est un domaine gratuit
        if (in_array(strtolower($domain), $this->emailDomainRules['free_domains'])) {
            $analysis['is_free'] = true;
        } else {
            // Si ce n'est pas gratuit, c'est probablement professionnel
            foreach ($this->emailDomainRules['business_indicators'] as $indicator) {
                if (stripos($domain, $indicator) !== false) {
                    $analysis['is_business'] = true;
                    break;
                }
            }
        }

        // Vérifier les patterns suspects
        foreach ($this->emailDomainRules['suspicious_patterns'] as $pattern) {
            if (stripos($domain, $pattern) !== false) {
                $analysis['is_suspicious'] = true;
                break;
            }
        }

        return $analysis;
    }

    private function checkProspectMatch(string $email, array $context): array
    {
        $match = [
            'name_match' => false,
            'company_match' => false
        ];

        $prospectName = strtolower($context['prospect_name'] ?? '');
        $prospectCompany = strtolower($context['prospect_company'] ?? '');
        $emailLower = strtolower($email);
        $domain = substr(strrchr($email, "@"), 1);

        // Vérifier correspondance nom
        if (!empty($prospectName)) {
            $nameWords = explode(' ', $prospectName);
            foreach ($nameWords as $word) {
                if (strlen($word) > 2 && stripos($emailLower, $word) !== false) {
                    $match['name_match'] = true;
                    break;
                }
            }
        }

        // Vérifier correspondance entreprise
        if (!empty($prospectCompany)) {
            $companyWords = explode(' ', $prospectCompany);
            foreach ($companyWords as $word) {
                if (strlen($word) > 3 && stripos($domain, $word) !== false) {
                    $match['company_match'] = true;
                    break;
                }
            }
        }

        return $match;
    }

    private function checkCompanyMatchInUrl(string $url, array $context): bool
    {
        $company = strtolower($context['prospect_company'] ?? '');
        if (empty($company)) {
            return false;
        }

        $domain = parse_url($url, PHP_URL_HOST);
        if (!$domain) {
            return false;
        }

        $companyWords = explode(' ', $company);
        foreach ($companyWords as $word) {
            if (strlen($word) > 3 && stripos($domain, $word) !== false) {
                return true;
            }
        }

        return false;
    }

    private function calculateRuleScores(array $validationResults, array $context): array
    {
        $ruleScores = [];

        // Score de qualité des contacts
        $validContacts = array_filter($validationResults, fn($r) => $r['is_valid']);
        if (!empty($validContacts)) {
            $totalScore = array_sum(array_column($validContacts, 'score'));
            $ruleScores['contact_quality'] = $totalScore / count($validContacts);
        } else {
            $ruleScores['contact_quality'] = 0;
        }

        // Score de diversité des types
        $contactTypes = array_unique(array_column(array_column($validationResults, 'contact'), 'type'));
        $ruleScores['contact_diversity'] = min(100, count($contactTypes) * 30);

        // Score de correspondance avec le prospect
        $matchingContacts = array_filter($validationResults, function($r) {
            return in_array('name_match_in_email', $r['bonuses']) || 
                   in_array('company_match_in_domain', $r['bonuses']) ||
                   in_array('company_website', $r['bonuses']);
        });
        $ruleScores['prospect_relevance'] = min(100, (count($matchingContacts) / count($validationResults)) * 100);

        // Score de fiabilité des sources
        $qualitySources = array_filter($validationResults, function($r) {
            return in_array('linkedin_source', $r['bonuses']) || 
                   in_array('found_in_contact_section', $r['bonuses']);
        });
        $ruleScores['source_reliability'] = min(100, (count($qualitySources) / count($validationResults)) * 100);

        return $ruleScores;
    }

    private function calculateOverallScore(float $averageScore, array $ruleScores, int $totalContacts, int $validContacts): float
    {
        // Pondération des différents facteurs
        $qualityWeight = 0.4;
        $diversityWeight = 0.2;
        $relevanceWeight = 0.25;
        $reliabilityWeight = 0.15;

        $overallScore = 
            ($ruleScores['contact_quality'] * $qualityWeight) +
            ($ruleScores['contact_diversity'] * $diversityWeight) +
            ($ruleScores['prospect_relevance'] * $relevanceWeight) +
            ($ruleScores['source_reliability'] * $reliabilityWeight);

        // Ajustement selon le taux de validation
        $validationRate = $validContacts / $totalContacts;
        if ($validationRate < 0.5) {
            $overallScore *= 0.8; // Pénalité si trop peu de contacts valides
        }

        return min(100.0, max(0.0, $overallScore));
    }

    private function generateValidationMessages(int $validContacts, int $totalContacts, float $averageScore, array $ruleScores): array
    {
        $messages = [];
        
        $messages[] = "Validation rule-based completed: {$validContacts}/{$totalContacts} contacts validated";
        $messages[] = "Average contact quality: " . round($averageScore, 2) . "/100";
        
        if ($ruleScores['prospect_relevance'] > 70) {
            $messages[] = "High prospect relevance detected";
        } elseif ($ruleScores['prospect_relevance'] < 30) {
            $messages[] = "Low prospect relevance - contacts may not match the target";
        }
        
        if ($ruleScores['contact_diversity'] > 60) {
            $messages[] = "Good contact diversity across different types";
        }
        
        if ($ruleScores['source_reliability'] > 70) {
            $messages[] = "High source reliability - contacts from trusted sources";
        }

        return $messages;
    }

    public function getValidationRules(): array
    {
        return $this->validationRules;
    }

    public function isConfigured(): bool
    {
        return true; // Les règles sont intégrées, pas de configuration externe
    }

    public function getServiceInfo(): array
    {
        return [
            'name' => 'Rule-Based Validation Strategy',
            'type' => 'validation_strategy',
            'available' => $this->isConfigured(),
            'description' => 'Stratégie de validation basée sur des règles déterministes sans IA',
            'ai_dependency' => false,
            'cost' => 'Free'
        ];
    }
}