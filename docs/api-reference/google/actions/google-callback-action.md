# GoogleCallbackAction - Référence Technique

## Description

Action HTTP qui traite le callback Google du flow **web (Authorization Code + PKCE)**. Elle rend la présentation configurée (vue Blade ou composant Inertia) avec soit la donnée de succès, soit une erreur normalisée.

## Hiérarchie / Implémentations

```
AbstractAction (AndyDefer\Actions\Actions)
    └── GoogleCallbackAction
```

Dépendances injectées :

- `GoogleOAuthInterface` — service OAuth Google (par défaut `GoogleOAuthService`).
- `AuthenticationKitConfigInterface` — configuration Google (`callback.type`, `callback.target`).

## Rôle principal

Cette action est le point d'entrée HTTP du flow navigateur. Elle :

1. Vérifie que le record reçu est bien un `GoogleCallbackRecord` (défense en profondeur via `before()` puis re-vérification dans `handle()`).
2. Délègue la vérification du code, l'échange de tokens et la résolution utilisateur au `GoogleOAuthService::callback()`.
3. **Rend une page** avec :
   - `success` => `GoogleCallbackData` en cas de succès,
   - `error`   => `ErrorResponseData` en cas d'échec.
4. Convertit **toute exception** en réponse d'erreur rendue dans la même page (jamais de throw propagé).

Contrairement à `GoogleAndroidCallbackAction` (flow Android), cette action **ne retourne jamais de JSON** : le client est un navigateur, la présentation est toujours une vue ou un composant Inertia.

## Installation

Enregistrée dans le conteneur par `GoogleServiceProvider` :

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

Route associée (groupe `web.`) :

```php
Route::name('web.')->group(function (): void {
    Route::get('/google-callback', action_route(
        GoogleCallbackRequest::class,
        GoogleCallbackAction::class
    ))->name('google-callback');
});
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

Point d'entrée hérité de `AbstractAction`. Exécute le cycle `before() → handle() → after()`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Doit être un `GoogleCallbackRecord`, sinon `handle()` produit une réponse d'erreur. |

**Retourne :** `ResponseFactory` — de type `VIEW` ou `INERTIA`.

**Exceptions :** `InvalidArgumentException` — si `$record` n'est pas un `GoogleCallbackRecord` (levé dans `before()` **avant** `handle()`).

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

Méthode protégée implémentée par l'action. Le contrat : produire une réponse de présentation Web.

**Comportement observable :**

| Résultat de `callback()` | Réponse produite |
|---|---|
| `GoogleCallbackData` | `view` / `inertia` avec `['success' => $data]` |
| `ErrorResponseData` | `view` / `inertia` avec `['error' => $data]` |
| Autre type | `view` / `inertia` avec `['error' => UNEXPECTED_ERROR]` |

**Gestion des exceptions :**

| Exception | ErrorCode | Prop injectée |
|---|---|---|
| `GoogleOAuthException` avec `isProviderError() === true` | `GOOGLE_PROVIDER_ERROR` | `error` |
| `GoogleOAuthException` autre | `GOOGLE_CALLBACK_ERROR` | `error` |
| `GuzzleException` (directe ou encapsulée ≤ 5 niveaux) | `GOOGLE_CALLBACK_ERROR` | `error` |
| Autre `Exception` | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` | `error` |

Le statut HTTP est toujours `200` : les erreurs sont rendues dans la page, pas signalées par un code HTTP d'erreur.

## Cas d'utilisation

### Cas 1 : Route GET `/google-callback` (groupe web)

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleCallbackRequest;
use function action_route;

Route::get(
    '/google-callback',
    action_route(GoogleCallbackRequest::class, GoogleCallbackAction::class)
)->name('google-callback');
```

Réponse succès :

```php
// La vue reçoit $success (GoogleCallbackData)
echo $success->claims->getEmail();
echo $success->token;
```

Réponse erreur :

```php
// La vue reçoit $error (ErrorResponseData)
echo $error->errorCode;
echo $error->message;
```

### Cas 2 : Composant Inertia

Config :

```php
'callback' => [
    'type'   => 'inertia',
    'target' => 'Auth/GoogleCallback',
],
```

Le composant Inertia reçoit :

```tsx
interface Props {
    success?: GoogleCallbackData;
    error?: ErrorResponseData;
}
```

Les deux props ne sont **jamais** présentes simultanément — l'action garantit l'exclusivité mutuelle via `renderSuccess()` / `renderError()`.

### Cas 3 : `ErrorResponseData` retourné par le service

Le service applicatif peut retourner directement un `ErrorResponseData` (erreur métier, application non autorisée, etc.) :

```php
// Dans une sous-classe de GoogleOAuthService
public function onCallbackSuccess(GoogleCallbackVO $vo): GoogleCallbackData|ErrorResponseData
{
    if (/* condition métier */) {
        return ErrorCode::GOOGLE_CALLBACK_ERROR->toResponseData(
            message: 'Cannot proceed for this domain',
        );
    }

    // ...
}
```

L'action rend la page avec `['error' => $error]`. Statut HTTP `200`.

### Cas 4 : `GoogleOAuthException` levée par le service ou un hook

Un override peut lever une `GoogleOAuthException` (par exemple `providerError` si le flow métier détecte une erreur venant de Google côté service) :

```php
throw GoogleOAuthException::providerError('access_denied');
```

L'action rend la page avec `['error' => GOOGLE_PROVIDER_ERROR]` — aucun throw propagé au client.

### Cas 5 : Échec HTTP Google (`GuzzleException`)

Si `ClientService::post()` lève une `ConnectException`, l'action rend la page avec `['error' => GOOGLE_CALLBACK_ERROR]` (message d'origine conservé).

### Cas 6 : `GuzzleException` encapsulée dans une `RuntimeException`

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
instanceof GoogleCallbackRecord ? ── non ──→ INVALID_RECORD_TYPE
    ↓ oui
googleOAuth->callback($record)
    ↓
┌─────────────────────────────────────┐
│  Résultat attendu                   │
└─────────────────────────────────────┘
    ├── GoogleCallbackData → renderSuccess → view/inertia(['success' => $data])
    ├── ErrorResponseData  → renderError   → view/inertia(['error'   => $error])
    └── autre              → renderError   → view/inertia(['error'   => UNEXPECTED])

───────── exceptions (throw) ─────────

GoogleOAuthException      → isProviderError() ? PROVIDER_ERROR : CALLBACK_ERROR
GuzzleException (≤5)      → CALLBACK_ERROR
Autre Exception           → UNEXPECTED_ERROR

───────── renderPresentation(props) ─────────

type = config->getGoogleCallbackResponseType()   // VIEW | INERTIA
target = config->getGoogleCallbackTarget()

match (type):
  VIEW    → ResponseFactory::view($target, $props)
  INERTIA → ResponseFactory::inertia($target, $props)
  default → ResponseFactory::text(..., 500)
```

## Gestion des erreurs

| Situation | ErrorCode | Statut HTTP |
|---|---|---|
| Record invalide passé à `run()` | `InvalidArgumentException` levée dans `before()` | — |
| Record invalide passé à `handle()` | `INVALID_RECORD_TYPE` | `200` (rendu dans la page) |
| `GoogleOAuthException::providerError()` | `GOOGLE_PROVIDER_ERROR` | `200` (rendu dans la page) |
| Autre `GoogleOAuthException` | `GOOGLE_CALLBACK_ERROR` | `200` (rendu dans la page) |
| `GuzzleException` directe ou encapsulée | `GOOGLE_CALLBACK_ERROR` | `200` (rendu dans la page) |
| Autre `Exception` | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` | `200` (rendu dans la page) |
| `ErrorResponseData` retourné par le service | dépend de `$error->errorCode` | `200` (rendu dans la page) |
| Type de retour inattendu | `GOOGLE_CALLBACK_UNEXPECTED_ERROR` | `200` (rendu dans la page) |

Chaque `ErrorCode` est converti via `toResponseData(message: ...)` en `ErrorResponseData`, dont la structure normalisée est :

```json
{
    "message": "…",
    "status": 400,
    "errorCode": "GOOGLE_CALLBACK_ERROR",
    "errors": null
}
```

Cette structure est injectée **en tant que prop `error`** dans la vue / le composant Inertia.

## Intégration

```
Route GET /google-callback
        ↓
GoogleCallbackRequest                ← validation (code, state, error…)
        ↓
GoogleCallbackAction                 ← this
        ↓
GoogleOAuthInterface (GoogleOAuthService)
        ↓
GoogleStateStore / GoogleIdTokenVerifier / ClientService / Application
        ↓
GoogleCallbackData | ErrorResponseData
        ↓
ResponseFactory (VIEW | INERTIA)
```

L'action est volontairement **mince** : aucune logique métier n'y réside. Toute la mécanique OAuth et la résolution utilisateur sont déléguées au service (via `onCallbackSuccess()` de l'application, voir `AfyaGoogleOAuthService`). Cela permet de substituer le service par un mock dans les tests (voir `GoogleCallbackActionTest`).

## Performance

- **O(1)** hors appels réseau et I/O du service.
- Un seul appel `googleOAuth->callback($record)` par requête.
- Un seul `match` sur le type de résultat (3 branches).
- Recherche `GuzzleException` dans la chaîne d'exceptions bornée à 5 niveaux.
- Aucune allocation superflue : le payload de succès est passé tel quel à `ResponseFactory::view()` / `ResponseFactory::inertia()`.

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

use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleCallbackRequest;
use Illuminate\Support\Facades\Route;
use function action_route;

// 1. Route
Route::get(
    '/google-callback',
    action_route(GoogleCallbackRequest::class, GoogleCallbackAction::class)
)->name('google-callback');

// 2. Configuration (config/authentication-kit.php)
// 'google' => [
//     'callback' => [
//         'type'   => 'inertia',
//         'target' => 'Auth/GoogleCallback',
//     ],
// ],

// 3. Composant Inertia (resources/js/pages/Auth/GoogleCallback.tsx)
// interface Props {
//     success?: GoogleCallbackData;
//     error?: ErrorResponseData;
// }
//
// if (error) {
//     // afficher error.errorCode / error.message
// }
//
// if (success) {
//     // success.data    : GoogleCallbackData (claims + tokens)
//     // success.token   : plain Nemesis token
//     // success.metadata: propriétés libres injectées par l'app
// }

// 4. Vue Blade (resources/views/auth/google-callback.blade.php)
// @if(isset($error))
//     <h1>{{ $error->errorCode }}</h1>
//     <p>{{ $error->message }}</p>
// @endif
//
// @if(isset($success))
//     <p>Utilisateur : {{ $success->claims->getEmail() }}</p>
//     <p>Token : {{ $success->token }}</p>
// @endif
```

## Voir aussi

- [`GoogleAndroidCallbackAction`](./GoogleAndroidCallbackAction.md) — équivalent Android (JSON uniquement)
- [`GoogleOAuthService`](../Services/GoogleOAuthService.md) — implémentation par défaut du service
- [`GoogleOAuthInterface`](../Contracts/GoogleOAuthInterface.md) — contrat du service
- [`GoogleCallbackRecord`](../Records/GoogleCallbackRecord.md) — input de l'action
- [`GoogleCallbackData`](../Datas/GoogleCallbackData.md) — payload de succès
- [`GoogleCallbackRequest`](../Requests/GoogleCallbackRequest.md) — validation HTTP
- [`GoogleOAuthException`](../Exceptions/GoogleOAuthException.md) — exception unique du flow Google
- [`ErrorCode`](../../Enums/ErrorCode.md) — catalogue des codes d'erreur
- [`ErrorResponseData`](../../../../nemesis/Datas/ErrorResponseData.md) — structure d'erreur normalisée
- [`AuthenticationKitConfig`](../../Configs/AuthenticationKitConfig.md) — configuration `callback.type` / `callback.target`
