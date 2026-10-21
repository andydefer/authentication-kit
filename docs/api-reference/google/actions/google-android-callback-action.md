# GoogleAndroidCallbackAction - Référence Technique

## Description

Action HTTP qui traite le callback Google du flow **Android ID-token-only**. Elle délègue la vérification et la résolution utilisateur/token au service, puis produit une réponse **JSON** contenant soit les données de succès, soit une erreur normalisée.

## Hiérarchie / Implémentations

```
AbstractAction (AndyDefer\Actions\Actions)
    └── GoogleAndroidCallbackAction
```

Dépendances injectées :

- `GoogleOAuthInterface` — service OAuth Google (par défaut `GoogleOAuthService`).

## Rôle principal

Cette action est le point d'entrée HTTP du flow **ID-token-only** côté Android. Elle :

1. Vérifie que le record reçu est bien un `GoogleAndroidCallbackRecord` (défense en profondeur via `before()` puis re-vérification dans `handle()`).
2. Délègue la vérification de l'`id_token` et la résolution utilisateur/token au `GoogleOAuthService::androidCallback()`.
3. Traduit le résultat en réponse JSON :
   - `GoogleAndroidCallbackData` → `ResponseFactory::json()` avec statut `200`.
   - `ErrorResponseData` → `ResponseFactory::json()` avec le statut porté par l'`ErrorResponseData` (400, 500, etc.).
4. Convertit **toute exception** en `ResponseFactory::json()` d'erreur normalisée (jamais de throw propagé).

Contrairement à `GoogleCallbackAction` (web flow), cette action **ne rend jamais de page** : le client Android consomme exclusivement du JSON.

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

**Exceptions :** `InvalidArgumentException` — si `$record` n'est pas un `GoogleAndroidCallbackRecord` (levé dans `before()` **avant** `handle()`).

**Exemple :**

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleAndroidCallbackAction;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;

/** @var GoogleAndroidCallbackAction $action */
$record = new GoogleAndroidCallbackRecord(
    model_type: \App\Models\User::class,
    id_token: 'eyJhbGciOiJSUzI1NiIs...',
);

$factory = $action->run($record);
// → ResponseFactory de type JSON
```

### `handle(AbstractRecord $record): ResponseFactory` (protégée)

Méthode protégée implémentée par l'action. Le contrat : produire une réponse JSON uniquement.

**Comportement observable :**

| Résultat de `androidCallback()` | Réponse produite |
|---|---|
| `GoogleAndroidCallbackData` | `ResponseFactory::json($data)` — statut `200` |
| `ErrorResponseData` | `ResponseFactory::json($error, $error->status)` — statut porté par l'erreur |
| Autre type | `ErrorCode::GOOGLE_CALLBACK_UNEXPECTED_ERROR->toJsonResponseFactory()` — statut `500` |

**Gestion des exceptions :**

| Exception | ErrorCode | Statut |
|---|---|---|
| `GoogleOAuthException` avec `isProviderError() === true` | `GOOGLE_PROVIDER_ERROR` | `400` |
| `GoogleOAuthException` autre | `GOOGLE_CALLBACK_ERROR` | `500` |
| `GuzzleException` (directe ou encapsulée ≤ 5 niveaux) | `GOOGLE_CALLBACK_ERROR` | `500` |
| Autre `Exception` | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` | `500` |

## Cas d'utilisation

### Cas 1 : Route POST `/api/google-android-callback`

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
POST /api/google-android-callback
Content-Type: application/json

{
    "model_type": "App\\Models\\User",
    "id_token": "eyJhbGciOiJSUzI1NiIs..."
}
```

Réponse succès (200) :

```json
{
    "claims": { "sub": "…", "email": "…", "emailVerified": true, "…": "…" },
    "modelType": "App\\Models\\User",
    "auth": { "id": 42, "email": "user@example.com" },
    "token": "plain-nemesis-token",
    "isNewUser": false,
    "metadata": {}
}
```

### Cas 2 : `ErrorResponseData` retourné par le service

Le service applicatif peut retourner directement un `ErrorResponseData` (cas nominal pour les erreurs métier) :

```php
<?php

declare(strict_types=1);

// Dans une sous-classe de GoogleOAuthService
public function onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData
{
    if (/* condition métier */) {
        return ErrorCode::GOOGLE_CALLBACK_ERROR->toResponseData(
            message: 'Cannot proceed for this domain',
        );
    }

    // ...
}
```

Réponse (500, statut porté par `GOOGLE_CALLBACK_ERROR`) :

```json
{
    "message": "Cannot proceed for this domain",
    "status": 500,
    "errorCode": "GOOGLE_CALLBACK_ERROR",
    "errors": null
}
```

L'action transmet tel quel le statut HTTP de l'`ErrorResponseData`.

### Cas 3 : `GoogleOAuthException` levée par le service ou un hook

Un override peut lever une `GoogleOAuthException` (par exemple `providerError`) si le flow métier détecte une erreur venant de Google côté service :

```php
throw GoogleOAuthException::providerError('access_denied');
```

L'action produit une réponse `GOOGLE_PROVIDER_ERROR` (statut `400`), sans faire remonter l'exception au client.

### Cas 4 : Échec HTTP Google (`GuzzleException`)

Si `ClientService::post()` lève une `ConnectException`, l'action classe la réponse comme `GOOGLE_CALLBACK_ERROR` (500), avec le message d'origine.

### Cas 5 : `GuzzleException` encapsulée dans une `RuntimeException`

Le client HTTP peut encapsuler l'exception Guzzle dans une `RuntimeException`. L'action parcourt `getPrevious()` jusqu'à **5 niveaux** pour identifier la cause réelle :

```php
try {
    // ...
} catch (RuntimeException $e) {
    // $e->getPrevious() est une ConnectException
    // → GOOGLE_CALLBACK_ERROR au lieu de UNEXPECTED
}
```

## Flux d'exécution

```
run($record)
    ↓
before($record)                    ← garde-fou de type (InvalidArgumentException)
    ↓
handle($record)
    ↓
instanceof GoogleAndroidCallbackRecord ? ── non ──→ INVALID_RECORD_TYPE
    ↓ oui
googleOAuth->androidCallback($record)
    ↓
┌─────────────────────────────────────┐
│  Résultat attendu                   │
└─────────────────────────────────────┘
    ├── GoogleAndroidCallbackData → json(data), 200
    ├── ErrorResponseData         → json(error, error->status)
    └── autre                     → json(UNEXPECTED, 500)

───────── exceptions (throw) ─────────

GoogleOAuthException      → isProviderError() ? PROVIDER_ERROR(400) : CALLBACK_ERROR(500)
GuzzleException (≤5)      → CALLBACK_ERROR(500)
Autre Exception           → UNEXPECTED_ERROR(500)
```

## Gestion des erreurs

| Situation | ErrorCode | Statut HTTP |
|---|---|---|
| Record invalide passé à `run()` | `InvalidArgumentException` levée dans `before()` | — |
| Record invalide passé à `handle()` | `INVALID_RECORD_TYPE` | `500` |
| `GoogleOAuthException::providerError()` | `GOOGLE_PROVIDER_ERROR` | `400` |
| Autre `GoogleOAuthException` | `GOOGLE_CALLBACK_ERROR` | `500` |
| `GuzzleException` directe ou encapsulée | `GOOGLE_CALLBACK_ERROR` | `500` |
| Autre `Exception` | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` | `500` |
| `ErrorResponseData` retourné par le service | dépend de `$error->errorCode` | `$error->status` |
| Type de retour inattendu | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` | `500` |

Toutes les réponses d'erreur sont des `ErrorResponseData` normalisés :

```json
{
    "message": "…",
    "status": 400,
    "errorCode": "GOOGLE_PROVIDER_ERROR",
    "errors": null
}
```

## Intégration

```
Route POST /api/google-android-callback
        ↓
GoogleAndroidCallbackRequest        ← validation (model_type, id_token)
        ↓
GoogleAndroidCallbackAction         ← this
        ↓
GoogleOAuthInterface (GoogleOAuthService)
        ↓
GoogleIdTokenVerifier / Model / Nemesis (délégués à l'application)
        ↓
GoogleAndroidCallbackData | ErrorResponseData
        ↓
ResponseFactory (JSON uniquement)
```

L'action est volontairement **mince** : aucune logique métier n'y réside. Toute la mécanique OAuth et la résolution utilisateur/token sont déléguées au service. Cela permet de substituer le service par un mock dans les tests (voir `GoogleAndroidCallbackActionTest`) ou par une implémentation applicative (ex. `AfyaGoogleOAuthService`).

## Performance

- **O(1)** hors appels réseau et I/O du service.
- Un seul appel `googleOAuth->androidCallback($record)` par requête.
- Un seul `match` sur le type de résultat (3 branches).
- Recherche `GuzzleException` dans la chaîne d'exceptions bornée à 5 niveaux.
- Aucune allocation superflue : le payload du succès est passé tel quel à `ResponseFactory::json()`.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+    | ✅ Complet (`readonly`, enums, `match`, type intersection) |
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
//     "claims": { "sub": "…", "email": "…", "emailVerified": true, … },
//     "modelType": "App\\Models\\User",
//     "auth": { "id": 42, "email": "…" },
//     "token": "plain-nemesis-token",
//     "isNewUser": false,
//     "metadata": {}
// }

// 4. Réponse provider error (400)
// {
//     "message": "access_denied",
//     "status": 400,
//     "errorCode": "GOOGLE_PROVIDER_ERROR",
//     "errors": null
// }

// 5. Réponse callback error (500)
// {
//     "message": "Google OAuth state not found",
//     "status": 500,
//     "errorCode": "GOOGLE_CALLBACK_ERROR",
//     "errors": null
// }

// 6. Réponse unexpected error (500)
// {
//     "message": "GoogleOAuthInterface::androidCallback() returned an unexpected type.",
//     "status": 500,
//     "errorCode": "GOOGLE_CALLBACK_UNEXPECTED_ERROR",
//     "errors": null
// }
```

## Voir aussi

- [`GoogleCallbackAction`](./GoogleCallbackAction.md) — équivalent web (rendu vue / Inertia)
- [`GoogleOAuthService`](../Services/GoogleOAuthService.md) — implémentation par défaut du service
- [`GoogleOAuthInterface`](../Contracts/GoogleOAuthInterface.md) — contrat du service
- [`GoogleAndroidCallbackRecord`](../Records/GoogleAndroidCallbackRecord.md) — input de l'action
- [`GoogleAndroidCallbackData`](../Datas/GoogleAndroidCallbackData.md) — payload de succès
- [`GoogleAndroidCallbackRequest`](../Requests/GoogleAndroidCallbackRequest.md) — validation HTTP
- [`GoogleOAuthException`](../Exceptions/GoogleOAuthException.md) — exception unique du flow Google
- [`ErrorCode`](../../Enums/ErrorCode.md) — catalogue des codes d'erreur
- [`ErrorResponseData`](../../../../nemesis/Datas/ErrorResponseData.md) — structure d'erreur normalisée
