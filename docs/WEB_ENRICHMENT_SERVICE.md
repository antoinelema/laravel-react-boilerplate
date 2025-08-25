# Service d'Enrichissement Web - Documentation Technique

## 📋 Vue d'ensemble

Le Service d'Enrichissement Web est un système multi-sources conçu pour enrichir les prospects avec des informations de contact (emails, téléphones, sites web, réseaux sociaux) via des techniques de web scraping et de recherche **sans utiliser d'IA**.

## 🏗️ Architecture

### Services principaux

1. **WebEnrichmentService** - Service orchestrateur principal
2. **DuckDuckGoService** - Recherche gratuite via DuckDuckGo HTML API
3. **GoogleSearchService** - Recherche avancée avec Selenium WebDriver
4. **UniversalScraperService** - Scraping direct de pages web
5. **RuleBasedValidationStrategy** - Validation par règles déterministes

### Modèles de données

- **WebScrapingResult** - Résultat d'une session d'enrichissement
- **ContactData** - Données de contact extraites
- **ValidationResult** - Résultat de validation avec scores

## 🚀 Installation et Configuration

### 1. Packages PHP requis

```bash
composer require symfony/dom-crawler symfony/panther facebook/webdriver
```

### 2. Configuration dans `config/services.php`

```php
// Configuration générale
'web_enrichment' => [
    'enable_duckduckgo' => env('WEB_ENRICHMENT_ENABLE_DUCKDUCKGO', true),
    'enable_google_search' => env('WEB_ENRICHMENT_ENABLE_GOOGLE_SEARCH', false),
    'enable_universal_scraper' => env('WEB_ENRICHMENT_ENABLE_UNIVERSAL_SCRAPER', true),
    'timeout' => env('WEB_ENRICHMENT_TIMEOUT', 30),
],

// DuckDuckGo (gratuit, pas de clé API)
'duckduckgo' => [
    'base_url' => env('DUCKDUCKGO_BASE_URL', 'https://html.duckduckgo.com'),
    'timeout' => env('DUCKDUCKGO_TIMEOUT', 30),
],

// Google Search avec Selenium (optionnel)
'google_search' => [
    'selenium_host' => env('GOOGLE_SEARCH_SELENIUM_HOST', 'http://localhost:4444'),
    'timeout' => env('GOOGLE_SEARCH_TIMEOUT', 30),
],

// Universal Scraper
'universal_scraper' => [
    'timeout' => env('UNIVERSAL_SCRAPER_TIMEOUT', 30),
    'user_agent' => env('UNIVERSAL_SCRAPER_USER_AGENT', 'Mozilla/5.0 (compatible; Prospecto/1.0)'),
],
```

### 3. Variables d'environnement (.env)

```bash
# Enrichissement web - services activés
WEB_ENRICHMENT_ENABLE_DUCKDUCKGO=true
WEB_ENRICHMENT_ENABLE_GOOGLE_SEARCH=false  # Nécessite Selenium
WEB_ENRICHMENT_ENABLE_UNIVERSAL_SCRAPER=true
WEB_ENRICHMENT_TIMEOUT=30

# Configuration DuckDuckGo
DUCKDUCKGO_TIMEOUT=30

# Configuration Google Search (si activé)
GOOGLE_SEARCH_SELENIUM_HOST=http://localhost:4444
GOOGLE_SEARCH_TIMEOUT=30

# Configuration Universal Scraper
UNIVERSAL_SCRAPER_TIMEOUT=30
```

## 📖 Utilisation

### Usage basique via ProspectEnrichmentService

```php
use App\__Infrastructure__\Services\ProspectEnrichment\ProspectEnrichmentService;

// Injection de dépendance
$enrichmentService = app(ProspectEnrichmentService::class);

// Enrichir les contacts web d'un prospect
$prospect = /* Votre ProspectModel */;
$enrichedContacts = $enrichmentService->enrichProspectWebContacts($prospect);

// Résultat organisé par type
/*
[
    'emails' => [
        ['value' => 'contact@company.com', 'confidence' => 'high', 'score' => 85.0, ...]
    ],
    'phones' => [
        ['value' => '+33123456789', 'confidence' => 'medium', 'score' => 75.0, ...]
    ],
    'websites' => [
        ['value' => 'https://company.com', 'confidence' => 'medium', 'score' => 70.0, ...]
    ],
    'social_media' => [
        ['value' => 'https://linkedin.com/company/...', 'platform' => 'linkedin', ...]
    ]
]
*/
```

### Usage direct du WebEnrichmentService

```php
use App\__Infrastructure__\Services\WebEnrichmentService;

$webService = app(WebEnrichmentService::class);

$result = $webService->enrichProspectContacts(
    'John Doe',
    'Tech Company',
    [
        'max_contacts' => 10,
        'urls_to_scrape' => ['https://techcompany.com/contact'],
        'company_website' => 'https://techcompany.com'
    ]
);

if ($result->success && $result->hasValidContacts()) {
    foreach ($result->contacts as $contact) {
        echo "{$contact->type}: {$contact->value} (Score: {$contact->validationScore})\n";
    }
}
```

## 🔍 Fonctionnalités détaillées

### 1. DuckDuckGoService

**Avantages :**
- ✅ Gratuit, pas de clé API
- ✅ Pas de limite de taux stricte
- ✅ Disponible immédiatement

**Technique :**
- Utilise l'API HTML de DuckDuckGo
- Requêtes optimisées avec opérateurs de recherche
- Extraction via Symfony DomCrawler
- User-Agent rotation pour éviter la détection

**Exemple de requête générée :**
```
"John Doe" "Tech Company" (email OR contact OR "adresse email")
```

### 2. GoogleSearchService

**Avantages :**
- ✅ Résultats de meilleure qualité
- ✅ Opérateurs de recherche avancés
- ✅ Support des sites spécifiques (LinkedIn, etc.)

**Prérequis :**
- Serveur Selenium en fonctionnement
- Configuration `GOOGLE_SEARCH_SELENIUM_HOST`

**Installation Selenium (Docker) :**
```bash
docker run -d -p 4444:4444 -p 7900:7900 --shm-size=2g selenium/standalone-chrome:latest
```

**Opérateurs utilisés :**
```
"John Doe" "Tech Company" email OR contact
site:linkedin.com "John Doe" "Tech Company" email
site:company.com "John Doe" email OR contact
"John Doe" AND "Tech Company" AND (email OR contact OR "nous joindre")
```

### 3. UniversalScraperService

**Capacités :**
- Extraction d'emails, téléphones, sites web, réseaux sociaux
- Analyse contextuelle (sections contact, équipe, etc.)
- Support de formats multiples de téléphones français
- Détection automatique de plateformes sociales

**Types de données extraites :**
- **Emails :** Validation format + domaine professionnel vs gratuit
- **Téléphones :** Formats français (+33, 0x) et internationaux
- **Sites web :** Distinction entreprise vs réseaux sociaux
- **Réseaux sociaux :** LinkedIn, Twitter, Facebook avec métadonnées

### 4. RuleBasedValidationStrategy (Sans IA)

**Critères de validation :**

#### Pour les emails :
- ✅ Format valide (filter_var)
- ✅ Domaine professionnel (+25 points) vs gratuit (-15 points)
- ✅ Correspondance nom prospect (+30 points)
- ✅ Correspondance entreprise dans domaine (+35 points)
- ✅ Trouvé dans section contact (+20 points)
- ❌ Emails suspects : noreply, admin, test, etc. (-30 points)

#### Pour les téléphones :
- ✅ Format français (+25 points)
- ✅ Longueur valide (10-15 chiffres)
- ✅ Format international (+15 points)

#### Pour les sites web :
- ✅ URL valide (filter_var)
- ✅ HTTPS (+10 points)
- ✅ Plateforme sociale reconnue (+15 points)
- ✅ Site de l'entreprise (+30 points)

#### Bonus contextuels :
- ✅ Source LinkedIn (+15 points)
- ✅ Diversité des types de contacts
- ✅ Fiabilité de la source

## 📊 Système de scoring

### Scores de validation
- **0-39** : Invalide (rejeté)
- **40-59** : Faible confiance
- **60-79** : Confiance moyenne
- **80-100** : Haute confiance

### Score global (ValidationResult)
```php
$overallScore = 
    ($contactQuality * 0.4) +
    ($contactDiversity * 0.2) + 
    ($prospectRelevance * 0.25) +
    ($sourceReliability * 0.15);
```

## 🧪 Tests

### Lancer les tests

```bash
# Tests unitaires
./vendor/bin/sail test tests/Unit/Services/WebEnrichmentServiceTest.php
./vendor/bin/sail test tests/Unit/Services/RuleBasedValidationStrategyTest.php

# Tests d'intégration
./vendor/bin/sail test tests/Feature/ProspectEnrichmentWebTest.php

# Tous les tests d'enrichissement
./vendor/bin/sail test --filter=Enrichment
```

### Tester les services en production

```php
$enrichmentService = app(ProspectEnrichmentService::class);
$testResults = $enrichmentService->testWebEnrichmentServices();

foreach ($testResults as $service => $result) {
    echo "{$service}: {$result['status']}\n";
}
```

## 🔧 Maintenance et monitoring

### Logs à surveiller

```bash
# Logs d'enrichissement
tail -f storage/logs/laravel.log | grep "web enrichment"

# Erreurs de services
tail -f storage/logs/laravel.log | grep "Error enriching\|search failed"
```

### Métriques importantes

```php
// Obtenir les infos sur les services
$webService = app(WebEnrichmentService::class);
$serviceInfo = $webService->getAvailableServices();

foreach ($serviceInfo as $name => $info) {
    echo "{$name}: " . ($info['configured'] ? 'OK' : 'NON CONFIGURÉ') . "\n";
}
```

## ⚠️ Limitations et considérations

### Limites techniques
- **Rate limiting** : Délais respectueux entre requêtes (1-4 secondes)
- **Timeout** : 30 secondes par service par défaut
- **Dépendances** : Selenium optionnel pour Google Search
- **Robots.txt** : Respecté par les scrapers

### Limites légales
- **RGPD** : Les données extraites doivent respecter la réglementation
- **Terms of Service** : Vérifier les CGU des sites scrapés
- **Usage équitable** : Pas d'usage abusif des services gratuits

### Bonnes pratiques
- ✅ Activer uniquement les services nécessaires
- ✅ Configurer des timeouts appropriés
- ✅ Monitorer les logs d'erreurs
- ✅ Respecter les limites de taux
- ✅ Valider les résultats avant utilisation

## 🔄 Évolutions futures

### Améliorations possibles
1. **Cache intelligent** : Éviter les recherches redondantes
2. **Nouveaux services** : Bing, Yandex, moteurs spécialisés
3. **Enrichissement asynchrone** : Jobs en arrière-plan
4. **ML sans IA externe** : Modèles locaux pour améliorer la validation
5. **API REST** : Exposition via endpoints dédiés

### Extensibilité
Le système est conçu pour être facilement extensible :

```php
// Ajouter un nouveau service de recherche
class NewSearchService implements SearchServiceInterface 
{
    public function searchProspectContacts(string $name, string $company, array $options): WebScrapingResult
    {
        // Implémentation
    }
}

// L'ajouter au WebEnrichmentService
$webEnrichmentService->addSearchService('new_service', $newSearchService);
```

## 📞 Support et dépannage

### Problèmes courants

**1. Aucun contact trouvé**
- Vérifier que les services sont activés dans la config
- Tester avec des prospects connus (ex: grandes entreprises)
- Vérifier les logs pour des erreurs de timeout

**2. Google Search ne fonctionne pas**
- S'assurer que Selenium est démarré : `curl http://localhost:4444/status`
- Vérifier `GOOGLE_SEARCH_SELENIUM_HOST` dans .env
- Désactiver temporairement avec `WEB_ENRICHMENT_ENABLE_GOOGLE_SEARCH=false`

**3. Scores de validation trop bas**
- Ajuster les seuils dans `RuleBasedValidationStrategy`
- Vérifier la correspondance nom/entreprise
- Analyser les `validationDetails` des contacts

**4. Timeouts fréquents**
- Augmenter `WEB_ENRICHMENT_TIMEOUT`
- Vérifier la connectivité réseau
- Réduire le nombre de services simultanés

### Debug mode

```php
// Activer les logs détaillés
use Illuminate\Support\Facades\Log;

Log::info('Debug prospect enrichment', [
    'prospect_name' => $prospectName,
    'prospect_company' => $prospectCompany,
    'services_available' => $webService->getAvailableServices()
]);
```

---

**Créé le :** $(date +"%Y-%m-%d")
**Version :** 1.0.0
**Dernière mise à jour :** $(date +"%Y-%m-%d %H:%M")