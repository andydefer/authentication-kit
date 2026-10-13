# GoogleIdTokenVerifier - Référence Technique

## Description

Vérifie un ID token Google (JWT signé RS256) : signature cryptographique via JWKS, puis validation des claims (`iss`, `aud`, `exp`, `iat`, `nonce`, `email_verified`).

## Hiérarchie

```
GoogleIdTokenVerifier
```

Aucune interface, aucun parent. Classe non-`final` pour permettre le mocking.

## Rôle principal

Dans le flux OAuth 2.0 / OpenID Connect, après l'échange du code contre les tokens Google, cette classe valide que l'`id_token` a bien été émis par Google pour **cette** application, qu'il n'est pas expiré et que l'email est vérifié. Elle retourne les claims sous forme de `GoogleIdTokenClaimsData`.

## Installation

Aucun prérequis spécifique. Dépend de :

- `andydefer/php-client` (`ClientService`)
- `AuthenticationKitConfig` (client_id, endpoint JWKS)
- Extension PHP `openssl` (obligatoire)

## API / Méthodes publiques

### `__construct(ClientService $client, AuthenticationKitConfig $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$client` | `ClientService` | Client HTTP pour récupérer le JWKS Google |
| `$config` | `AuthenticationKitConfig` | Configuration (client_id, JWKS endpoint) |

**Exemple :**

```php
$verifier = new GoogleIdTokenVerifier(
    client: app(ClientService::class),
    config: app(AuthenticationKitConfig::class),
);
```

---

### `verify(string $idToken, ?string $expectedNonce = null): GoogleIdTokenClaimsData`

Vérifie un ID token Google et retourne ses claims validés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$idToken` | `string` | Le JWT compact (`header.payload.signature`) |
| `$expectedNonce` | `?string` | Le nonce attendu (généré lors de la redirection). `null` pour ignorer la vérification |

**Retourne :** `GoogleIdTokenClaimsData` — claims validés (sub, email, emailVerified, iss, aud, exp, iat, nonce, name, givenName, familyName, picture, locale, hd)

**Exceptions :** `GoogleOAuthException` — voir tableau ci-dessous

**Exemple :**

```php
$claims = $verifier->verify(
    idToken: $idToken,
    expectedNonce: $storedNonce,
);

echo $claims->email;      // 'user@example.com'
echo $claims->sub;        // 'google-user-id'
echo $claims->emailVerified; // true
```

## Cas d'utilisation

### Cas 1 : Vérification dans le callback OAuth

Après l'échange du code contre les tokens Google, valider l'`id_token` avant de créer la session locale.

```php
$tokens = $response->getData();

$claims = $idTokenVerifier->verify(
    idToken: $tokens->idToken,
    expectedNonce: $stateRecord->nonce,
);

// À ce stade : $claims est fiable
// L'utilisateur peut être créé/lie à partir de $claims->sub et $claims->email
```

### Cas 2 : Vérification sans nonce

Pour des flux où le nonce n'est pas utilisé (déconseillé pour OIDC).

```php
$claims = $idTokenVerifier->verify(
    idToken: $idToken,
    expectedNonce: null,
);
```

## Flux d'exécution

```
verify(idToken, expectedNonce)
    ↓
1. explode('.') → 3 parties ?
    ↓
2. base64UrlDecode(header) + json_decode
    ↓
3. base64UrlDecode(payload) + json_decode
    ↓
4. base64UrlDecode(signature)
    ↓
5. Check alg === 'RS256' && kid !== ''
    ↓
6. resolvePublicKey(kid)
    ├── GET JWKS Google
    ├── find key by kid
    └── build RSA public key from n, e
    ↓
7. openssl_verify(header.payload, signature, publicKey, SHA256)
    ↓
8. verifyClaims(payload, expectedNonce)
    ├── iss ∈ ALLOWED_ISSUERS
    ├── aud === config.client_id
    ├── exp > now
    ├── iat ≤ now + 60
    ├── nonce match (si attendu)
    └── email_verified === true
    ↓
9. hydrateClaims(payload) → GoogleIdTokenClaimsData
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Token non-JWT (pas 3 parties) | `GoogleOAuthException` | `Malformed Google ID token` |
| Header/payload non JSON | `GoogleOAuthException` | `Malformed Google ID token` |
| Signature base64 invalide | `GoogleOAuthException` | `Malformed Google ID token` |
| `alg` ≠ RS256 ou `kid` vide | `GoogleOAuthException` | `Malformed Google ID token` |
| `kid` absent du JWKS | `GoogleOAuthException` | `No matching public key found for Google ID token` |
| Modulus/exponent invalides | `GoogleOAuthException` | `No matching public key found for Google ID token` |
| Signature cryptographique invalide | `GoogleOAuthException` | `Google ID token signature is invalid` |
| `iss` non autorisé | `GoogleOAuthException` | `Google ID token issuer is invalid` |
| `aud` ≠ client_id | `GoogleOAuthException` | `Google ID token audience is invalid` |
| Token expiré | `GoogleOAuthException` | `Google ID token has expired` |
| `iat` dans le futur (>60s) | `GoogleOAuthException` | `Google ID token was issued in the future` |
| `nonce` ≠ attendu | `GoogleOAuthException` | `Google ID token nonce mismatch` |
| `email_verified` ≠ true | `GoogleOAuthException` | `Google email is not verified` |

Toutes ces exceptions sont des `GoogleOAuthException`. La méthode `isProviderError()` retourne `false` dans tous ces cas (ce sont des erreurs de vérification, pas des erreurs remontées par Google).

## Intégration

Utilisé par `GoogleOAuthService::callback()` après l'échange du code. C'est la **seule** source de vérité pour les claims Google dans le package.

```
GoogleOAuthService
    → GoogleIdTokenVerifier::verify()
        → ClientService::get(JWKS endpoint)
        → openssl_verify()
        → GoogleIdTokenClaimsData
```

Le `ClientService` est partagé avec `GoogleOAuthService`, donc la même instance Guzzle est réutilisée.

## Performance

- **JWKS** : appel HTTP à `https://www.googleapis.com/oauth2/v3/certs` à chaque `verify()`. Google met ces clés en cache avec `Cache-Control`, mais **cette classe ne cache pas**. Pour une production à fort trafic, envisager un cache (Laravel `Cache::remember`) sur `resolvePublicKey()`.
- **Signature** : `openssl_verify` est rapide (~0,1 ms pour RSA 2048).
- **Complexité** : O(1) hors appel réseau. L'appel JWKS est O(n) où n = nombre de clés (typiquement 2-3).

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ⚠️ `readonly` properties OK, mais `\OpenSSLAsymmetricKey` disponible depuis 8.0 |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Services\GoogleIdTokenVerifier;
use AndyDefer\PhpClient\Clients\ClientService;

$verifier = new GoogleIdTokenVerifier(
    client: app(ClientService::class),
    config: app(AuthenticationKitConfig::class),
);

try {
    $claims = $verifier->verify(
        idToken: $idTokenFromGoogle,
        expectedNonce: $nonceStoredInState,
    );

    // Utilisation des claims vérifiés
    $googleId     = $claims->sub;
    $email        = $claims->email;
    $emailChecked = $claims->emailVerified;
    $fullName     = $claims->name;
    $picture      = $claims->picture;

    // À ce stade, on peut créer/lier l'utilisateur local en confiance
} catch (GoogleOAuthException $e) {
    if ($e->isProviderError()) {
        // Erreur remontée par Google (access_denied, etc.)
    } else {
        // Erreur de vérification (signature, claims, etc.)
    }

    logger()->warning('Google ID token verification failed', [
        'reason' => $e->getMessage(),
    ]);
}
```

## Voir aussi

- `GoogleOAuthService` — Orchestre le flux OAuth complet et appelle ce vérificateur
- `GoogleOAuthException` — Exception levée pour toutes les erreurs de vérification
- `GoogleIdTokenClaimsData` — DTO retourné par `verify()`
- `GoogleJwksResponse` — Response qui parse le JWKS Google
- `EmptyRequest` — Request utilisé pour l'appel GET au JWKS