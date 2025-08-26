<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\__Infrastructure__\Services\External\UniversalScraperService;
use ReflectionClass;
use ReflectionMethod;

class EmailExtractionTest extends TestCase
{
    private UniversalScraperService $universalScraperService;
    private ReflectionMethod $isGenericEmailMethod;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->universalScraperService = new UniversalScraperService();
        
        // Utiliser la reflection pour accéder à la méthode privée
        $reflection = new ReflectionClass($this->universalScraperService);
        $this->isGenericEmailMethod = $reflection->getMethod('isGenericEmail');
        $this->isGenericEmailMethod->setAccessible(true);
    }

    /** @test */
    public function it_accepts_legitimate_business_emails()
    {
        $legitimateEmails = [
            'contact@entreprise.com',
            'contact@monrestaurant.fr', 
            'info@maboite.com',
            'jean.martin@entreprise.com',
            'hello@startup.com',
            'support@logiciel.fr',
            'admin@serveur.com',
            'direction@cabinet.fr',
            'accueil@hotel.com',
            'commercial@agence.fr',
            'reservation@restaurant.com'
        ];

        foreach ($legitimateEmails as $email) {
            $isGeneric = $this->isGenericEmailMethod->invoke($this->universalScraperService, $email);
            $this->assertFalse($isGeneric, "L'email '$email' devrait être accepté mais a été rejeté comme générique");
        }
    }

    /** @test */
    public function it_rejects_generic_and_test_emails()
    {
        $genericEmails = [
            'noreply@newsletter.com',
            'no-reply@example.com', 
            'postmaster@domain.com',
            'webmaster@site.fr',
            'admin@test.com',
            'admin@demo.entreprise.com',
            'contact@test.site.com',
            'support@demo.app.fr',
            'hello@localhost',
            'info@example.com'
        ];

        foreach ($genericEmails as $email) {
            $isGeneric = $this->isGenericEmailMethod->invoke($this->universalScraperService, $email);
            $this->assertTrue($isGeneric, "L'email '$email' devrait être rejeté comme générique mais a été accepté");
        }
    }

    /** @test */
    public function it_handles_edge_cases_correctly()
    {
        // Ces emails devraient être acceptés car ils ne correspondent pas aux patterns génériques
        $edgeCases = [
            'admin@entreprise-reelle.com', // admin mais pas sur domaine de test
            'support@ma-boite.fr',        // support mais pas sur domaine de test
            'contact@real-business.org'    // contact mais pas sur domaine de test/demo
        ];

        foreach ($edgeCases as $email) {
            $isGeneric = $this->isGenericEmailMethod->invoke($this->universalScraperService, $email);
            $this->assertFalse($isGeneric, "L'email '$email' devrait être accepté");
        }
    }
}