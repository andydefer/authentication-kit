# GoogleIdTokenVerifier - Référence Technique

## Description

Service qui vérifie cryptographiquement un ID token Google (JWT RS256) contre le JWKS officiel de Google, valide les claims standards (`iss`, `aud`, `exp`, `iat`, `nonce`, `email_verified`) et retourne un Value Object immuable `GoogleIdTokenClaimsVO`.

## Hiérarchie / Implémentations

```
GoogleIdTokenVerifier
```

Pas d'interface — classe concrète. Dépendances injectées :

- `ClientService` — client HTTP pour récupérer le JWKS Google.
- `AuthenticationKitConfig` — configuration Google (`client_id`, `jwks_endpoint`).

## Rôle principal

Ce service est le garant cryptographique du flow Google. Il :

1. **Parse** le JWT (header.payload.signature) et vérifie la structure.
2. **Résout la clé publique** RSA correspondant au `kid` du header depuis le JWKS Google (`https://www.googleapis.com/oauth2/v3/certs` par défaut).
3. **Vérifie la signature** RS256 via `openssl_verify`.
4. **Valide les claims** :
   - `iss` ∈ {`accounts.google.com`, `https://accounts.google.com`}
   - `aud` === `client_id` configuré
   - `exp` > maintenant
   - `iat` ≤ maintenant + 60 s (tolérance d'horloge)
   - `nonce` === valeur attendue (si fournie)
   - `email_verified` === `true`
5. **Retourne** un `GoogleIdTokenClaimsVO` avec tous les claims utiles (sub, email, name, picture, locale, hd…).

Il est utilisé par `GoogleOAuthService` dans les deux flows (web et Android) pour produire les VO consommés par les hooks `onCallbackSuccess` / `onAndroidCallbackSuccess`.

## Installation

Enregistré en singleton par `GoogleServiceProvider` :

```php
$this->app->singleton(
    abstract: GoogleIdTokenVerifier::class,
    concrete: function ($app): GoogleIdTokenVerifier {
        return new GoogleIdTokenVerifier(
            client: $app->make(ClientService::class),
            config: $app->make(AuthenticationKitConfig::class),
        );
    }
);
```

## API / Méthodes publiques

### `verify(string $idToken, ?string $expectedNonce = null): GoogleIdTokenClaimsVO`

Vérifie le token Google et retourne les claims validés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$idToken` | `string` | Le JWT brut fourni par Google (Credential Manager ou échange de code). |
| `$expectedNonce` | `?string` | Nonce attendu (flow web). `null` pour le flow Android. |

**Retourne :** `GoogleIdTokenClaimsVO` — claims validés (sub, email, emailVerified, iss, aud, exp, iat, nonce, name, givenName, familyName, picture, locale, hd).

**Exceptions :**

| Exception | Cas |
|-----------|-----|
| `GoogleOAuthException::idTokenMalformed()` | JWT mal formé (≠ 3 segments, JSON invalide, `alg ≠ RS256`, `kid` absent). |
| `GoogleOAuthException::idTokenSignatureInvalid()` | Signature RSA invalide. |
| `GoogleOAuthException::idTokenUnknownKey()` | `kid` absent du JWKS Google, ou clé publique inexploitable. |
| `GoogleOAuthException::idTokenIssuerInvalid()` | `iss` hors liste blanche. |
| `GoogleOAuthException::idTokenAudienceInvalid()` | `aud` ≠ `client_id`. |
| `GoogleOAuthException::idTokenExpired()` | `exp` ≤ maintenant. |
| `GoogleOAuthException::idTokenIssuedInFuture()` | `iat` > maintenant + 60 s. |
| `GoogleOAuthException::idTokenNonceMismatch()` | `nonce` ≠ `$expectedNonce` (comparaison `hash_equals`). |
| `GoogleOAuthException::emailNotVerified()` | `email_verified` ≠ `true`. |

**Exemple :**

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Services\GoogleIdTokenVerifier;

/** @var GoogleIdTokenVerifier $verifier */
$claims = $verifier->verify(
    idToken: 'eyJhbGciOiJSUzI1NiIs...',
    expectedNonce: 'session-nonce-xyz',
);

echo $claims->getEmail();           // user@example.com
echo $claims->getSub();             // Google user id
var_dump($claims->isEmailVerified()); // true
```

## Cas d'utilisation

### Cas 1 : Vérification dans le flow web (avec nonce)

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Services\GoogleIdTokenVerifier;

/** @var GoogleIdTokenVerifier $verifier */
$claims = $verifier->verify(
    idToken: $googleResponse->idToken,
    expectedNonce: $stateRecord->nonce,
);
```

Le nonce est émis lors de l'`authorizationRedirect` et stocké dans `GoogleStateRecord`. Sa vérification empêche le rejeu d'un ID token intercepté.

### Cas 2 : Vérification dans le flow Android (sans nonce)

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Services\GoogleIdTokenVerifier;

/** @var GoogleIdTokenVerifier $verifier */
$claims = $verifier->verify(
    idToken: $record->id_token,
    expectedNonce: null,
);
```

Credential Manager ne fournit pas de nonce de session serveur, la vérification se concentre sur la signature, `aud`, `exp`, `iat` et `email_verified`.

### Cas 3 : Récupération d'une clé publique dans le JWKS

```php
<?php

declare(strict_types=1);

// Interne : resolvePublicKey($kid) itère sur $response->getKeys()
// et reconstruit la clé PEM à partir des paramètres n et e de la JWK.
//
// Exemple de JWK Google :
// {
//     "kty": "RSA",
//     "alg": "RS256",
//     "use": "sig",
//     "kid": "a1b2c3...",
//     "n": "0vx7agoebGcQ...",
//     "e": "AQAB"
// }
```

Le verifier convertit `n` et `e` en un `SubjectPublicKeyInfo` DER (OID RS256 + BIT STRING), l'encode en PEM, puis `openssl_pkey_get_public()` produit une `\OpenSSLAsymmetricKey` utilisée par `openssl_verify()`.

### Cas 4 : Claims optionnels exposés

```php
<?php

declare(strict_types=1);

$claims = $verifier->verify($idToken);

$claims->getName();       // "John Doe" (ou null si absent)
$claims->getGivenName();  // "John"
$claims->getFamilyName(); // "Doe"
$claims->getPicture();    // URL de la photo de profil
$claims->getLocale();     // "en"
$claims->getHd();         // "example.com" (Google Workspace domain)
```

## Flux d'exécution

```
verify($idToken, $expectedNonce)
    ↓
explode('.', $idToken) → 3 segments ? ── non ──→ idTokenMalformed
    ↓ oui
decodeJson(header) / decodeJson(payload) ── invalid ──→ idTokenMalformed
    ↓
header.alg === 'RS256' && header.kid ≠ '' ? ── non ──→ idTokenMalformed
    ↓ oui
resolvePublicKey(kid)
    ↓
GET {jwks_endpoint} via ClientService → GoogleJwksResponse
    ↓
clé avec kid trouvée ? ── non ──→ idTokenUnknownKey
    ↓ oui
buildRsaPublicKey(n, e) → PEM → OpenSSLAsymmetricKey
    ↓
openssl_verify(header.payload, signature, publicKey, SHA256) ── ≠ 1 ──→ idTokenSignatureInvalid
    ↓ = 1
verifyClaims(payload, expectedNonce)
    ├── iss ∉ liste blanche      → idTokenIssuerInvalid
    ├── aud ≠ client_id          → idTokenAudienceInvalid
    ├── exp ≤ maintenant         → idTokenExpired
    ├── iat > maintenant + 60    → idTokenIssuedInFuture
    ├── nonce ≠ attendu          → idTokenNonceMismatch
    └── email_verified ≠ true    → emailNotVerified
    ↓ tout OK
hydrateClaims(payload) → GoogleIdTokenClaimsVO
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| JWT non conforme | `GoogleOAuthException` | `Malformed Google ID token` |
| Algorithme ≠ RS256 | `GoogleOAuthException` | `Malformed Google ID token` |
| `kid` manquant | `GoogleOAuthException` | `Malformed Google ID token` |
| `kid` absent du JWKS | `GoogleOAuthException` | `No matching public key found for Google ID token` |
| Signature invalide | `GoogleOAuthException` | `Google ID token signature is invalid` |
| `iss` invalide | `GoogleOAuthException` | `Google ID token issuer is invalid` |
| `aud` invalide | `GoogleOAuthException` | `Google ID token audience is invalid` |
| Token expiré | `GoogleOAuthException` | `Google ID token has expired` |
| `iat` dans le futur | `GoogleOAuthException` | `Google ID token was issued in the future` |
| Nonce mismatch | `GoogleOAuthException` | `Google ID token nonce mismatch` |
| Email non vérifié | `GoogleOAuthException` | `Google email is not verified` |

Toutes les exceptions sont **spécifiques** — le code appelant peut distinguer chaque cas, et les actions convertissent la `GoogleOAuthException` en `ErrorResponseData` normalisée (voir `GoogleCallbackAction` / `GoogleAndroidCallbackAction`).

## Intégration

```
GoogleOAuthService::callback()          GoogleOAuthService::androidCallback()
        ↓                                        ↓
                GoogleIdTokenVerifier::verify()
                        ↓
            ┌───────────────────────┐
            │  ClientService        │
            │  GET {jwks_endpoint}  │
            └───────────────────────┘
                        ↓
                GoogleIdTokenClaimsVO
                        ↓
            GoogleCallbackVO / GoogleAndroidCallbackVO
                        ↓
            onCallbackSuccess() / onAndroidCallbackSuccess()
```

Le verifier est **injecté** dans `GoogleOAuthService` par le provider, ce qui permet de le substituer par un mock dans les tests (`GoogleOAuthServiceTest`).

## Performance

- **Une seule requête HTTP** par appel à `verify()` (JWKS endpoint).
- **Pas de cache interne** : le JWKS est récupéré à chaque vérification. Pour un trafic élevé, décorer le service ou injecter un `ClientService` avec cache HTTP (Laravel `Http::cache()` ou un `CachePlugin` Guzzle).
- Complexité **O(1)** par rapport au nombre de clés dans le JWKS (itération linéaire courte, quelques clés maximum).
- La reconstruction PEM est **O(log n)** en longueur du modulus ; négligeable.
- Aucune allocation superflue côté payload (parsing JSON direct).

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+    | ✅ Complet (`readonly`, `OpenSSLAsymmetricKey`, enums) |
| PHP 8.2+    | ✅ Complet |
| PHP 8.5     | ✅ Complet |

Nécessite l'extension **`openssl`** activée.

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Google\Exceptions\GoogleOAuthException;
use AndyDefer\AuthenticationKit\Google\Services\GoogleIdTokenVerifier;
use AndyDefer\PhpClient\Clients\ClientService;

/** @var ClientService $client */
/** @var AuthenticationKitConfig $config */

$verifier = new GoogleIdTokenVerifier(
    client: $client,
    config: $config,
);

try {
    // Flow web : nonce attendu
    $claims = $verifier->verify(
        idToken: 'eyJhbGciOiJSUzI1NiIs...',
        expectedNonce: 'session-nonce-xyz',
    );

    echo $claims->getEmail();       // user@example.com
    echo $claims->getSub();         // 108234...
    echo $claims->getName();        // John Doe
    echo $claims->getPicture();     // https://lh3.googleusercontent.com/...

} catch (GoogleOAuthException $e) {
    match (true) {
        $e->getMessage() === 'Malformed Google ID token'         => 'bad structure',
        $e->getMessage() === 'Google ID token signature is invalid' => 'forged token',
        $e->getMessage() === 'Google email is not verified'       => 'unverified email',
        default                                                   => 'other error',
    };
}
```

## Voir aussi

- [`GoogleIdTokenClaimsVO`](../ValueObjects/GoogleIdTokenClaimsVO.md) — Value Object retourné
- [`GoogleOAuthService`](./GoogleOAuthService.md) — utilise ce verifier
- [`GoogleOAuthException`](../Exceptions/GoogleOAuthException.md) — toutes les erreurs émises
- [`GoogleJwksResponse`](../Responses/GoogleJwksResponse.md) — DTO du JWKS
- [`AuthenticationKitConfig`](../../Configs/AuthenticationKitConfig.md) — `client_id` et `jwks_endpoint`
- [`ClientService`](../../../../php-client/Clients/ClientService.md) — client HTTP injecté
- [`ErrorCode`](../../Enums/ErrorCode.md) — codes d'erreur finaux
