# GoogleOAuthService - Référence Technique

## Description

Orchestre le flux OAuth 2.0 / OpenID Connect côté serveur avec Google : construction de l'URL d'autorisation (avec PKCE, state et nonce) et traitement du callback (échange du code contre tokens, vérification de l'ID token).

## Hiérarchie

```
GoogleOAuthInterface
    └── GoogleOAuthService
```

Classe non-`final` pour permettre le mocking. Les hooks `before*` / `after*` offrent des points d'extension via héritage.

## Rôle principal

C'est le **cœur** du flux Google. Il implémente les **deux étapes serveur** de l'Authorization Code Flow :

1. **Étape 1 — Authorization Redirect** : génère PKCE + state + nonce, persiste le tout dans un state store, retourne l'URL Google.
2. **Étape 2 — Callback** : valide le state, échange le code, vérifie l'ID token, retourne les claims et tokens.

Le service ne gère **aucune** persistance utilisateur, session locale ou token Nemesis — ce sont des responsabilités externes.

## Installation

Aucun prérequis spécifique. Dépend de :

- `andydefer/php-client` (`ClientService`)
- `AuthenticationKitConfig`
- `GooglePkceGenerator`, `GoogleStateStoreInterface`, `GoogleIdTokenVerifier`

## API / Méthodes publiques

### `__construct(ClientService $client, AuthenticationKitConfig $config, GooglePkceGenerator $pkceGenerator, GoogleStateStoreInterface $stateStore, GoogleIdTokenVerifier $idTokenVerifier)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$client` | `ClientService` | Client HTTP pour l'appel `POST /token` Google |
| `$config` | `AuthenticationKitConfig` | Config OAuth (client_id, secret, redirect_uri, scopes, TTL, PKCE length) |
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

Construit l'URL de redirection vers Google et persiste le state associé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `GoogleRedirectRecord` | Contient `state`, `code_challenge`, `nonce`. Si vides, ils sont générés |

**Retourne :** `GoogleRedirectData` — objet avec la propriété `url` (URL Google complète)

**Exceptions :** Aucune exception levée par cette méthode (les erreurs techniques remonteront depuis `stateStore->put()` si celui-ci échoue).

**Comportement :**

1. Génère PKCE (`code_verifier` + `code_challenge`).
2. Utilise `state` du record s'il est fourni, sinon génère un state aléatoire.
3. Utilise `nonce` du record s'il est fourni, sinon génère un nonce aléatoire.
4. Persiste un `GoogleStateRecord` (code_verifier, nonce, expires_at) dans le state store avec TTL config.
5. Construit l'URL Google avec tous les paramètres.
6. Retourne `GoogleRedirectData`.

**Exemple :**

```php
$data = $service->authorizationRedirect(new GoogleRedirectRecord(
    state: '',
    code_challenge: '',
    nonce: '',
));

// Rediriger l'utilisateur
return redirect($data->url);
```

---

### `callback(GoogleCallbackRecord $record): GoogleCallbackData`

Traite le retour de Google : valide le state, échange le code, vérifie l'ID token.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `GoogleCallbackRecord` | Contient `code`, `state`, et éventuellement `error` / `error_description` |

**Retourne :** `GoogleCallbackData` — contient `idToken`, `accessToken`, `expiresIn`, `tokenType`, `scope`, `claims`

**Exceptions :** `GoogleOAuthException` — plusieurs variantes selon le type d'échec

**Comportement :**

1. Si `error` présent dans le record → `GoogleOAuthException::providerError()`.
2. Consomme le state store (`pull`). Si absent → `stateNotFound()`.
3. Vérifie `expires_at` du state. Si expiré → `stateExpired()`.
4. Envoie `POST /token` à Google avec code + client_secret + code_verifier.
5. Vérifie l'ID token via `GoogleIdTokenVerifier::verify()` avec le nonce attendu.
6. Retourne `GoogleCallbackData`.

**Exemple :**

```php
try {
    $data = $service->callback(new GoogleCallbackRecord(
        code: $request->input('code'),
        state: $request->input('state'),
        error: $request->input('error'),
        error_description: $request->input('error_description'),
    ));

    $googleId = $data->claims->sub;
    $email    = $data->claims->email;
    // Créer/lier l'utilisateur local...
} catch (GoogleOAuthException $e) {
    if ($e->isProviderError()) {
        // Google a renvoyé une erreur (access_denied, etc.)
    } else {
        // Erreur de state, d'échange ou de vérification
    }
}
```

## Hooks (méthodes `protected`)

| Méthode | Signature | Description |
|---------|-----------|-------------|
| `beforeAuthorizationRedirect` | `(GoogleRedirectRecord): void` | Avant la construction de l'URL |
| `afterAuthorizationRedirect` | `(GoogleRedirectRecord, GoogleRedirectData): void` | Après la construction de l'URL |
| `beforeCallback` | `(GoogleCallbackRecord): void` | Avant le traitement du callback |
| `afterCallback` | `(GoogleCallbackRecord, GoogleCallbackData): void` | Après un callback réussi |
| `afterCallbackFailed` | `(GoogleCallbackRecord, ErrorCode): void` | Après un échec (une `ErrorCode` est passée) |

**Exemple d'extension :**

```php
final class LoggedGoogleOAuthService extends GoogleOAuthService
{
    protected function afterCallback(GoogleCallbackRecord $record, GoogleCallbackData $data): void
    {
        logger()->info('Google login success', [
            'sub'   => $data->claims->sub,
            'email' => $data->claims->email,
        ]);
    }

    protected function afterCallbackFailed(GoogleCallbackRecord $record, ErrorCode $error): void
    {
        logger()->warning('Google login failed', ['code' => $error->value]);
    }
}
```

## Cas d'utilisation

### Cas 1 : Redirection initiale

L'utilisateur clique sur « Se connecter avec Google ». Le backend génère l'URL et redirige.

```php
$data = $service->authorizationRedirect(new GoogleRedirectRecord(
    state: '',
    code_challenge: '',
    nonce: '',
));

return redirect()->away($data->url);
```

### Cas 2 : Traitement du callback

Google redirige l'utilisateur vers `/auth/google/callback` avec `code` et `state`.

```php
$data = $service->callback(new GoogleCallbackRecord(
    code: $request->query('code', ''),
    state: $request->query('state', ''),
    error: $request->query('error'),
    error_description: $request->query('error_description'),
));

// $data->claims->sub, $data->claims->email, $data->claims->emailVerified === true
// $data->accessToken, $data->idToken, $data->expiresIn
```

### Cas 3 : Gestion d'un refus utilisateur

L'utilisateur refuse l'accès. Google redirige avec `error=access_denied`.

```php
try {
    $service->callback($record);
} catch (GoogleOAuthException $e) {
    if ($e->isProviderError()) {
        return response()->json([
            'error'   => 'google_provider_error',
            'message' => $e->getMessage(),
        ], 400);
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
state = record.state ?: pkceGenerator.generateState()
nonce = record.nonce ?: pkceGenerator.generateNonce()
    ↓
stateStore.put(state, GoogleStateRecord{code_verifier, nonce, expires_at}, ttl)
    ↓
new GoogleAuthorizationRedirectRequest(...)
    ↓
GoogleRedirectData{url}
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
GoogleCallbackData{idToken, accessToken, expiresIn, tokenType, scope, claims}
    ↓
afterCallback(record, data)
```

## Gestion des erreurs

Toutes les erreurs sont levées via `GoogleOAuthException`. La méthode `isProviderError()` distingue les erreurs remontées par Google des erreurs internes.

| Situation | Exception | `isProviderError()` | Message |
|-----------|-----------|---------------------|---------|
| Google renvoie une erreur (`error=...`) | `providerError()` | `true` | `error_description` de Google |
| State absent du store | `stateNotFound()` | `false` | `Google OAuth state not found` |
| State expiré | `stateExpired()` | `false` | `Google OAuth state has expired` |
| Échange code échoue (4xx/5xx) | `RuntimeException` (via `ClientService`) | — | `HTTP request failed: ...` |
| ID token invalide | Plusieurs `idToken*()` | `false` | voir `GoogleIdTokenVerifier` |

**Note :** l'appel HTTP à Google peut lever une `RuntimeException` (gérée par `ClientService`). Elle n'est **pas** une `GoogleOAuthException`. L'appelant doit la gérer séparément.

## Intégration

```
GoogleRedirectAction / GoogleCallbackAction
        ↓
GoogleOAuthService
        ├── GooglePkceGenerator
        ├── GoogleStateStoreInterface (→ CacheService)
        ├── ClientService (→ Guzzle)
        ├── GoogleTokenExchangeResponse
        └── GoogleIdTokenVerifier
                └── ClientService (→ Guzzle, JWKS)
```

Le service dépend de cinq collaborateurs. Chacun peut être remplacé indépendamment via le container Laravel.

## Performance

- **Authorization Redirect** : très rapide (~1 ms) — génération aléatoire + 1 écriture cache.
- **Callback** : coût dominé par les **deux appels HTTP** :
  - `POST /token` à Google (~100-300 ms)
  - `GET /certs` au JWKS Google (~100-200 ms)
- **Pas de cache JWKS** dans l'implémentation actuelle. Pour une production à fort trafic, wrapper `GoogleIdTokenVerifier::resolvePublicKey()` avec un cache.

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

// → 'https://accounts.google.com/o/oauth2/v2/auth?client_id=...&...'
return redirect()->away($redirect->url);

// ============================================================
// 2. Callback (autre requête)
// ============================================================
try {
    $callback = $service->callback(new GoogleCallbackRecord(
        code: $request->query('code', ''),
        state: $request->query('state', ''),
        error: $request->query('error'),
        error_description: $request->query('error_description'),
    ));

    $claims = $callback->claims;

    // Créer ou lier l'utilisateur local
    $user = User::firstOrCreate(
        ['email' => $claims->email],
        [
            'name'              => $claims->name,
            'password'          => null,
            'email_verified_at' => now(),
        ],
    );

    // Générer le token de session (Nemesis, etc.)
    $token = $user->createToken('web')->plainTextToken;

    return response()->json([
        'user'  => $user,
        'token' => $token,
    ]);

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
- `GoogleOAuthException` — Exception unique, avec `isProviderError()`
- `GoogleAuthorizationRedirectRequest`, `GoogleTokenExchangeRequest` — Requêtes HTTP vers Google