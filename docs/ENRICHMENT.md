# Système d'enrichissement des prospects

Ce document décrit le fonctionnement du système d'enrichissement web des prospects dans Prospecto.

## Vue d'ensemble

Le système d'enrichissement permet d'enrichir automatiquement les données de prospects en récupérant des informations de contact (emails, téléphones, sites web, réseaux sociaux) depuis diverses sources web.

## Fonctionnalités principales

### 1. Enrichissement intelligent avec filtrage
- **Exclusion automatique** des prospects récemment enrichis (< 30 jours par défaut)
- **Exclusion automatique** des prospects ayant déjà email ET téléphone
- **Système de scoring** de complétude des données (0-100%)
- **Prioritisation** automatique (high/medium/low)

### 2. Sources d'enrichissement
- **DuckDuckGo Search** : Recherche générale
- **Google Search avec Selenium** : Recherche approfondie
- **Universal Scraper** : Extraction générique de données

### 3. Interfaces d'utilisation
- **Interface web** : Boutons d'enrichissement individuels et par lot
- **API REST** : Endpoints pour intégrations
- **CLI Artisan** : Commande pour enrichissement automatisé/programmé
- **Hooks React** : Composants réutilisables pour le frontend

## Configuration

### Variables d'environnement

```bash
# Activation/désactivation générale
ENRICHMENT_ENABLED=true

# Paramètres de fréquence et limites
ENRICHMENT_DEFAULT_REFRESH_DAYS=30
ENRICHMENT_MAX_ATTEMPTS=3
ENRICHMENT_RATE_LIMIT_DELAY=2
ENRICHMENT_MAX_CONTACTS_PER_PROSPECT=10

# Seuils et scoring
ENRICHMENT_COMPLETENESS_THRESHOLD=80
ENRICHMENT_AUTO_ENABLED_DEFAULT=true

# Services individuels
ENRICHMENT_SERVICE_DUCKDUCKGO=true
ENRICHMENT_SERVICE_GOOGLE=true  
ENRICHMENT_SERVICE_UNIVERSAL=true

# Configuration Selenium (optionnel)
SELENIUM_HUB_URL=http://selenium:4444/wd/hub
SELENIUM_BROWSER=chrome
SELENIUM_TIMEOUT=30
```

### Configuration Docker pour Selenium (optionnel)

Ajouter au `docker-compose.yml` pour Google Search avec Selenium :

```yaml
services:
  selenium:
    image: selenium/standalone-chrome:latest
    ports:
      - "4444:4444"
      - "7900:7900"  # VNC pour debugging
    environment:
      - SE_VNC_NO_PASSWORD=1
    volumes:
      - /dev/shm:/dev/shm
```

## Utilisation

### 1. Interface web

#### Enrichissement individuel
```jsx
import EnrichmentButton from '@/components/EnrichmentButton'

<EnrichmentButton 
    prospect={prospect}
    onEnrichmentComplete={(prospect, result) => {
        // Traitement du résultat
    }}
/>
```

#### Enrichissement par lot
```jsx
import { BulkEnrichmentButton } from '@/components/EnrichmentButton'

<BulkEnrichmentButton 
    selectedProspects={selectedProspects}
    onBulkEnrichmentComplete={(prospects, results) => {
        // Traitement des résultats
    }}
/>
```

### 2. API REST

#### Vérifier l'éligibilité
```http
GET /api/v1/prospects/{id}/enrichment-eligibility
```

#### Enrichir un prospect
```http
POST /api/v1/prospects/{id}/enrich
Content-Type: application/json

{
    "force": false,
    "max_contacts": 10,
    "custom_urls": []
}
```

#### Enrichissement par lot
```http
POST /api/v1/prospects/bulk-enrich
Content-Type: application/json

{
    "prospect_ids": [1, 2, 3],
    "max_processing": 10
}
```

### 3. Commande CLI

#### Enrichissement automatique
```bash
# Mode simulation (dry-run)
./vendor/bin/sail artisan prospects:auto-enrich --dry-run

# Enrichir jusqu'à 10 prospects
./vendor/bin/sail artisan prospects:auto-enrich --limit=10

# Enrichir pour un utilisateur spécifique
./vendor/bin/sail artisan prospects:auto-enrich --user-id=123

# Mode agressif (refresh après 7 jours)
./vendor/bin/sail artisan prospects:auto-enrich --force-refresh-days=7 --delay=1
```

#### Programmation cron
```bash
# Tous les jours à 2h du matin, max 20 prospects
0 2 * * * php artisan prospects:auto-enrich --limit=20

# Toutes les heures en journée (mode conservateur)
0 9-18 * * * php artisan prospects:auto-enrich --limit=5 --delay=3
```

## Architecture

### Base de données

#### Table `prospects` (colonnes ajoutées)
- `last_enrichment_at` : Timestamp du dernier enrichissement
- `enrichment_attempts` : Nombre de tentatives d'enrichissement
- `enrichment_status` : Statut actuel ('pending', 'completed', 'failed')
- `enrichment_score` : Score de succès du dernier enrichissement
- `auto_enrich_enabled` : Auto-enrichissement activé/désactivé
- `enrichment_blacklisted_at` : Date de désactivation d'enrichissement
- `enrichment_data` : Métadonnées JSON de l'enrichissement
- `data_completeness_score` : Score de complétude (0-100)

#### Table `prospect_enrichment_history`
Audit trail complet de tous les enrichissements avec :
- Détails d'exécution (service utilisé, durée, etc.)
- Résultats obtenus (contacts trouvés)
- Erreurs et raisons d'échec
- Déclencheur (manuel, auto, API, etc.)

### Services principaux

#### `EnrichmentEligibilityService`
- Calcul du score de complétude des données
- Détermination de l'éligibilité avec raisons détaillées
- Système de prioritisation (high/medium/low)
- Filtrage intelligent des prospects

#### `ProspectEnrichmentService` (étendu)
- Intégration des checks d'éligibilité
- Gestion des modes force et retry
- Enrichissement par lot avec gestion d'erreurs
- Historique complet et traçabilité

### Frontend

#### Hooks React
- `useEnrichmentEligibility()` : Gestion de l'éligibilité
- `useProspectEnrichment()` : Enrichissement avec état
- `useEnrichmentStats()` : Statistiques globales

#### Composants
- `EnrichmentButton` : Bouton intelligent avec états
- `BulkEnrichmentButton` : Enrichissement par lot
- `EnrichmentStatusBadge` : Badge de statut d'enrichissement

## Monitoring et statistiques

### Endpoints de statistiques
```http
GET /api/v1/prospects/enrichment-stats
```

Retourne :
- Nombre total de prospects
- Prospects éligibles pour enrichissement
- Taux de complétude moyen
- Prospects enrichis récemment
- Prospects jamais enrichis
- Prospects en blacklist

### Logs et debugging
- Tous les enrichissements sont loggés avec détails
- Mode dry-run pour tester sans exécuter
- Barres de progression en CLI avec messages détaillés

## Bonnes pratiques

### Performance
- Utilisez le délai (`--delay`) pour respecter les limites des services
- Limitez le nombre de prospects traités simultanément
- Programmez les enrichissements durant les heures creuses

### Éthique et légalité
- Respectez les robots.txt des sites web
- Implémentez des délais raisonnables entre requêtes
- Ne stockez que les données nécessaires
- Respectez le RGPD pour les données personnelles

### Maintenance
- Surveillez les statistiques d'échec
- Ajustez les seuils selon vos besoins
- Nettoyez périodiquement l'historique ancien
- Monitez les performances des services externes

## Dépannage

### Problèmes courants

1. **Selenium ne démarre pas**
   - Vérifiez que le service Docker est lancé
   - Contrôlez les ports (4444 et 7900)

2. **Aucun contact trouvé**
   - Vérifiez la configuration des services
   - Activez le mode debug pour voir les requêtes
   - Testez manuellement les URLs prospects

3. **Enrichissement lent**
   - Ajustez le délai entre requêtes
   - Désactivez les services lents
   - Utilisez le mode par lot pour optimiser

4. **Prospects pas éligibles**
   - Vérifiez les seuils de complétude
   - Contrôlez les dates de dernier enrichissement
   - Utilisez le mode `--force` si nécessaire

### Mode debug
```bash
# Logs détaillés en CLI
./vendor/bin/sail artisan prospects:auto-enrich --dry-run -vvv

# Logs Laravel
tail -f storage/logs/laravel.log
```