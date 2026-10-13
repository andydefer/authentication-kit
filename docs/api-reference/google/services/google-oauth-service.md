# GoogleOAuthService - Référence Technique

## Description

Orchestre le flux Google OAuth 2.0 / OpenID Connect côté serveur : construction de l'URL d'autorisation (PKCE, state, nonce) et traitement du callback (échange de code, vérification d'ID token, résolution finale).

## Hiérarchie

```
GoogleOAuthInterface
    └── GoogleOAuthService
```

Classe non-`final`. Conçue pour être étendue via surcharge de `onCallbackSuccess()` et des hooks `before*` / `after*`.

## Rôle principal

Implémente les **deux étapes serveur** de l'Authorization Code Flow avec PKCE :

1. **`authorizationRedirect()`** — Génère PKCE + state + nonce, persiste le state, retourne l'URL Google.
2. **`callback()`** — Valide le state, échange le code contre des tokens Google, vérifie l'ID token, puis délègue la décision finale à `onCallbackSuccess()`.

Le service ne gère **aucune** persistance utilisateur ni session locale. Ces responsabilités sont portées par `onCallbackSuccess()`, que l'application surcharge.

## Installation

Aucun prérequis spécifique. Dépend de :

- `andydefer/php-client` (`ClientService`)
- `AuthenticationKitConfig` (client_id, secret, redirect_uri, scopes, TTL)
- `GooglePkceGenerator`, `GoogleStateStoreInterface`, `GoogleIdTokenVerifier`
- Extension PHP `openssl` (via `GoogleIdTokenVerifier`)

## API / Méthodes publiques

### `__construct(ClientService $client, AuthenticationKitConfig $config, GooglePkceGenerator $pkceGenerator, GoogleStateStoreInterface $stateStore, GoogleIdTokenVerifier $idTokenVerifier)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$client` | `ClientService` | Client HTTP pour l'appel `POST /token` Google |
| `$config` | `AuthenticationKitConfig` | Config OAuth (client_id, secret, redirect_uri, scopes, TTL) |
| `$pkceGenerator` | `GooglePkceGenerator` | Génère code_verifier, code_challenge, state, nonce |
| `$stateStore` | `GoogleStateStoreInterface` | Persiste et consomme les state records |
| `$idTokenVerifier` | `GoogleIdTokenVerifier` | Vérifie cryptographiquement l'ID token |

**Exemple :**

```php
$service = new GoogleOAuthService(
    client: app(ClientService::class),
    config: app(AuthenticationKitConfig::class),
    pkceGenerator: app(GooglePkceGenerator::class),
    stateStore: app(GoogleStateStoreInterface::class),
    idTokenVerifier: app(GoogleIdTokenVerifier::class),
);
```

---

### `authorizationRedirect(GoogleRedirectRecord $record): GoogleRedirectData`

Construit l'URL de redirection Google et persiste le state associé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `GoogleRedirectRecord` | Contient `state`, `code_challenge`, `nonce`. Si vides, ils sont générés |

**Retourne :** `GoogleRedirectData` — objet avec la propriété `url`

**Comportement :**

1. Appelle `beforeAuthorizationRedirect()`.
2. Génère PKCE (`verifier` + `challenge`).
3. Utilise `state` fourni ou en génère un.
4. Utilise `nonce` fourni ou en génère un.
5. Persiste un `GoogleStateRecord` (verifier, nonce, expires_at) dans le state store avec TTL config.
6. Construit `GoogleAuthorizationRedirectRequest` et récupère l'URL.
7. Appelle `afterAuthorizationRedirect()`.
8. Retourne `GoogleRedirectData`.

**Exemple :**

```php
$data = $service->authorizationRedirect(new GoogleRedirectRecord(
    state: '',
    code_challenge: '',
    nonce: '',
));

return redirect()->away($data->url);
```

---

### `callback(GoogleCallbackRecord $record): ResponseFactory`

Traite le retour de Google, vérifie les tokens, et retourne la réponse HTTP finale.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `GoogleCallbackRecord` | Contient `code`, `state`, `error`, `error_description` |

**Retourne :** `ResponseFactory` — La réponse produite par `onCallbackSuccess()`

**Exceptions :** `GoogleOAuthException` — voir tableau ci-dessous

**Comportement :**

1. Appelle `beforeCallback()`.
2. Si `error` présent → `GoogleOAuthException::providerError()`.
3. Consomme le state store. Si absent → `stateNotFound()`.
4. Vérifie `expires_at`. Si expiré → `stateExpired()`.
5. Envoie `POST /token` à Google avec code + client_secret + code_verifier.
6. Vérifie l'ID token via `GoogleIdTokenVerifier::verify()`.
7. Construit `GoogleCallbackData`.
8. Appelle `afterCallback()`.
9. Retourne `onCallbackSuccess($data)`.

**Exemple :**

```php
try {
    $response = $service->callback(new GoogleCallbackRecord(
        code: $request->query('code', ''),
        state: $request->query('state', ''),
        error: $request->query('error'),
        error_description: $request->query('error_description'),
    ));

    return $response;
} catch (GoogleOAuthException $e) {
    // Gérer
}
```

---

### `onCallbackSuccess(GoogleCallbackData $data): ResponseFactory`

Décide de la réponse finale après un callback réussi.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `GoogleCallbackData` | Claims vérifiés + tokens Google |

**Retourne :** `ResponseFactory` — Réponse HTTP finale

**Implémentation par défaut :** retourne `ResponseFactory::json($data)` — utile pour les APIs pures.

**Surcharge attendue :** persister l'utilisateur, émettre une session, retourner un redirect.

**Exemple de surcharge :**

```php
public function onCallbackSuccess(GoogleCallbackData $data): ResponseFactory
{
    $user = User::firstOrCreate(
        ['email' => $data->claims->email],
        [
            'name' => $data->claims->name ?? $data->claims->email,
            'password' => null,
            'email_verified_at' => now(),
        ],
    );

    Auth::login($user);

    return ResponseFactory::redirect('/dashboard');
}
```

## Hooks (méthodes `protected`)

| Méthode | Signature | Rôle |
|---------|-----------|------|
| `beforeAuthorizationRedirect` | `(GoogleRedirectRecord): void` | Avant la construction de l'URL |
| `afterAuthorizationRedirect` | `(GoogleRedirectRecord, GoogleRedirectData): void` | Après la construction de l'URL |
| `beforeCallback` | `(GoogleCallbackRecord): void` | Avant le traitement du callback |
| `afterCallback` | `(GoogleCallbackRecord, GoogleCallbackData): void` | Après succès, avant `onCallbackSuccess()` |
| `afterCallbackFailed` | `(GoogleCallbackRecord, ErrorCode): void` | Après un échec (state, provider, etc.) |

Tous sont `void` par défaut. Surcharge libre, ils sont appelés à des moments précis du flux.

## Cas d'utilisation

### Cas 1 : Flux API pur (JSON)

Retourner les tokens au client sans gestion de session.

```php
$response = $service->callback($record);

// Réponse par défaut : { idToken, accessToken, expiresIn, tokenType, scope, claims }
return $response;
```

### Cas 2 : Flux web avec session

Créer l'utilisateur et rediriger.

```php
final class WebGoogleOAuthService extends GoogleOAuthService
{
    public function onCallbackSuccess(GoogleCallbackData $data): ResponseFactory
    {
        $user = User::firstOrCreate(
            ['email' => $data->claims->email],
            [
                'name' => $data->claims->name ?? $data->claims->email,
                'password' => null,
                'email_verified_at' => now(),
            ],
        );

        Auth::login($user);

        return ResponseFactory::redirect('/dashboard');
    }
}
```

### Cas 3 : Audit via hooks

Logger chaque étape du flux.

```php
final class LoggedGoogleOAuthService extends GoogleOAuthService
{
    protected function beforeCallback(GoogleCallbackRecord $record): void
    {
        Log::info('[Google] Callback received', ['state' => $record->state]);
    }

    protected function afterCallback(GoogleCallbackRecord $record, GoogleCallbackData $data): void
    {
        Log::info('[Google] Verified claims', ['sub' => $data->claims->sub]);
    }

    protected function afterCallbackFailed(GoogleCallbackRecord $record, ErrorCode $error): void
    {
        Log::warning('[Google] Callback failed', ['code' => $error->value]);
    }
}
```

## Flux d'exécution

### Authorization Redirect

```
authorizationRedirect(record)
    ↓
beforeAuthorizationRedirect(record)
    ↓
pkceGenerator.generate() → { verifier, challenge }
    ↓
state  = record.state  ?: pkceGenerator.generateState()
nonce  = record.nonce  ?: pkceGenerator.generateNonce()
    ↓
stateStore.put(state, GoogleStateRecord{verifier, nonce, expires_at}, ttl)
    ↓
new GoogleAuthorizationRedirectRequest(...)
    ↓
GoogleRedirectData { url }
    ↓
afterAuthorizationRedirect(record, data)
```

### Callback

```
callback(record)
    ↓
beforeCallback(record)
    ↓
record.error ? → throw GoogleOAuthException::providerError(...)
    ↓
stateStore.pull(record.state)
    ↓
state === null ? → throw GoogleOAuthException::stateNotFound()
    ↓
state.expires_at < now ? → throw GoogleOAuthException::stateExpired()
    ↓
new GoogleTokenExchangeRequest(...)
    ↓
client.post(token_endpoint, request, GoogleTokenExchangeResponse::class)
    ↓
response.getData() → GoogleTokensData
    ↓
idTokenVerifier.verify(tokens.idToken, state.nonce)
    ↓
GoogleCallbackData { idToken, accessToken, expiresIn, tokenType, scope, claims }
    ↓
afterCallback(record, data)
    ↓
onCallbackSuccess(data) → ResponseFactory
```

## Gestion des erreurs

| Situation | Exception | `isProviderError()` | Message |
|-----------|-----------|---------------------|---------|
| Google renvoie une erreur | `providerError()` | `true` | Description Google |
| State absent | `stateNotFound()` | `false` | `Google OAuth state not found` |
| State expiré | `stateExpired()` | `false` | `Google OAuth state has expired` |
| Échange de code échoue | `RuntimeException` (via `ClientService`) | — | `HTTP request failed: ...` |
| ID token invalide | `GoogleOAuthException::idToken*()` | `false` | Voir `GoogleIdTokenVerifier` |

L'appel HTTP à Google peut lever une `RuntimeException` gérée par `ClientService`. Elle n'est **pas** une `GoogleOAuthException` et doit être catchée séparément si nécessaire.

## Intégration

```
GoogleRedirectAction / GoogleCallbackAction
        ↓
GoogleOAuthInterface (résolu par le container)
        ↓
GoogleOAuthService (ou surcharge projet)
        ├── GooglePkceGenerator
        ├── GoogleStateStoreInterface  → CacheService
        ├── ClientService              → Guzzle
        ├── GoogleTokenExchangeResponse
        └── GoogleIdTokenVerifier
                └── ClientService (JWKS)
```

Le service est enregistré en `singleton` par `GoogleServiceProvider`. L'application peut le remplacer :

```php
$this->app->bind(
    GoogleOAuthInterface::class,
    \App\Services\Auth\AfyaGoogleOAuthService::class,
);
```

## Performance

- **Authorization Redirect** : très rapide (~1 ms) — génération aléatoire + 1 écriture cache.
- **Callback** : dominé par **deux appels HTTP** :
  - `POST /token` à Google (~100-300 ms).
  - `GET /certs` au JWKS Google (~100-200 ms).
- **Pas de cache JWKS** dans l'implémentation actuelle. Pour de fortes charges, wrapper `GoogleIdTokenVerifier::resolvePublicKey()` avec un cache.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Enums\ErrorCode;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Records\GoogleCallbackRecord;
use AndyDefer\AuthenticationKit\Google\Records\GoogleRedirectRecord;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;

/** @var GoogleOAuthService $service */

// ============================================================
// 1. Redirection initiale
// ============================================================
$redirect = $service->authorizationRedirect(new GoogleRedirectRecord(
    state: '',
    code_challenge: '',
    nonce: '',
));

return redirect()->away($redirect->url);

// ============================================================
// 2. Callback
// ============================================================
try {
    return $service->callback(new GoogleCallbackRecord(
        code: $request->query('code', ''),
        state: $request->query('state', ''),
        error: $request->query('error'),
        error_description: $request->query('error_description'),
    ));
} catch (GoogleOAuthException $e) {
    $errorCode = $e->isProviderError()
        ? ErrorCode::GOOGLE_PROVIDER_ERROR
        : ErrorCode::GOOGLE_CALLBACK_ERROR;

    return $errorCode->toJsonResponseFactory(message: $e->getMessage());
}
```

## Voir aussi

- `GoogleOAuthInterface` — Contrat implémenté
- `GooglePkceGenerator` — Génère PKCE, state, nonce
- `GoogleStateStoreInterface` — Persistance du state
- `GoogleIdTokenVerifier` — Vérification cryptographique de l'ID token
- `GoogleRedirectRecord`, `GoogleCallbackRecord` — Records d'entrée
- `GoogleRedirectData`, `GoogleCallbackData` — DTOs de sortie
- `GoogleOAuthException` — Exception unique avec `isProviderError()`
- `GoogleAuthorizationRedirectRequest`, `GoogleTokenExchangeRequest` — Requêtes HTTP vers Google
- `GoogleCallbackAction`, `GoogleRedirectAction` — Actions ADR qui consomment ce service