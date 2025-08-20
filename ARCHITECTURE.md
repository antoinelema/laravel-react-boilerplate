# Architecture

## Vue d'ensemble

L'application My Garage adopte une architecture hexagonale (aussi appelée Clean Architecture) pour garantir la séparation des responsabilités, la testabilité et la maintenabilité du code. Cette approche permet d'isoler la logique métier du framework, de la base de données et de l'interface utilisateur.

## Découpage principal

- **Domaine (`app/__Domain__`)** :
  - Contient les modèles métier purs (ex : User), les entités, les value objects et les interfaces des repositories.
  - Aucun accès à Laravel, Eloquent ou à la base de données.
  - Exemples : `User`, `UserRepositoryInterface`.

- **Application (`app/__Application__`)** :
  - Contient les cas d'usage (use cases), les services d'application, les contrôleurs HTTP, les requêtes de validation, etc.
  - Orchestration de la logique métier, sans dépendre d'implémentations concrètes.
  - Exemples : `UserService`, `UserController`, `UpdateUserRequest`.

- **Infrastructure (`app/__Infrastructure__`)** :
  - Contient les implémentations concrètes des interfaces du domaine (repositories Eloquent, services externes, etc.).
  - Dépend de Laravel/Eloquent, gère la persistance et l'intégration technique.
  - Exemples : `UserEloquent`, `UserRepository`.

- **Interface Utilisateur (`resources/js`)** :
  - Frontend React (avec Inertia.js), pages, composants, hooks, formulaires.
  - Validation des formulaires avec zod/react-hook-form.

## Flux de dépendances

- Le **Domaine** ne dépend de rien.
- L'**Application** dépend du Domaine.
- L'**Infrastructure** dépend du Domaine et de l'Application.
- L'**Interface Utilisateur** (React) communique avec l'Application via des routes API Laravel.

## Exemple de flux (mise à jour d'un profil utilisateur)

1. Le front React envoie une requête PUT `/profile` avec les données du formulaire.
2. Le contrôleur (`AuthController`) valide la requête et appelle le cas d'usage approprié.
3. Le cas d'usage manipule les entités du domaine et utilise un repository (interface).
4. L'implémentation concrète du repository (Eloquent) effectue la persistance.
5. Le contrôleur retourne une réponse JSON au front.

## Avantages de cette architecture

- **Testabilité** : la logique métier peut être testée indépendamment de Laravel ou de la base de données.
- **Évolutivité** : possibilité de changer de framework, de base de données ou d'interface sans toucher au cœur métier.
- **Lisibilité** : séparation claire des responsabilités et des couches.

## Contraintes et choix spécifiques

- **UserEloquent** remplace le modèle User Laravel natif pour mieux coller au domaine.
- **Validation** : double validation (front avec zod, back avec Laravel).
- **Authentification** : middleware Laravel, login classique et Google.
- **Tests** : tests unitaires sur le domaine, tests d'intégration sur les cas d'usage et les routes.

## Structure des dossiers

```
app/
  __Domain__/
    User.php, ...
    UseCase/
    Data/
  __Application__/
    Http/
    Services/
  __Infrastructure__/
    Eloquent/
    Persistence/
resources/js/
  Pages/
  components/
  ...
routes/web.php
```

---

Pour toute question sur l'architecture, voir le README ou contacter l'auteur.
