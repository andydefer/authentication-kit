# GoogleCallbackAction - Référence Technique

## Description

Action HTTP qui traite le retour de Google après consentement OAuth. Elle délègue la logique métier au `GoogleOAuthService` et force la production d'une réponse **Web** (`VIEW` ou `INERTIA`), jamais JSON. Les erreurs sont rendues dans la même vue / page Inertia avec la prop `error` de type `ErrorResponseData`.

## Hiérarchie / Implémentations

```
AbstractAction (AndyDefer\Actions\Actions)
    └── GoogleCallbackAction
```

Dépendances injectées :

- `GoogleOAuthInterface` — service OAuth Google (par défaut `GoogleOAuthService`).
- `AuthenticationKitConfigInterface` — configuration (`callback.type`, `callback.target`).

## Rôle principal

Cette action est le point d'entrée HTTP du callback OAuth. Elle :

1. Vérifie que le record reçu est bien un `GoogleCallbackRecord` (défense en profondeur).
2. Délègue au `GoogleOAuthService::callback()`.
3. **Impose** que la réponse produite soit de type `VIEW` ou `INERTIA` — tout autre type est rejeté.
4. Convertit **toute exception** (`GoogleOAuthException`, `UnexpectedValueException`, `GuzzleException`, etc.) en réponse Web avec `ErrorResponseData` normalisé dans la prop `error`.
5. Utilise le même `target` (vue Blade ou composant Inertia) pour le succès et les erreurs, pour que le front n'ait qu'un seul point d'entrée.

## Installation

`GoogleCallbackAction` est enregistrée dans le conteneur par `GoogleServiceProvider` :

```php
$this->app->singleton(
    abstract: GoogleCallbackAction::class,
    concrete: function ($app): GoogleCallbackAction {
        return new GoogleCallbackAction(
            googleOAuth: $app->make(GoogleOAuthService::class),
            config: $app->make(AuthenticationKitConfigInterface::class),
        );
    }
);
```

Configuration requise dans `config/authentication-kit.php` :

```php
'google' => [
    'callback' => [
        'type'   => 'view',                     // ou "inertia"
        'target' => 'auth.google-callback',     // nom de la vue / composant
    ],
],
```

## API / Méthodes publiques

### `run(AbstractRecord $record): ResponseFactory` (héritée)

Point d'entrée hérité de `AbstractAction`. Exécute le cycle `before() → handle() → after() → ResponseFactory`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Doit être un `GoogleCallbackRecord`, sinon `handle()` produit une réponse d'erreur. |

**Retourne :** `ResponseFactory` — toujours `VIEW` ou `INERTIA`.

**Exceptions :** `InvalidArgumentException` — si `$record` n'est pas un `GoogleCallbackRecord` (levé dans `before()` avant toute autre logique).

**Exemple :**

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;

/** @var GoogleCallbackAction $action */
$record = GoogleCallbackRecord::from([
    'code'  => '4/0AX4Xf...',
    'state' => 'abc123',
]);

$factory = $action->run($record);
// → ResponseFactory de type VIEW ou INERTIA
```

### `handle(AbstractRecord $record): ResponseFactory` (protégée)

Méthode protégée implémentée par l'action. Le contrat : produire une réponse Web.

**Comportement observable :**

- Succès → retourne directement la `ResponseFactory` produite par le service (déjà validée `VIEW`/`INERTIA`).
- `GoogleOAuthException` avec `isProviderError() === true` → `GOOGLE_PROVIDER_ERROR`.
- `GoogleOAuthException` autre → `GOOGLE_CALLBACK_ERROR`.
- `UnexpectedValueException` (type de réponse invalide) → `GOOGLE_CALLBACK_UNEXPECTED_ERROR`.
- `GuzzleException` directe ou encapsulée → `GOOGLE_CALLBACK_ERROR`.
- Autres `Exception` → `GOOGLE_CALLBACK_UNEXPECTED_ERROR`.

Chaque cas d'erreur produit une `ResponseFactory::view()` ou `ResponseFactory::inertia()` avec la prop `error` (voir plus bas).

### `before(AbstractRecord $record): void` (protégée, hook hérité)

Garde-fou de type : lève `InvalidArgumentException` si `$record` n'est pas un `GoogleCallbackRecord`.

## Cas d'utilisation

### Cas 1 : Route HTTP `/auth/google/callback`

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleCallbackRequest;
use function action_route;

Route::get(
    '/auth/google/callback',
    action_route(GoogleCallbackRequest::class, GoogleCallbackAction::class)
)->name('google.callback');
```

### Cas 2 : Override du target selon le contexte

```php
<?php

declare(strict_types=1);

// config/authentication-kit.php
'google' => [
    'callback' => [
        'type'   => 'inertia',
        'target' => 'Auth/GoogleCallback',
    ],
],
```

Toutes les réponses (succès **et** erreurs) sont rendues par le composant Inertia `Auth/GoogleCallback`. Le composant reçoit :

- sur succès : `data` (`GoogleCallbackData`)
- sur erreur  : `error` (`ErrorResponseData`)

### Cas 3 : Propagation d'erreur Guzzle encapsulée

Le client HTTP utilisé (`AndyDefer\PhpClient\Clients\ClientService`) peut encapsuler une `GuzzleException` dans une `RuntimeException`. L'action parcourt jusqu'à **5 niveaux** de `getPrevious()` pour identifier la cause réelle et la classer comme `GOOGLE_CALLBACK_ERROR` au lieu de `GOOGLE_CALLBACK_UNEXPECTED_ERROR`.

## Flux d'exécution

```
run($record)
    ↓
before($record)                      ← garde-fou de type (InvalidArgumentException)
    ↓
handle($record)
    ↓
instanceof GoogleCallbackRecord ? ── non ──→ renderError(INVALID_RECORD_TYPE)
    ↓ oui
googleOAuth->callback($record)
    ↓
getType() ∈ {VIEW, INERTIA} ? ── non ──→ throw UnexpectedValueException
    ↓ oui
return $response

───────── catch ─────────

GoogleOAuthException      → isProviderError() ? GOOGLE_PROVIDER_ERROR : GOOGLE_CALLBACK_ERROR
UnexpectedValueException  → GOOGLE_CALLBACK_UNEXPECTED_ERROR
GuzzleException (niveau ≤5) → GOOGLE_CALLBACK_ERROR
Exception                 → GOOGLE_CALLBACK_UNEXPECTED_ERROR

───────── renderError(ErrorResponseData) ─────────

type = config->getGoogleCallbackResponseType()      // VIEW | INERTIA
target = config->getGoogleCallbackTarget()           // auth.google-callback

match (type):
  VIEW    → ResponseFactory::view($target, ['error' => $error])
  INERTIA → ResponseFactory::inertia($target, ['error' => $error])
  default → LogicException (jamais atteint, config validée)
```

## Gestion des erreurs

| Situation | Exception source | ErrorCode produit |
|-----------|------------------|-------------------|
| Record non conforme | `InvalidArgumentException` (levée dans `before`) | *(exception levée, pas de réponse)* |
| Record non conforme passé directement à `handle()` | — | `INVALID_RECORD_TYPE` |
| Google renvoie une erreur | `GoogleOAuthException::providerError` | `GOOGLE_PROVIDER_ERROR` |
| State mismatch / expiré | `GoogleOAuthException` autre | `GOOGLE_CALLBACK_ERROR` |
| Service retourne un type ≠ VIEW/INERTIA | `UnexpectedValueException` | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` |
| Échec HTTP Google (Guzzle) | `GuzzleException` (directe ou encapsulée ≤5 niveaux) | `GOOGLE_CALLBACK_ERROR` |
| Autre exception | `Exception` | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` |

Chaque `ErrorCode` est converti via `toResponseData(message: ...)` en `ErrorResponseData`, dont la structure normalisée est :

```json
{
    "message": "…",
    "status": 500,
    "errorCode": "GOOGLE_CALLBACK_ERROR",
    "errors": null
}
```

Cette structure est injectée **en tant que prop `error`** dans la vue / le composant Inertia.

## Intégration

```
Route /auth/google/callback
        ↓
GoogleCallbackRequest                 ← validation (code, state, error…)
        ↓
GoogleCallbackAction                  ← this
        ↓
GoogleOAuthService (interface)        ← logique OAuth
        ↓
GoogleStateStore / GoogleIdTokenVerifier / ClientService
        ↓
ResponseFactory (VIEW | INERTIA)      ← rendu via config callback.target
```

`GoogleCallbackAction` est volontairement mince : **aucune logique métier** n'y réside. Toute la mécanique OAuth est dans `GoogleOAuthInterface`, ce qui permet de substituer une implémentation dans les tests (voir les helpers `runAction()` dans `GoogleCallbackActionTest`).

## Performance

- **O(1)** hors appels réseau et I/O du service.
- Un seul appel `googleOAuth->callback($record)` par requête.
- La vérification `in_array($response->getType(), ALLOWED_RESPONSE_TYPES, true)` est en O(1) (2 éléments).
- La recherche `GuzzleException` dans la chaîne d'exceptions est bornée à 5 niveaux.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+    | ✅ Complet (`readonly` properties, enums) |
| PHP 8.2+    | ✅ Complet |
| PHP 8.5     | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleCallbackRequest;
use Illuminate\Support\Facades\Route;
use function action_route;

// 1. Route
Route::get(
    '/auth/google/callback',
    action_route(GoogleCallbackRequest::class, GoogleCallbackAction::class)
)->name('google.callback');

// 2. Configuration (config/authentication-kit.php)
// 'google' => [
//     'callback' => [
//         'type'   => 'view',
//         'target' => 'auth.google-callback',
//     ],
// ],

// 3. Vue (resources/views/auth/google-callback.blade.php)
// <h1>{{ $error ? $error->errorCode : 'OK' }}</h1>
// @if($error)
//     <p>{{ $error->message }}</p>
// @endif
// @if(isset($data))
//     <p>Utilisateur : {{ $data->claims->email }}</p>
// @endif

// 4. Succès attendu :
//    view('auth.google-callback', ['data' => GoogleCallbackData])
//
// 5. Erreur attendue :
//    view('auth.google-callback', ['error' => ErrorResponseData])
```

## Voir aussi

- [`GoogleOAuthService`](../Services/GoogleOAuthService.md) — implémentation par défaut du service
- [`GoogleOAuthInterface`](../Contracts/GoogleOAuthInterface.md) — contrat du service
- [`GoogleCallbackRecord`](../Records/GoogleCallbackRecord.md) — input de l'action
- [`GoogleCallbackData`](../Datas/GoogleCallbackData.md) — payload de succès
- [`GoogleCallbackRequest`](../Requests/GoogleCallbackRequest.md) — validation HTTP
- [`ErrorResponseData`](../../../../nemesis/Datas/ErrorResponseData.md) — structure de la prop `error`
- [`ErrorCode`](../../Enums/ErrorCode.md) — catalogue des codes d'erreur
- [`AuthenticationKitConfig`](../../Configs/AuthenticationKitConfig.md) — configuration `callback.type` / `callback.target`
