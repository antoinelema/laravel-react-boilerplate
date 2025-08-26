<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\__Infrastructure__\Services\Validation\RuleBasedValidationStrategy;
use App\__Domain__\Data\Enrichment\ContactData;

class RuleBasedValidationStrategyTest extends TestCase
{
    private RuleBasedValidationStrategy $strategy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->strategy = new RuleBasedValidationStrategy();
    }

    public function testValidateEmailWithBusinessDomain(): void
    {
        $contacts = [
            ContactData::email(
                email: 'john.doe@company.com',
                validationScore: 70.0,
                confidenceLevel: 'medium',
                context: [
                    'in_contact_section' => true
                ]
            )
        ];

        $context = [
            'prospect_name' => 'John Doe',
            'prospect_company' => 'Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        $this->assertTrue($result->isValid);
        $this->assertGreaterThan(60, $result->overallScore);
        $this->assertArrayHasKey('contact_quality', $result->ruleScores);
        $this->assertArrayHasKey('prospect_relevance', $result->ruleScores);
    }

    public function testValidateEmailWithFreeEmailDomain(): void
    {
        $contacts = [
            ContactData::email(
                email: 'user@gmail.com',
                validationScore: 60.0,
                confidenceLevel: 'medium'
            )
        ];

        $context = [
            'prospect_name' => 'Test User',
            'prospect_company' => 'Test Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        // Devrait encore être valide mais avec un score plus bas à cause du domaine gratuit
        $this->assertTrue($result->isValid);
        $this->assertLessThan(80, $result->overallScore);
    }

    public function testValidatePhoneWithFrenchFormat(): void
    {
        $contacts = [
            ContactData::phone(
                phone: '+33123456789',
                validationScore: 80.0,
                confidenceLevel: 'high'
            )
        ];

        $context = [
            'prospect_name' => 'Jean Dupont',
            'prospect_company' => 'Entreprise FR'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        $this->assertTrue($result->isValid);
        $this->assertGreaterThan(70, $result->overallScore);
        $this->assertArrayHasKey('contact_quality', $result->ruleScores);
    }

    public function testValidateWebsiteWithValidUrl(): void
    {
        $contacts = [
            ContactData::website(
                website: 'https://company.com',
                validationScore: 70.0,
                confidenceLevel: 'medium'
            )
        ];

        $context = [
            'prospect_name' => 'John Smith',
            'prospect_company' => 'Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        $this->assertTrue($result->isValid);
        $this->assertGreaterThan(60, $result->overallScore);
    }

    public function testValidateMultipleContactTypes(): void
    {
        $contacts = [
            ContactData::email('test@company.com', 85.0, 'high'),
            ContactData::phone('+33123456789', 80.0, 'high'),
            ContactData::website('https://company.com', 75.0, 'medium')
        ];

        $context = [
            'prospect_name' => 'Test User',
            'prospect_company' => 'Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        $this->assertTrue($result->isValid);
        $this->assertGreaterThan(80, $result->overallScore);
        $this->assertGreaterThan(50, $result->ruleScores['contact_diversity']); // Bonus pour diversité
    }

    public function testValidateContactsWithProspectMatch(): void
    {
        // Email qui correspond au nom et à l'entreprise du prospect
        $contacts = [
            ContactData::email(
                email: 'john.doe@testcompany.com',
                validationScore: 70.0,
                confidenceLevel: 'medium'
            )
        ];

        $context = [
            'prospect_name' => 'John Doe',
            'prospect_company' => 'Test Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        $this->assertTrue($result->isValid);
        $this->assertGreaterThan(80, $result->overallScore);
        $this->assertGreaterThan(50, $result->ruleScores['prospect_relevance']); // Bonus pour correspondance
    }

    public function testValidateContactsWithLinkedInSource(): void
    {
        $contacts = [
            ContactData::email(
                email: 'professional@company.com',
                validationScore: 75.0,
                confidenceLevel: 'high',
                context: [
                    'source_url' => 'https://linkedin.com/in/professional'
                ]
            )
        ];

        $context = [
            'prospect_name' => 'Professional User',
            'prospect_company' => 'Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        $this->assertTrue($result->isValid);
        $this->assertGreaterThan(75, $result->overallScore);
        $this->assertGreaterThan(50, $result->ruleScores['source_reliability']); // Bonus pour LinkedIn
    }

    public function testValidateInvalidEmailFormat(): void
    {
        $contacts = [
            ContactData::email(
                email: 'invalid-email', // Format invalide
                validationScore: 50.0,
                confidenceLevel: 'low'
            )
        ];

        $context = [
            'prospect_name' => 'Test User',
            'prospect_company' => 'Test Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        $this->assertFalse($result->isValid);
        $this->assertLessThan(40, $result->overallScore);
    }

    public function testValidateSuspiciousEmail(): void
    {
        $contacts = [
            ContactData::email(
                email: 'noreply@company.com',
                validationScore: 60.0,
                confidenceLevel: 'medium'
            )
        ];

        $context = [
            'prospect_name' => 'Test User',
            'prospect_company' => 'Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        // Devrait être pénalisé pour email suspect
        $this->assertLessThan(70, $result->overallScore);
    }

    public function testValidateEmptyContactList(): void
    {
        $result = $this->strategy->validateContacts([], []);

        $this->assertFalse($result->isValid);
        $this->assertEquals(0, $result->overallScore);
        $this->assertContains('No contacts to validate', $result->validationMessages);
    }

    public function testValidateContactsWithLowScores(): void
    {
        $contacts = [
            ContactData::email('low@score.com', 30.0, 'low'),
            ContactData::phone('123', 25.0, 'low')
        ];

        $context = [
            'prospect_name' => 'Test User',
            'prospect_company' => 'Test Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        $this->assertFalse($result->isValid);
        $this->assertLessThan(60, $result->overallScore);
        $this->assertContains('No valid contacts found', $result->validationMessages);
    }

    public function testGetValidationRules(): void
    {
        $rules = $this->strategy->getValidationRules();

        $this->assertArrayHasKey('email', $rules);
        $this->assertArrayHasKey('phone', $rules);
        $this->assertArrayHasKey('website', $rules);

        $this->assertArrayHasKey('min_score', $rules['email']);
        $this->assertArrayHasKey('max_score', $rules['email']);
        $this->assertArrayHasKey('required_format', $rules['email']);
    }

    public function testIsConfigured(): void
    {
        $this->assertTrue($this->strategy->isConfigured());
    }

    public function testGetServiceInfo(): void
    {
        $info = $this->strategy->getServiceInfo();

        $this->assertArrayHasKey('name', $info);
        $this->assertArrayHasKey('type', $info);
        $this->assertArrayHasKey('ai_dependency', $info);
        $this->assertFalse($info['ai_dependency']); // Pas de dépendance IA
        $this->assertTrue($info['available']);
    }

    public function testValidationMessagesGeneration(): void
    {
        $contacts = [
            ContactData::email('high@score.com', 85.0, 'high'),
            ContactData::email('medium@score.com', 65.0, 'medium'),
            ContactData::email('low@score.com', 30.0, 'low')
        ];

        $context = [
            'prospect_name' => 'Test User',
            'prospect_company' => 'Test Company'
        ];

        $result = $this->strategy->validateContacts($contacts, $context);

        $this->assertNotEmpty($result->validationMessages);
        $this->assertTrue(str_contains($result->validationMessages[0], 'Validation rule-based completed'));
        $this->assertTrue(str_contains($result->validationMessages[1], 'Average contact quality'));
    }
}