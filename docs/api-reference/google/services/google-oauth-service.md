# GoogleOAuthService - Référence Technique

## Description

Implémentation par défaut de `GoogleOAuthInterface`. Elle orchestre les deux flux OAuth 2.0 supportés par le package — Authorization Code + PKCE (navigateur) et ID-token-only (Android Credential Manager) — et délègue toute décision métier à l'application via deux méthodes à surcharger.

## Hiérarchie / Implémentations

```
GoogleOAuthInterface
    └── GoogleOAuthService
```

Dépendances injectées :

- `ClientService` — client HTTP pour l'échange de code et le JWKS.
- `AuthenticationKitConfig` — configuration Google (client_id, endpoints, callback.type, etc.).
- `GooglePkceGenerator` — génération PKCE (verifier, challenge, state, nonce).
- `GoogleStateStoreInterface` — stockage éphémère du state PKCE.
- `GoogleIdTokenVerifier` — vérification du JWT Google contre le JWKS.

## Rôle principal

Le service est le point d'entrée unique côté serveur pour Google OAuth. Il expose :

1. **`authorizationRedirect()`** — construit l'URL d'autorisation Google (flow navigateur, Authorization Code + PKCE).
2. **`callback()`** — traite le retour du navigateur, échange le code, vérifie l'`id_token`, produit un `GoogleCallbackVO` et délègue à `onCallbackSuccess()`.
3. **`androidCallback()`** — vérifie un `id_token` fourni directement par Credential Manager, produit un `GoogleAndroidCallbackVO` et délègue à `onAndroidCallbackSuccess()`.
4. **`onCallbackSuccess()` / `onAndroidCallbackSuccess()`** — **points d'extension métier à surcharger** : résolution/création de l'utilisateur, émission de token, retour de `GoogleCallbackData` / `GoogleAndroidCallbackData` (ou d'un `ErrorResponseData` en cas d'échec).

Le service **ne retourne jamais** de `ResponseFactory` : les actions `GoogleCallbackAction` et `GoogleAndroidCallbackAction` transforment la donnée typée en vue/Inertia ou JSON.

Sept hooks (`beforeAuthorizationRedirect`, `afterAuthorizationRedirect`, `beforeCallback`, `afterCallback`, `afterCallbackFailed`, `beforeAndroidCallback`, `afterAndroidCallback`, `afterAndroidCallbackFailed`) permettent d'instrumenter le flow sans le modifier.

## Installation

Enregistré par `GoogleServiceProvider` :

```php
$this->app->singleton(
    abstract: GoogleOAuthService::class,
    concrete: function ($app): GoogleOAuthService {
        return new GoogleOAuthService(
            client: $app->make(ClientService::class),
            config: $app->make(AuthenticationKitConfig::class),
            pkceGenerator: $app->make(GooglePkceGenerator::class),
            stateStore: $app->make(GoogleStateStoreInterface::class),
            idTokenVerifier: $app->make(GoogleIdTokenVerifier::class),
        );
    }
);
```

Prérequis `.env` :

```
AUTH_KIT_GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
AUTH_KIT_GOOGLE_CLIENT_SECRET=xxx
AUTH_KIT_GOOGLE_REDIRECT_URI=https://app.test/auth/google/callback
```

## API / Méthodes publiques

### `authorizationRedirect(GoogleRedirectRecord $record): GoogleRedirectData`

Construit l'URL d'autorisation Google et stocke l'état PKCE.

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

### `callback(GoogleCallbackRecord $record): GoogleCallbackData|ErrorResponseData`

Traite le retour du navigateur (Authorization Code + PKCE).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `GoogleCallbackRecord` | Contient `code`, `state`, et éventuellement `error` / `error_description`. |

**Retourne :** `GoogleCallbackData|ErrorResponseData` — retourné tel quel par `onCallbackSuccess()`.

**Exceptions :** `GoogleOAuthException` — non levée dans le flow nominal, mais peut l'être si l'appel HTTP vers Google échoue (`GuzzleException`) ; sinon toute erreur métier est retournée comme `ErrorResponseData`.

**Exemple :**

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;

/** @var GoogleOAuthService $service */
$record = GoogleCallbackRecord::from([
    'code'  => $request->query('code'),
    'state' => $request->query('state'),
]);

$result = $service->callback($record);

if ($result instanceof \AndyDefer\Nemesis\Datas\ErrorResponseData) {
    // gérer l'erreur métier
}

// $result est un GoogleCallbackData (retourné par onCallbackSuccess())
```

### `androidCallback(GoogleAndroidCallbackRecord $record): GoogleAndroidCallbackData|ErrorResponseData`

Traite un `id_token` fourni directement par Credential Manager (ID-token-only).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `GoogleAndroidCallbackRecord` | Contient `model_type` (FQCN du modèle cible) et `id_token` (JWT Google). |

**Retourne :** `GoogleAndroidCallbackData|ErrorResponseData` — retourné tel quel par `onAndroidCallbackSuccess()`.

**Exceptions :** `GoogleOAuthException` — non levée dans le flow nominal ; peut l'être si l'appel JWKS échoue.

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

$result = $service->androidCallback($record);
```

### `onCallbackSuccess(GoogleCallbackVO $vo): GoogleCallbackData|ErrorResponseData`

**Point d'extension à surcharger.** Reçoit les données Google vérifiées, doit retourner un `GoogleCallbackData` pleinement rempli (auth, token, isNewUser, metadata) — ou un `ErrorResponseData`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$vo` | `GoogleCallbackVO` | `idToken`, `accessToken`, `expiresIn`, `tokenType`, `scope`, `claims` (`GoogleIdTokenClaimsVO`), `operation`. |

**Retourne :** `GoogleCallbackData|ErrorResponseData`.

**Implémentation par défaut :** retourne `ErrorResponseData` avec le message `onCallbackSuccess() is not implemented for this flow.`

**Exemple :**

```php
<?php

declare(strict_types=1);

final class MyGoogleOAuthService extends GoogleOAuthService
{
    public function onCallbackSuccess(GoogleCallbackVO $vo): GoogleCallbackData|ErrorResponseData
    {
        $user = User::updateOrCreate(
            ['email' => $vo->getClaims()->getEmail()],
            ['name'  => $vo->getClaims()->displayName()],
        );

        [$token, $plainToken] = $this->nemesis->createWithPlainToken(
            /* ... */,
            $user,
        );

        return new GoogleCallbackData(
            idToken: $vo->getIdToken(),
            accessToken: $vo->getAccessToken(),
            expiresIn: $vo->getExpiresIn(),
            tokenType: $vo->getTokenType(),
            scope: $vo->getScope(),
            claims: $vo->getClaims(),
            operation: $vo->getOperation(),
            auth: new StrictAssociative([...]),
            token: $plainToken,
            isNewUser: $user->wasRecentlyCreated,
        );
    }
}
```

### `onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData`

**Point d'extension à surcharger.** Reçoit les claims vérifiés et le `model_type`, doit retourner un `GoogleAndroidCallbackData` pleinement rempli — ou un `ErrorResponseData`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$vo` | `GoogleAndroidCallbackVO` | `claims` (`GoogleIdTokenClaimsVO`), `modelType`. |

**Retourne :** `GoogleAndroidCallbackData|ErrorResponseData`.

**Implémentation par défaut :** retourne `ErrorResponseData` avec le message `onAndroidCallbackSuccess() is not implemented for this flow.`

**Exemple :**

```php
<?php

declare(strict_types=1);

final class MyGoogleOAuthService extends GoogleOAuthService
{
    public function onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData
    {
        $email = strtolower($vo->getClaims()->getEmail());

        $user = User::where('email', $email)->first();
        $isNewUser = $user === null;

        if ($isNewUser) {
            $user = User::create([
                'name'              => $vo->getClaims()->displayName(),
                'email'             => $email,
                'email_verified_at' => now(),
            ]);
        }

        [$token, $plainToken] = $this->nemesis->createWithPlainToken(
            /* ... */,
            $user,
        );

        return new GoogleAndroidCallbackData(
            claims: $vo->getClaims(),
            modelType: $vo->getModelType(),
            auth: new StrictAssociative([...]),
            token: $plainToken,
            isNewUser: $isNewUser,
        );
    }
}
```

## Cas d'utilisation

### Cas 1 : Rediriger un utilisateur vers Google

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;

$record = GoogleRedirectRecord::from([
    'operation' => OperationType::LOGIN->value,
]);

$data = app(GoogleOAuthService::class)->authorizationRedirect($record);

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

return app(GoogleOAuthService::class)->callback($record);
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

return app(GoogleOAuthService::class)->androidCallback($record);
```

### Cas 4 : Instrumenter le flow web

```php
<?php

declare(strict_types=1);

final class AuditingGoogleOAuthService extends GoogleOAuthService
{
    protected function afterCallback(GoogleCallbackRecord $record, GoogleCallbackVO $vo): void
    {
        Log::info('Google web callback verified', [
            'email'     => $vo->getClaims()->getEmail(),
            'operation' => $vo->getOperation()->value,
        ]);
    }
}
```

### Cas 5 : Instrumenter le flow Android

```php
<?php

declare(strict_types=1);

final class AuditingGoogleOAuthService extends GoogleOAuthService
{
    protected function afterAndroidCallback(
        GoogleAndroidCallbackRecord $record,
        GoogleAndroidCallbackVO $vo,
    ): void {
        Log::info('Google Android callback verified', [
            'email'     => $vo->getClaims()->getEmail(),
            'modelType' => $vo->getModelType(),
        ]);
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
error présent ? ── oui ──→ afterCallbackFailed(GOOGLE_PROVIDER_ERROR) → ErrorResponseData
    ↓ non
GoogleStateStoreInterface::pull(state) ── absent ──→ afterCallbackFailed → ErrorResponseData
    ↓ présent
state expiré ? ── oui ──→ afterCallbackFailed(GOOGLE_STATE_EXPIRED) → ErrorResponseData
    ↓ non
GoogleTokenExchangeRequest → ClientService::post → GoogleTokenExchangeResponse
    ↓
GoogleIdTokenVerifier::verify(idToken, nonce) → GoogleIdTokenClaimsVO
    ↓
new GoogleCallbackVO(...)
    ↓
afterCallback(record, vo)                   ← hook
    ↓
onCallbackSuccess(vo)                       → GoogleCallbackData|ErrorResponseData
```

### `androidCallback()`

```
GoogleAndroidCallbackRecord
    ↓
beforeAndroidCallback()                     ← hook
    ↓
GoogleIdTokenVerifier::verify(idToken, null) → GoogleIdTokenClaimsVO
    ↓
claims->isEmailVerified() ? ── non ──→ afterAndroidCallbackFailed → ErrorResponseData
    ↓ oui
class_exists(model_type) ? ── non ──→ afterAndroidCallbackFailed(GOOGLE_MODEL_NOT_FOUND) → ErrorResponseData
    ↓ oui
is_subclass_of(MailAuthenticatable) ? ── non ──→ afterAndroidCallbackFailed(GOOGLE_INVALID_MODEL) → ErrorResponseData
    ↓ oui
new GoogleAndroidCallbackVO(claims, modelType)
    ↓
afterAndroidCallback(record, vo)            ← hook
    ↓
onAndroidCallbackSuccess(vo)                → GoogleAndroidCallbackData|ErrorResponseData
```

## Gestion des erreurs

| Situation | ErrorCode retourné |
|-----------|--------------------|
| Google renvoie `error` (web) | `GOOGLE_PROVIDER_ERROR` |
| State absent du store | `GOOGLE_STATE_MISMATCH` |
| State expiré | `GOOGLE_STATE_EXPIRED` |
| Email Google non vérifié (Android) | `GOOGLE_CALLBACK_ERROR` |
| `model_type` inexistant | `GOOGLE_MODEL_NOT_FOUND` |
| `model_type` sans `MailAuthenticatable` | `GOOGLE_INVALID_MODEL` |
| `onCallbackSuccess()` / `onAndroidCallbackSuccess()` non surchargé | `GOOGLE_CALLBACK_ERROR` |
| Échec HTTP du client | `GuzzleException` propagée (à `GoogleCallbackAction` / `GoogleAndroidCallbackAction`) |

Le service retourne systématiquement un `ErrorResponseData` pour les erreurs métier. Les exceptions réellement lancées (`GuzzleException`) sont interceptées par les actions.

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

- la persistance applicative (`afterCallback` / `afterAndroidCallback`) ;
- l'émission de tokens (`onCallbackSuccess` / `onAndroidCallbackSuccess`) ;
- le logging métier (`beforeCallback` / `beforeAndroidCallback`) ;
- l'enrichissement du payload (`onAndroidCallbackSuccess` → `GoogleAndroidCallbackData::$metadata`).

## Performance

- **`authorizationRedirect()`** : O(1) hors I/O du state store.
- **`callback()`** : deux requêtes HTTP sortantes (`token_endpoint`, `jwks_endpoint` mis en cache par `GoogleIdTokenVerifier`).
- **`androidCallback()`** : une vérification JWKS (mise en cache) + un `class_exists` + un `is_subclass_of`.
- Aucune opération lourde non bornée. Le service ne crée pas d'utilisateur (délégué à `onAndroidCallbackSuccess()`).

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet (readonly, enums, match, intersection types) |
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
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleAndroidCallbackVO;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleCallbackVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Nemesis\Datas\ErrorResponseData;

final class MyGoogleOAuthService extends GoogleOAuthService
{
    public function onCallbackSuccess(GoogleCallbackVO $vo): GoogleCallbackData|ErrorResponseData
    {
        $user = User::updateOrCreate(
            ['email' => $vo->getClaims()->getEmail()],
            ['name'  => $vo->getClaims()->displayName()],
        );

        return new GoogleCallbackData(
            idToken: $vo->getIdToken(),
            accessToken: $vo->getAccessToken(),
            expiresIn: $vo->getExpiresIn(),
            tokenType: $vo->getTokenType(),
            scope: $vo->getScope(),
            claims: $vo->getClaims(),
            operation: $vo->getOperation(),
            auth: new StrictAssociative(['id' => $user->id, 'email' => $user->email]),
            token: 'plain-nemesis-token',
            isNewUser: $user->wasRecentlyCreated,
        );
    }

    public function onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData
    {
        $user = User::firstOrCreate(
            ['email' => $vo->getClaims()->getEmail()],
            ['name'  => $vo->getClaims()->displayName()],
        );

        return new GoogleAndroidCallbackData(
            claims: $vo->getClaims(),
            modelType: $vo->getModelType(),
            auth: new StrictAssociative(['id' => $user->id, 'email' => $user->email]),
            token: 'plain-nemesis-token',
            isNewUser: $user->wasRecentlyCreated,
        );
    }
}

// --- Routes

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

    return app(MyGoogleOAuthService::class)->callback($record);
});

Route::post('/google-android-callback', function (\Illuminate\Http\Request $request) {
    $record = new GoogleAndroidCallbackRecord(
        model_type: \App\Models\User::class,
        id_token: $request->input('id_token'),
    );

    return app(MyGoogleOAuthService::class)->androidCallback($record);
});
```

## Voir aussi

- [`GoogleOAuthInterface`](../Contracts/GoogleOAuthInterface.md) — contrat du service
- [`GoogleCallbackVO`](../ValueObjects/GoogleCallbackVO.md) — payload vérifié du flow web
- [`GoogleAndroidCallbackVO`](../ValueObjects/GoogleAndroidCallbackVO.md) — payload vérifié du flow Android
- [`GoogleIdTokenClaimsVO`](../ValueObjects/GoogleIdTokenClaimsVO.md) — claims Google vérifiés
- [`GoogleCallbackData`](../Datas/GoogleCallbackData.md) — payload de succès web
- [`GoogleAndroidCallbackData`](../Datas/GoogleAndroidCallbackData.md) — payload de succès Android
- [`GoogleIdTokenVerifier`](./GoogleIdTokenVerifier.md) — vérification du JWT Google
- [`GooglePkceGenerator`](./GooglePkceGenerator.md) — génération PKCE
- [`GoogleStateStoreInterface`](../Contracts/GoogleStateStoreInterface.md) — stockage du state PKCE
- [`GoogleCallbackAction`](../Actions/GoogleCallbackAction.md) — action HTTP du flow web
- [`GoogleAndroidCallbackAction`](../Actions/GoogleAndroidCallbackAction.md) — action HTTP du flow Android
- [`ErrorCode`](../../Enums/ErrorCode.md) — catalogue des codes d'erreur
