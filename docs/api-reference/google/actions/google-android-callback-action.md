# GoogleAndroidCallbackAction - Référence Technique

## Description

Action HTTP qui traite le callback Android du flow OAuth Google. Elle reçoit un `id_token` obtenu par Credential Manager, délègue la vérification et l'émission de token au `GoogleOAuthService`, et renvoie **exclusivement** une réponse JSON.

## Hiérarchie / Implémentations

```
AbstractAction (AndyDefer\Actions\Actions)
    └── GoogleAndroidCallbackAction
```

Dépendances injectées :

- `GoogleOAuthInterface` — service OAuth Google (par défaut `GoogleOAuthService`).

## Rôle principal

Cette action est le point d'entrée HTTP du flow **ID-token-only** côté Android. Elle :

1. Vérifie que le record reçu est bien un `GoogleAndroidCallbackRecord`.
2. Délègue la logique au `GoogleOAuthService::androidCallback()`.
3. **Impose** que la réponse produite soit de type `JSON` — toute autre réponse (VIEW, INERTIA, REDIRECT, …) est rejetée.
4. Convertit les exceptions en réponse JSON standardisée avec `ErrorResponseData` normalisé.
5. Distingue les erreurs métier (`GoogleOAuthException`) des erreurs techniques (`UnexpectedValueException`, `GuzzleException`, `Exception` générique).

Contrairement à `GoogleCallbackAction`, cette action **ne rend jamais de page** : Android n'a pas de WebView pour l'afficher.

## Installation

Enregistrée dans le conteneur par `GoogleServiceProvider` :

```php
$this->app->singleton(
    abstract: GoogleAndroidCallbackAction::class,
    concrete: function ($app): GoogleAndroidCallbackAction {
        return new GoogleAndroidCallbackAction(
            googleOAuth: $app->make(GoogleOAuthService::class),
        );
    }
);
```

Route associée (groupe `api.`) :

```php
Route::post('/google-android-callback', action_route(
    GoogleAndroidCallbackRequest::class,
    GoogleAndroidCallbackAction::class
))->name('google-android-callback');
```

## API / Méthodes publiques

### `run(AbstractRecord $record): ResponseFactory` (héritée)

Point d'entrée hérité de `AbstractAction`. Exécute le cycle `before() → handle() → after()`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Doit être un `GoogleAndroidCallbackRecord`, sinon `handle()` produit une réponse d'erreur. |

**Retourne :** `ResponseFactory` — toujours de type `JSON`.

**Exceptions :** `InvalidArgumentException` — si `$record` n'est pas un `GoogleAndroidCallbackRecord` (levé dans `before()` avant toute autre logique).

**Exemple :**

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleAndroidCallbackAction;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;

/** @var GoogleAndroidCallbackAction $action */
$record = new GoogleAndroidCallbackRecord(
    model_type: \App\Models\User::class,
    id_token: 'fake.jwt.token',
);

$factory = $action->run($record);
// → ResponseFactory de type JSON
```

### `handle(AbstractRecord $record): ResponseFactory` (protégée)

Méthode protégée implémentée par l'action. Le contrat : produire une réponse JSON uniquement.

**Comportement observable :**

- Succès → retourne directement la `ResponseFactory` produite par le service (déjà validée `JSON`).
- `GoogleOAuthException` avec `isProviderError() === true` → `GOOGLE_PROVIDER_ERROR`.
- `GoogleOAuthException` autre → `GOOGLE_CALLBACK_ERROR`.
- `UnexpectedValueException` (type de réponse invalide) → `GOOGLE_CALLBACK_UNEXPECTED_ERROR`.
- `GuzzleException` directe ou encapsulée (≤ 5 niveaux) → `GOOGLE_CALLBACK_ERROR`.
- Autres `Exception` → `GOOGLE_CALLBACK_UNEXPECTED_ERROR`.

## Cas d'utilisation

### Cas 1 : Route POST `/google-android-callback`

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleAndroidCallbackAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleAndroidCallbackRequest;
use function action_route;

Route::post(
    '/google-android-callback',
    action_route(GoogleAndroidCallbackRequest::class, GoogleAndroidCallbackAction::class)
)->name('google-android-callback');
```

Requête Android :

```json
POST /google-android-callback
Content-Type: application/json

{
    "model_type": "App\\Models\\User",
    "id_token": "eyJhbGciOiJSUzI1NiIs..."
}
```

Réponse (200) :

```json
{
    "message": "Login successful",
    "auth": { "id": 61, "name": "Andy Kani", "email": "..." },
    "token": "plain-nemesis-token",
    "modelType": "App\\Models\\User",
    "isNewUser": false,
    "claims": { "sub": "…", "email": "…", "emailVerified": true, … }
}
```

### Cas 2 : Erreur métier — mauvais modèle

Requête :

```json
POST /google-android-callback

{
    "model_type": "App\\Models\\Something",
    "id_token": "eyJhbGciOiJSUzI1NiIs..."
}
```

Réponse (400) :

```json
{
    "message": "The specified model_type must implement GoogleAuthenticatable to handle the Android flow",
    "status": 400,
    "errorCode": "GOOGLE_CALLBACK_ERROR",
    "errors": null
}
```

### Cas 3 : Erreur technique — service retourne une page

Si un override de `GoogleOAuthService::onAndroidCallbackSuccess()` retourne une `VIEW`, l'action transforme la réponse :

```json
{
    "message": "GoogleOAuthInterface::androidCallback() must return a JSON response, got \"view\".",
    "status": 500,
    "errorCode": "GOOGLE_CALLBACK_UNEXPECTED_ERROR",
    "errors": null
}
```

L'override fautif est immédiatement détectable via ce message.

## Flux d'exécution

```
run($record)
    ↓
before($record)                             ← garde-fou de type (InvalidArgumentException)
    ↓
handle($record)
    ↓
instanceof GoogleAndroidCallbackRecord ? ── non ──→ INVALID_RECORD_TYPE
    ↓ oui
googleOAuth->androidCallback($record)
    ↓
getType() === JSON ? ── non ──→ throw UnexpectedValueException
    ↓ oui
return $response

───────── catch ─────────

GoogleOAuthException       → isProviderError() ? GOOGLE_PROVIDER_ERROR : GOOGLE_CALLBACK_ERROR
UnexpectedValueException   → GOOGLE_CALLBACK_UNEXPECTED_ERROR
GuzzleException (niveau ≤5) → GOOGLE_CALLBACK_ERROR
Exception                  → GOOGLE_CALLBACK_UNEXPECTED_ERROR
```

## Gestion des erreurs

| Situation | Exception source | ErrorCode produit |
|-----------|------------------|-------------------|
| Record non conforme passé à `run()` | `InvalidArgumentException` (dans `before`) | *(exception levée)* |
| Record non conforme passé à `handle()` | — | `INVALID_RECORD_TYPE` |
| Google renvoie une erreur | `GoogleOAuthException::providerError` | `GOOGLE_PROVIDER_ERROR` |
| State mismatch / email non vérifié / modèle invalide | `GoogleOAuthException` autre | `GOOGLE_CALLBACK_ERROR` |
| Service retourne un type ≠ JSON | `UnexpectedValueException` | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` |
| Échec HTTP Google (Guzzle) | `GuzzleException` (directe ou encapsulée ≤5 niveaux) | `GOOGLE_CALLBACK_ERROR` |
| Autre exception | `Exception` | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` |

Chaque `ErrorCode` est converti via `toJsonResponseFactory(message: ...)` en `ErrorResponseData` normalisé :

```json
{
    "message": "…",
    "status": 400,
    "errorCode": "GOOGLE_CALLBACK_ERROR",
    "errors": null
}
```

## Intégration

```
Route POST /google-android-callback
        ↓
GoogleAndroidCallbackRequest         ← validation (model_type, id_token)
        ↓
GoogleAndroidCallbackAction          ← this
        ↓
GoogleOAuthService (interface)       ← logique OAuth
        ↓
GoogleIdTokenVerifier / Nemesis / Model
        ↓
ResponseFactory (JSON uniquement)
```

L'action est **volontairement mince** : aucune logique métier n'y réside. Toute la mécanique OAuth est dans `GoogleOAuthInterface`, ce qui permet de la substituer par un mock dans les tests (voir `GoogleAndroidCallbackActionTest`).

## Performance

- **O(1)** hors appels réseau et I/O du service.
- Un seul appel `googleOAuth->androidCallback($record)` par requête.
- `in_array($response->getType(), ALLOWED_RESPONSE_TYPES, true)` en O(1).
- Recherche `GuzzleException` dans la chaîne d'exceptions bornée à 5 niveaux.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+    | ✅ Complet (`readonly`, enums, match) |
| PHP 8.2+    | ✅ Complet |
| PHP 8.5     | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleAndroidCallbackAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleAndroidCallbackRequest;
use Illuminate\Support\Facades\Route;
use function action_route;

// 1. Route
Route::post(
    '/google-android-callback',
    action_route(GoogleAndroidCallbackRequest::class, GoogleAndroidCallbackAction::class)
)->name('google-android-callback');

// 2. Requête Android (Ktor / OkHttp / Retrofit)
// POST https://api.example.com/google-android-callback
// Content-Type: application/json
// {
//     "model_type": "App\\Models\\User",
//     "id_token": "eyJhbGciOiJSUzI1NiIs..."
// }

// 3. Réponse succès (200)
// {
//     "message": "Login successful",
//     "auth": { "id": 61, "name": "…", "email": "…" },
//     "token": "plain-nemesis-token",
//     "modelType": "App\\Models\\User",
//     "isNewUser": false,
//     "claims": { … }
// }

// 4. Réponse erreur métier (400)
// {
//     "message": "Google email is not verified",
//     "status": 400,
//     "errorCode": "GOOGLE_CALLBACK_ERROR",
//     "errors": null
// }

// 5. Réponse erreur technique (500)
// {
//     "message": "GoogleOAuthInterface::androidCallback() must return a JSON response, got \"view\".",
//     "status": 500,
//     "errorCode": "GOOGLE_CALLBACK_UNEXPECTED_ERROR",
//     "errors": null
// }
```

## Voir aussi

- [`GoogleOAuthService`](../Services/GoogleOAuthService.md) — implémentation par défaut du service
- [`GoogleOAuthInterface`](../Contracts/GoogleOAuthInterface.md) — contrat du service
- [`GoogleAndroidCallbackRecord`](../Records/GoogleAndroidCallbackRecord.md) — input de l'action
- [`GoogleAndroidCallbackData`](../Datas/GoogleAndroidCallbackData.md) — payload de succès
- [`GoogleAndroidCallbackRequest`](../Requests/GoogleAndroidCallbackRequest.md) — validation HTTP
- [`GoogleCallbackAction`](./GoogleCallbackAction.md) — équivalent web (rendu de page)
- [`ErrorResponseData`](../../../../nemesis/Datas/ErrorResponseData.md) — structure d'erreur normalisée
- [`ErrorCode`](../../Enums/ErrorCode.md) — catalogue des codes d'erreur
