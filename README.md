# Laravel React Boilerplate

## Description

Un boilerplate moderne Laravel + React avec architecture hexagonale pour démarrer rapidement vos projets web. Ce template inclut une gestion d'utilisateurs complète avec authentification Google, une interface React moderne et une architecture scalable.

## Stack Technique

- **Backend** : Laravel 11 (PHP 8.2+)
- **Frontend** : React 18 + Inertia.js
- **Base de données** : MySQL/SQLite
- **UI** : Tailwind CSS + shadcn/ui
- **Tests** : PHPUnit + React Testing
- **Outils** : Vite, TypeScript

## Fonctionnalités

✅ **Authentification complète**
- Login/Register classique
- Authentification Google (Socialite)
- Gestion de session sécurisée
- Protection des routes

✅ **Architecture hexagonale**
- Séparation Domain/Application/Infrastructure
- Use Cases métier
- Repository pattern
- Testabilité maximale

✅ **Interface moderne**
- Composants React réutilisables
- Formulaires typés (zod + react-hook-form)
- Interface responsive
- Dark mode ready

✅ **Développement**
- Docker Compose inclus
- Hot reload (Vite)
- Linting & formatting
- Tests automatisés

## Installation

### Prérequis
- PHP 8.2+
- Composer
- Node.js 18+
- Docker (optionnel)

### Setup

1. **Cloner le projet**
```bash
git clone <votre-repo>
cd laravel-react-boilerplate
```

2. **Installer les dépendances**
```bash
composer install
npm install
```

3. **Configuration**
```bash
cp .env.example .env
php artisan key:generate
```

4. **Base de données**
```bash
php artisan migrate
```

5. **Démarrer le développement**
```bash
# Avec Docker
docker-compose up -d

# Ou manuellement
php artisan serve
npm run dev
```

## Structure du Projet

```
app/
├── __Domain__/           # Logique métier pure
│   ├── Data/            # Modèles métier
│   └── UseCase/         # Cas d'usage
├── __Application__/      # Couche application
│   ├── Http/           # Contrôleurs & Requests
│   └── Services/       # Services applicatifs
└── __Infrastructure__/   # Implémentations concrètes
    ├── Eloquent/       # Modèles Eloquent
    └── Persistence/    # Repositories

resources/js/
├── Pages/              # Pages React
├── components/         # Composants réutilisables
├── hooks/             # Hooks personnalisés
└── lib/               # Utilitaires
```

## Architecture

Ce boilerplate suit une **architecture hexagonale** (clean architecture) qui sépare :

- **Domain** : Logique métier pure, indépendante du framework
- **Application** : Orchestration des use cases, contrôleurs
- **Infrastructure** : Implémentations concrètes (BDD, API, etc.)

Cette approche garantit :
- 🧪 **Testabilité** maximale
- 🔧 **Maintenabilité** à long terme
- 🔄 **Flexibilité** pour changer d'implémentation
- 📦 **Séparation** claire des responsabilités

## Commandes Utiles

```bash
# Tests
composer test
npm test

# Linting
composer lint
npm run lint

# Build production
npm run build

# Analyse statique
composer analyse
```

## Configuration Google OAuth

1. Créer un projet sur [Google Cloud Console](https://console.cloud.google.com)
2. Activer Google+ API
3. Créer des identifiants OAuth 2.0
4. Ajouter dans `.env` :
```env
GOOGLE_CLIENT_ID=votre_client_id
GOOGLE_CLIENT_SECRET=votre_client_secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

## Licence

MIT License - voir le fichier [LICENSE](LICENSE)

