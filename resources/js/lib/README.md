# Utilitaires frontend : gestion des erreurs API

Ce dossier contient les utilitaires partagés pour le frontend React du boilerplate Laravel React.

## handleApiError

Fonction utilitaire pour centraliser la gestion des erreurs lors des appels API/fetch côté frontend. Elle garantit que l'utilisateur ne verra jamais d'erreur technique brute (SQL, stacktrace, etc.) et qu'un message adapté s'affichera toujours (via toast ou autre).

- **Fichier** : `api.js`
- **Utilisation** :

```js
import { handleApiError } from '@/lib/api';
import { toast } from 'sonner';

fetch('/endpoint', { ... })
  .then(async response => {
    if (!response.ok) {
      try {
        await handleApiError(response, 'Erreur lors de la création');
      } catch (err) {
        toast.error(err.message || 'Erreur lors de la création');
        throw err;
      }
    }
    return response.json();
  })
  .then(() => {
    toast.success('Succès !');
  });
```

- **Comportement** :
  - Si le backend retourne une erreur 500 ou une réponse non-JSON, un message générique est affiché.
  - Si le backend fournit un message, il est affiché à l'utilisateur.
  - Ce pattern est utilisé dans tous les formulaires critiques (profil, etc.).

Voir aussi le README pour la philosophie générale.
