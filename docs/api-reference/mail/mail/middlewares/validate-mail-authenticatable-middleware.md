# ValidateMailAuthenticatableMiddleware - Référence Technique

## Description

Middleware d'authentification Laravel qui valide le champ `model_type` dans les requêtes entrantes et garantit que le modèle spécifié existe et implémente l'interface `MailAuthenticatable`.

## Hiérarchie / Implémentations

```
Classe finale (final)
├── Implémente : Aucune interface explicite
├── Utilise : Closure, Request, Response
└── Dépend de : AuthenticationKitConfigInterface, MailAuthenticationInterface
```

## Rôle principal

Ce middleware agit comme un **gardien** pour toutes les routes d'authentification. Il :
1. Vérifie la présence du paramètre `model_type`
2. Valide l'existence de la classe
3. Confirme l'implémentation de `MailAuthenticatable`
4. Lie le service d'authentification correspondant dans le conteneur
5. Injecte automatiquement les cookies d'authentification si configuré

## Prérequis

```bash
# Aucune installation spécifique requise
# Le middleware est automatiquement enregistré par le MailServiceProvider
```

## API / Méthodes publiques

### `handle(Request $request, Closure $next): Response`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `Illuminate\Http\Request` | La requête HTTP entrante |
| `$next` | `Closure` | Le prochain middleware ou contrôleur à exécuter |

**Retourne :** `Symfony\Component\HttpFoundation\Response` - La réponse HTTP (JSON d'erreur ou réponse du prochain middleware)

**Exceptions :** Aucune exception levée directement - toutes les erreurs retournent des réponses JSON structurées

**Exemple :**
```php
<?php

declare(strict_types=1);

// Utilisation avec une route Laravel
Route::middleware(['validate.mail.authenticatable'])->post('/api/login', function (Request $request) {
    // Le middleware valide que model_type existe et est valide
    // Le service d'authentification est automatiquement lié
});
```

## Cas d'utilisation

### Cas 1 : Route d'authentification protégée

**Problème** : Plusieurs modèles utilisateurs peuvent s'authentifier (ex: `User`, `Admin`). Chaque requête doit spécifier quel modèle utiliser.

**Solution** : Le middleware valide le `model_type` et lie le bon service.

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Http\Middleware\ValidateMailAuthenticatableMiddleware;
use Illuminate\Support\Facades\Route;

// Groupe de routes protégées par le middleware
Route::middleware(['validate.mail.authenticatable'])->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/register', [RegisterController::class, 'register']);
    Route::post('/logout', [LogoutController::class, 'logout']);
});
```

### Cas 2 : Requête avec modèle valide

**Problème** : L'utilisateur souhaite se connecter en utilisant le modèle `User`.

**Solution** : Le middleware vérifie le modèle et passe la requête.

```php
<?php

declare(strict_types=1);

// Requête valide - model_type spécifie le modèle User
$payload = [
    'model_type' => App\Models\User::class,
    'email' => 'john@example.com',
    'password' => 'Secret123!',
];

$response = $this->postJson('/api/login', $payload);
// ✅ Le middleware valide et lie le service
// ✅ La requête est transmise au contrôleur
```

### Cas 3 : Gestion des cookies automatique

**Problème** : Dans une application web, le token d'authentification doit être stocké dans un cookie pour les sessions.

**Solution** : Le middleware détecte automatiquement les cookies en file d'attente et les ajoute à la réponse.

```php
<?php

declare(strict_types=1);

// Configuration dans config/authentication-kit.php
'store_token_in_cookie' => env('AUTH_KIT_STORE_TOKEN_IN_COOKIE', true),

// Route protégée
Route::middleware(['validate.mail.authenticatable'])->post('/login', function (Request $request) {
    // Le token est automatiquement stocké dans le cookie
    // Grâce au middleware qui ajoute les cookies à la réponse
});
```

## Flux d'exécution

```
Requête entrante
    ↓
1. Récupération de model_type
    ↓
2. Validation de la présence
    ├── Absent → 400 (MODEL_TYPE_REQUIRED)
    ↓
3. Validation de l'existence de la classe
    ├── Non trouvée → 500 (MODEL_NOT_FOUND)
    ↓
4. Validation de l'implémentation de MailAuthenticatable
    ├── Non implémentée → 500 (INVALID_MODEL)
    ↓
5. Liaison du MailAuthenticationService
    ↓
6. Exécution du prochain middleware/contrôleur
    ↓
7. Récupération de la réponse
    ↓
8. Vérification de la configuration cookie
    ├── True → Ajout des cookies en file d'attente
    └── False → Pas d'ajout
    ↓
Réponse retournée
```

## Gestion des erreurs

| Situation | Code HTTP | ErrorCode | Message |
|-----------|-----------|-----------|---------|
| `model_type` manquant | 400 | `MODEL_TYPE_REQUIRED` | `model_type is required` |
| Classe non trouvée | 500 | `MODEL_NOT_FOUND` | `Model {className} does not exist` |
| Interface non implémentée | 500 | `INVALID_MODEL` | `Model {className} must implement AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable` |

## Intégration

### Avec MailServiceProvider

Le middleware est automatiquement enregistré :

```php
<?php

// Dans MailServiceProvider
public function boot(): void
{
    $this->app->make(Router::class)->aliasMiddleware(
        name: 'validate.mail.authenticatable',
        class: ValidateMailAuthenticatableMiddleware::class
    );
}
```

### Avec d'autres middlewares

L'ordre des middlewares est important :

```php
<?php

// Ordre recommandé
Route::middleware([
    'validate.mail.authenticatable', // Premier : vérifie le modèle
    'nemesis.token',                 // Ensuite : valide le token
])->post('/logout', LogoutController::class);
```

### Avec le service d'authentification

Le middleware lie automatiquement le service :

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function login(Request $request, MailAuthenticationInterface $auth)
    {
        // $auth est automatiquement lié au bon modèle
        // grâce au middleware ValidateMailAuthenticatableMiddleware
        return $auth->login($request->only('email', 'password'));
    }
}
```

## Performance

- **Complexité** : O(1) - pas de boucle
- **Vérifications** : 
  - `class_exists()` : rapide, utilise le cache de chargement automatique
  - `class_implements()` : rapide, utilise le cache des classes
- **Liaison** : Faite une seule fois par requête
- **Mémoire** : Allocation minimale (quelques objets de réponse)
- **Impact** : Négligeable sur les performances globales

## Compatibilité

| Version | Support | Détails |
|---------|---------|---------|
| PHP 8.1+ | ✅ Complet | Toutes les fonctionnalités |
| PHP 8.0 | ✅ Complet | Support complet |
| Laravel 12 | ✅ Complet | Framework supporté |
| Laravel 13 | ✅ Complet | Framework supporté |
| Laravel 14 | ✅ Complet | Framework supporté |
| Laravel 15 | ✅ Complet | Framework supporté |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Http\Middleware\ValidateMailAuthenticatableMiddleware;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use Illuminate\Support\Facades\Route;

// 1. Définition du modèle utilisateur
class User extends Model implements MailAuthenticatable
{
    // Implémentation des méthodes requises
    public function getAuthIdentifierName(): string { return 'id'; }
    public function getAuthIdentifier(): mixed { return $this->id; }
    public function getAuthPassword(): string { return $this->password; }
    // ...
}

// 2. Configuration de la route avec le middleware
Route::middleware(['validate.mail.authenticatable'])->post('/api/login', function (Request $request) {
    $validated = $request->validate([
        'model_type' => 'required|string',
        'email' => 'required|email',
        'password' => 'required|string',
    ]);

    // Le middleware a déjà validé que model_type est valide
    // et a lié le service d'authentification approprié
    $auth = app(MailAuthenticationInterface::class);
    
    try {
        $user = $auth->login($validated['email'], $validated['password']);
        return response()->json(['token' => $user->token]);
    } catch (Exception $e) {
        return response()->json(['error' => $e->getMessage()], 401);
    }
});

// 3. Exemple de requête valide
$response = $this->postJson('/api/login', [
    'model_type' => User::class,
    'email' => 'john@example.com',
    'password' => 'Password123!',
]);

// 4. Exemple de requête invalide (model_type manquant)
$response = $this->postJson('/api/login', [
    'email' => 'john@example.com',
    'password' => 'Password123!',
]);
// Réponse : 400 - "model_type is required"

// 5. Exemple de requête invalide (classe inexistante)
$response = $this->postJson('/api/login', [
    'model_type' => 'NonExistentClass',
    'email' => 'john@example.com',
    'password' => 'Password123!',
]);
// Réponse : 500 - "Model NonExistentClass does not exist"
```

## Voir aussi

- `MailAuthenticatable` - Interface que doivent implémenter les modèles
- `MailAuthenticationInterface` - Service lié par le middleware
- `MailAuthenticationService` - Implémentation du service d'authentification
- `MailServiceProvider` - Enregistrement du middleware
- `ErrorResponseData` - Structure des réponses d'erreur