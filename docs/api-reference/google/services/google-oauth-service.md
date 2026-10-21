# GoogleOAuthService - Référence Technique

## Description

Implémentation par défaut de `GoogleOAuthInterface`. Elle orchestre les deux flux OAuth 2.0 supportés par le package : Authorization Code avec PKCE (navigateur) et ID-token-only (Android Credential Manager).

## Hiérarchie / Implémentations

```
GoogleOAuthInterface
    └── GoogleOAuthService
```

Dépendances injectées :

- `ClientService` — client HTTP pour l'échange de code et JWKS
- `AuthenticationKitConfig` — configuration Google (client_id, endpoints, callback.type, etc.)
- `GooglePkceGenerator` — génération PKCE (verifier, challenge, state, nonce)
- `GoogleStateStoreInterface` — stockage éphémère du state
- `GoogleIdTokenVerifier` — vérification JWT contre le JWKS Google
- `NemesisInterface` — émission de tokens Nemesis (flow Android uniquement)

## Rôle principal

C'est le point d'entrée unique côté serveur pour interagir avec Google. Il expose deux méthodes publiques qui correspondent aux deux flux OAuth :

1. **`authorizationRedirect()`** — construit l'URL d'autorisation Google (flow navigateur, Authorization Code + PKCE).
2. **`callback()`** — traite le retour de Google, échange le code contre des tokens, vérifie l'`id_token`, et délègue la décision finale à `onCallbackSuccess()`.
3. **`androidCallback()`** — vérifie un `id_token` fourni directement par le client Android (Credential Manager), résout ou crée le modèle cible via `GoogleAuthenticatable::androidGenerate()`, émet un token Nemesis, et délègue à `onAndroidCallbackSuccess()`.

Le service expose également **sept hooks** (`beforeAuthorizationRedirect`, `afterAuthorizationRedirect`, `beforeCallback`, `afterCallback`, `afterCallbackFailed`, `beforeAndroidCallback`, `afterAndroidCallback`, `afterAndroidCallbackFailed`) pour brancher de la logique métier sans surcharger le flow OAuth lui-même.

## Installation

Enregistré par `GoogleServiceProvider`. Prérequis :

```bash
composer require andydefer/authentication-kit
php artisan vendor:publish --tag=authentication-kit-config
```

Variables d'environnement dans `.env` :

```
AUTH_KIT_GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
AUTH_KIT_GOOGLE_CLIENT_SECRET=xxx
AUTH_KIT_GOOGLE_REDIRECT_URI=https://app.test/auth/google/callback
```

Le modèle cible du flow Android doit implémenter **`MailAuthenticatable` + `GoogleAuthenticatable`**.

## API / Méthodes publiques

### `authorizationRedirect(GoogleRedirectRecord $record): GoogleRedirectData`

Construit l'URL d'autorisation Google et stocke l'état PKCE associé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `GoogleRedirectRecord` | Contient `state`, `nonce` (optionnels) et `operation` (`LOGIN` / `REGISTER`). |

**Retourne :** `GoogleRedirectData` — porte l'URL à laquelle rediriger l'utilisateur.

**Exceptions :** Aucune directement. Les hooks peuvent lever, et toute exception propagée est gérée par `GoogleRedirectAction`.

**Exemple :**

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;

/** @var GoogleOAuthService $service */
$record = GoogleRedirectRecord::from([
    'operation' => OperationType::LOGIN->value,
]);

$data = $service->authorizationRedirect($record);

header('Location: '.$data->url);
```

---

### `callback(GoogleCallbackRecord $record): ResponseFactory`

Traite le retour de Google après consentement (flow navigateur, Authorization Code + PKCE).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `GoogleCallbackRecord` | Contient `code`, `state`, et éventuellement `error` / `error_description`. |

**Retourne :** `ResponseFactory` — retourné tel quel par `onCallbackSuccess()`. Par défaut : une vue Blade ou une page Inertia.

**Exceptions :**

- `GoogleOAuthException::providerError(...)` — Google a renvoyé une erreur (`access_denied`, etc.).
- `GoogleOAuthException::stateNotFound()` — state absent du store (replay, session expirée).
- `GoogleOAuthException::stateExpired()` — TTL du state dépassé.
- `GoogleOAuthException::idTokenSignatureInvalid()` (via `GoogleIdTokenVerifier`) — signature JWT invalide.
- `GoogleOAuthException::emailNotVerified()` — email Google non vérifié.
- Toute exception levée par `$client->post()` lors de l'échange de code.

**Exemple :**

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;

/** @var GoogleOAuthService $service */
$record = GoogleCallbackRecord::from([
    'code' => $request->query('code'),
    'state' => $request->query('state'),
]);

$factory = $service->callback($record);
// → VIEW ou INERTIA, jamais JSON
```

---

### `onCallbackSuccess(GoogleCallbackData $data): ResponseFactory`

Décide du rendu final côté navigateur, une fois l'identité vérifiée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `GoogleCallbackData` | `idToken`, `accessToken`, `expiresIn`, `tokenType`, `scope`, `claims`, `operation`. |

**Retourne :** `ResponseFactory` — `VIEW` ou `INERTIA` selon `authentication-kit.google.callback.type`, avec la prop `data` contenant `GoogleCallbackData`.

**Exceptions :** `LogicException` — si `callback.type` n'est ni `view` ni `inertia` (impossible en pratique, la config le valide).

**Exemple :**

```php
<?php

declare(strict_types=1);

// Override pour persister l'utilisateur avant le rendu
final class MyGoogleOAuthService extends GoogleOAuthService
{
    protected function afterCallback(
        \AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord $record,
        \AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData $data,
    ): void {
        User::updateOrCreate(
            ['email' => $data->claims->email],
            ['name'  => $data->claims->name],
        );
    }
}
```

---

### `androidCallback(GoogleAndroidCallbackRecord $record): ResponseFactory`

Traite un `id_token` fourni directement par Credential Manager (flow Android, ID-token-only).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `GoogleAndroidCallbackRecord` | Contient `model_type` (FQCN du modèle cible) et `id_token` (JWT Google). |

**Retourne :** `ResponseFactory` — retourné tel quel par `onAndroidCallbackSuccess()`. Par défaut : JSON avec `GoogleAndroidCallbackData`.

**Exceptions :**

- `GoogleOAuthException::emailNotVerified()` — email Google non vérifié.
- `GoogleOAuthException::modelNotFound()` — la classe `model_type` n'existe pas.
- `GoogleOAuthException::invalidModel()` — la classe n'implémente pas `MailAuthenticatable`.
- `GoogleOAuthException::modelNotGoogleAuthenticatable()` — la classe n'implémente pas `GoogleAuthenticatable`.
- Toute exception remontée par `GoogleIdTokenVerifier::verify()` ou `NemesisInterface::createWithPlainToken()`.

**Exemple :**

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;

/** @var GoogleOAuthService $service */
$record = new GoogleAndroidCallbackRecord(
    model_type: \App\Models\User::class,
    id_token: $request->input('id_token'),
);

$factory = $service->androidCallback($record);
// → JSON avec { message, auth, token, modelType, isNewUser, claims }
```

---

### `onAndroidCallbackSuccess(GoogleAndroidCallbackData $data): ResponseFactory`

Décide de la réponse finale côté Android, une fois le token émis.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `GoogleAndroidCallbackData` | Payload complet (message, auth, token, modelType, isNewUser, claims). |

**Retourne :** `ResponseFactory` — par défaut `ResponseFactory::json($data)`.

**Exceptions :** Aucune.

**Exemple :**

```php
<?php

declare(strict_types=1);

// Override pour ajouter un cookie HttpOnly avec le token
final class MyGoogleOAuthService extends GoogleOAuthService
{
    public function onAndroidCallbackSuccess(
        \AndyDefer\AuthenticationKit\Google\Datas\GoogleAndroidCallbackData $data,
    ): \AndyDefer\Actions\Http\ResponseFactory {
        return \AndyDefer\Actions\Http\ResponseFactory::json($data)
            ->withHeaders([
                'X-Token-Issued' => 'true',
            ]);
    }
}
```

## Cas d'utilisation

### Cas 1 : Rediriger un utilisateur vers Google (web)

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;

$record = GoogleRedirectRecord::from([
    'operation' => OperationType::LOGIN->value,
]);

$data = app(\AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService::class)
    ->authorizationRedirect($record);

return redirect($data->url);
```

### Cas 2 : Traiter le callback navigateur

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;

$record = GoogleCallbackRecord::from([
    'code'              => $request->query('code'),
    'state'             => $request->query('state'),
    'error'             => $request->query('error'),
    'error_description' => $request->query('error_description'),
]);

return app(\AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService::class)
    ->callback($record)
    ->toResponse();
```

### Cas 3 : Authentifier un client Android

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;

$record = new GoogleAndroidCallbackRecord(
    model_type: \App\Models\User::class,
    id_token: $request->input('id_token'),
);

return app(\AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService::class)
    ->androidCallback($record)
    ->toResponse();
```

### Cas 4 : Persister l'utilisateur après le callback navigateur

```php
<?php

declare(strict_types=1);

final class AuthenticatingGoogleService extends GoogleOAuthService
{
    protected function afterCallback(
        \AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord $record,
        \AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData $data,
    ): void {
        User::updateOrCreate(
            ['email' => $data->claims->email],
            ['name'  => $data->claims->name ?? $data->claims->email],
        );
    }
}
```

## Flux d'exécution

### `authorizationRedirect()`

```
GoogleRedirectRecord
    ↓
beforeAuthorizationRedirect()              ← hook
    ↓
GooglePkceGenerator::generate()            → [verifier, challenge]
    ↓
state (fourni ou généré) / nonce (fourni ou généré)
    ↓
GoogleStateStoreInterface::put(state, GoogleStateRecord, ttl)
    ↓
GoogleAuthorizationRedirectRequest → URL
    ↓
GoogleRedirectData(url)
    ↓
afterAuthorizationRedirect()                ← hook
```

### `callback()`

```
GoogleCallbackRecord
    ↓
beforeCallback()                            ← hook
    ↓
error présent ? ── oui ──→ afterCallbackFailed(GOOGLE_PROVIDER_ERROR) → throw
    ↓ non
GoogleStateStoreInterface::pull(state) ── absent ──→ afterCallbackFailed → throw
    ↓ présent
state expiré ? ── oui ──→ afterCallbackFailed → throw
    ↓ non
GoogleTokenExchangeRequest → ClientService::post → GoogleTokenExchangeResponse
    ↓
GoogleIdTokenVerifier::verify(idToken, nonce) → GoogleIdTokenClaimsData
    ↓
new GoogleCallbackData(...)
    ↓
afterCallback()                              ← hook
    ↓
onCallbackSuccess(data)                      → ResponseFactory
```

### `androidCallback()`

```
GoogleAndroidCallbackRecord
    ↓
beforeAndroidCallback()                      ← hook
    ↓
GoogleIdTokenVerifier::verify(idToken, null)
    ↓
emailVerified ? ── non ──→ afterAndroidCallbackFailed → throw
    ↓ oui
class_exists(model_type) ? ── non ──→ afterAndroidCallbackFailed → throw
    ↓ oui
is_subclass_of(MailAuthenticatable) ? ── non ──→ afterAndroidCallbackFailed → throw
    ↓ oui
is_subclass_of(GoogleAuthenticatable) ? ── non ──→ afterAndroidCallbackFailed → throw
    ↓ oui
where('email', claims->email)->first()
    ↓
null ? ── oui ──→ model_type::androidGenerate(claims)
    ↓ non
NemesisInterface::createWithPlainToken(...) → [token, plainToken]
    ↓
new GoogleAndroidCallbackData(...)
    ↓
afterAndroidCallback()                       ← hook
    ↓
onAndroidCallbackSuccess(data)               → ResponseFactory (JSON par défaut)
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Google renvoie `error` | `GoogleOAuthException` | Message de `error_description` ou `error` |
| State absent du store | `GoogleOAuthException` | `Google OAuth state not found` |
| State expiré | `GoogleOAuthException` | `Google OAuth state has expired` |
| Signature `id_token` invalide | `GoogleOAuthException` | `Google ID token signature is invalid` |
| Email Google non vérifié | `GoogleOAuthException` | `Google email is not verified` |
| `model_type` inexistant | `GoogleOAuthException` | `The specified model_type does not exist: X` |
| `model_type` sans `MailAuthenticatable` | `GoogleOAuthException` | `The specified model_type must implement MailAuthenticatable: X` |
| `model_type` sans `GoogleAuthenticatable` | `GoogleOAuthException` | `The specified model_type cannot handle the Google Android flow, it must implement GoogleAuthenticatable: X` |
| `callback.type` invalide | `LogicException` | `Unsupported Google callback response type: X` |

Les `GoogleOAuthException` sont interceptées par `GoogleCallbackAction` ou `GoogleAndroidCallbackAction`, et converties en réponse Web (props `error`) ou JSON (`ErrorResponseData`).

## Intégration

```
GoogleRedirectAction   GoogleCallbackAction   GoogleAndroidCallbackAction
        ↓                      ↓                         ↓
                        GoogleOAuthService (interface)
                        ↓            ↓             ↓
              ClientService   StateStore   IdTokenVerifier
                        ↓                          ↓
                    Google APIs                JWKS Google
```

Les hooks sont les points d'extension naturels pour :

- la persistance de l'utilisateur (`afterCallback`) ;
- l'émission d'autres tokens (`afterAndroidCallback`) ;
- le logging métier (`beforeCallback`, `beforeAndroidCallback`) ;
- l'enrichissement de la réponse (`onAndroidCallbackSuccess`).

## Performance

- **`authorizationRedirect()`** : O(1) hors I/O du state store.
- **`callback()`** : deux requêtes HTTP sortantes (`token_endpoint`, `jwks_endpoint` mis en cache par `GoogleIdTokenVerifier`).
- **`androidCallback()`** : une vérification JWKS (mise en cache) + un SELECT (par email) + un INSERT conditionnel + un INSERT Nemesis.
- Aucune opération lourde non bornée.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet (readonly, enums, match) |
| PHP 8.2+ | ✅ Complet |
| PHP 8.5  | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Datas\GoogleAndroidCallbackData;
use AndyDefer\AuthenticationKit\Google\Datas\GoogleCallbackData;
use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\Records\GoogleAndroidCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;

final class MyGoogleOAuthService extends GoogleOAuthService
{
    protected function afterCallback(
        GoogleCallbackRecord $record,
        GoogleCallbackData $data,
    ): void {
        // Crée / met à jour l'utilisateur côté web
        User::updateOrCreate(
            ['email' => $data->claims->email],
            ['name'  => $data->claims->name ?? $data->claims->email],
        );
    }

    public function onAndroidCallbackSuccess(GoogleAndroidCallbackData $data): \AndyDefer\Actions\Http\ResponseFactory
    {
        // Log métier
        logger()->info('Android Google login', [
            'is_new_user' => $data->isNewUser,
            'email'       => $data->claims->email,
        ]);

        return parent::onAndroidCallbackSuccess($data);
    }
}

// --- Routes ---

Route::get('/auth/google/redirect', function () {
    $data = app(MyGoogleOAuthService::class)->authorizationRedirect(
        GoogleRedirectRecord::from(['operation' => OperationType::LOGIN->value])
    );

    return redirect($data->url);
});

Route::get('/auth/google/callback', function (\Illuminate\Http\Request $request) {
    $record = GoogleCallbackRecord::from([
        'code'              => $request->query('code'),
        'state'             => $request->query('state'),
        'error'             => $request->query('error'),
        'error_description' => $request->query('error_description'),
    ]);

    return app(MyGoogleOAuthService::class)->callback($record)->toResponse();
});

Route::post('/google-android-callback', function (\Illuminate\Http\Request $request) {
    $record = new GoogleAndroidCallbackRecord(
        model_type: \App\Models\User::class,
        id_token: $request->input('id_token'),
    );

    return app(MyGoogleOAuthService::class)->androidCallback($record)->toResponse();
});
```

## Voir aussi

- [`GoogleOAuthInterface`](../Contracts/GoogleOAuthInterface.md) — contrat du service
- [`GoogleAuthenticatable`](../Contracts/GoogleAuthenticatable.md) — contrat du modèle cible Android
- [`GoogleStateStoreInterface`](../Contracts/GoogleStateStoreInterface.md) — stockage du state PKCE
- [`GoogleIdTokenVerifier`](./GoogleIdTokenVerifier.md) — vérification du JWT Google
- [`GooglePkceGenerator`](./GooglePkceGenerator.md) — génération PKCE
- [`GoogleCallbackData`](../Datas/GoogleCallbackData.md) — payload du flow navigateur
- [`GoogleAndroidCallbackData`](../Datas/GoogleAndroidCallbackData.md) — payload du flow Android
- [`GoogleCallbackRecord`](../Records/GoogleCallbackRecord.md) — input du callback navigateur
- [`GoogleAndroidCallbackRecord`](../Records/GoogleAndroidCallbackRecord.md) — input du callback Android
- [`GoogleRedirectRecord`](../Records/GoogleRedirectRecord.md) — input de la redirection
- [`AuthenticationKitConfig`](../../Configs/AuthenticationKitConfig.md) — configuration Google
- [`GoogleCallbackAction`](../Actions/GoogleCallbackAction.md) — action HTTP du flow navigateur
- [`GoogleAndroidCallbackAction`](../Actions/GoogleAndroidCallbackAction.md) — action HTTP du flow Android
