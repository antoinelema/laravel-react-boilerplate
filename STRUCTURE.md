
# Structure du Projet

## Vue d'ensemble

Ce boilerplate Laravel React suit une architecture hexagonale moderne avec une séparation claire des responsabilités.

## Arborescence

```
├── app/
│   ├── Http/                           # Contrôleurs Laravel de base
│   ├── Providers/                      # Service providers
│   ├── __Application__/                # Couche Application
│   │   ├── Http/
│   │   │   ├── Controllers/           # Contrôleurs métier
│   │   │   ├── Middleware/            # Middlewares personnalisés
│   │   │   └── Request/               # Form requests
│   │   ├── Presenter/                 # Présentateurs de données
│   │   └── Services/                  # Services applicatifs
│   ├── __Domain__/                     # Couche Domaine
│   │   ├── Data/                      # Modèles métier
│   │   │   └── User/                  # Domaine utilisateur
│   │   └── UseCase/                   # Cas d'usage métier
│   │       └── User/                  # Use cases utilisateur
│   └── __Infrastructure__/             # Couche Infrastructure
│       ├── Eloquent/                  # Modèles Eloquent
│       └── Persistence/               # Repositories
├── bootstrap/                          # Fichiers de démarrage Laravel
├── config/                            # Configuration Laravel
├── database/
│   ├── factories/                     # Factory pour les tests
│   ├── migrations/                    # Migrations de base de données
│   └── seeders/                       # Seeders
├── public/                            # Assets publics
├── resources/
│   ├── css/                          # Styles CSS
│   ├── js/
│   │   ├── Pages/                    # Pages React
│   │   ├── components/               # Composants React
│   │   │   ├── form/                 # Formulaires
│   │   │   └── ui/                   # Composants UI (shadcn)
│   │   ├── hooks/                    # Hooks React personnalisés
│   │   └── lib/                      # Utilitaires
│   └── views/                        # Templates Blade
├── routes/                           # Fichiers de routes
├── storage/                          # Stockage Laravel
├── tests/                            # Tests automatisés
│   ├── Feature/                      # Tests d'intégration
│   ├── Unit/                         # Tests unitaires
│   ├── __Application__/              # Tests couche application
│   ├── __Domain__/                   # Tests couche domaine
│   └── __Infrastructure__/           # Tests couche infrastructure
└── vendor/                           # Dépendances Composer
```

## Couches de l'Architecture

### Domain (`app/__Domain__/`)
- **Responsabilité** : Logique métier pure
- **Contenu** : Entités, Value Objects, Use Cases
- **Règles** : Aucune dépendance externe, testable en isolation

### Application (`app/__Application__/`)
- **Responsabilité** : Orchestration des use cases
- **Contenu** : Contrôleurs, Services, Requests
- **Règles** : Interface entre le domaine et l'infrastructure

### Infrastructure (`app/__Infrastructure__/`)
- **Responsabilité** : Implémentations concrètes
- **Contenu** : Eloquent, Repositories, APIs externes
- **Règles** : Détails techniques, frameworks spécifiques

## Frontend (React)

### Pages (`resources/js/Pages/`)
- Pages principales de l'application
- Utilisation d'Inertia.js pour le rendu

### Composants (`resources/js/components/`)
- **`ui/`** : Composants de base (shadcn/ui)
- **`form/`** : Formulaires réutilisables
- **Autres** : Composants métier spécifiques

### Hooks (`resources/js/hooks/`)
- Logique React réutilisable
- Gestion d'état local
- Appels API

## Tests

### Structure des tests
- **`Feature/`** : Tests d'intégration HTTP
- **`Unit/`** : Tests unitaires
- **`__Domain__/`** : Tests du domaine métier
- **`__Application__/`** : Tests des contrôleurs/services
- **`__Infrastructure__/`** : Tests des repositories

Cette structure garantit une séparation claire des responsabilités et une testabilité maximale.
