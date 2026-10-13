# GooglePkceGenerator - Référence Technique

## Description

Génère les valeurs cryptographiques requises par le flux OAuth 2.0 + PKCE : `code_verifier`, `code_challenge` (SHA256 base64url), `state` (anti-CSRF) et `nonce` (anti-replay).

## Hiérarchie

```
GooglePkceGenerator
```

Classe `final`, sans parent ni interface. Utilise `random_bytes()` (CSPRNG PHP natif).

## Rôle principal

Fournit des valeurs **imprévisibles** et **URL-safe** pour sécuriser le flux d'autorisation Google. Chaque valeur est générée à partir d'une source cryptographiquement sûre (`random_bytes`), puis encodée en base64url (RFC 4648 §5).

- **PKCE** : protège contre l'interception du `code` d'autorisation.
- **State** : protège contre le CSRF sur le callback.
- **Nonce** : protège contre le rejeu de l'`id_token`.

## Installation

Aucun prérequis. Dépend uniquement de :

- `AuthenticationKitConfig` (pour `getGooglePkceVerifierLength()`)
- Extension PHP `hash` (native, toujours disponible)

## API / Méthodes publiques

### `__construct(AuthenticationKitConfig $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `AuthenticationKitConfig` | Fournit la longueur du verifier (`pkce_verifier_length`, défaut 64) |

**Exemple :**

```php
$generator = new GooglePkceGenerator(
    config: app(AuthenticationKitConfig::class),
);
```

---

### `generate(): array`

Génère une paire PKCE : `code_verifier` et son `code_challenge` SHA256 base64url.

**Retourne :** `array{verifier: string, challenge: string}`

- `verifier` : chaîne aléatoire base64url de longueur `pkce_verifier_length` octets (avant encodage).
- `challenge` : `base64url(sha256(verifier, raw=true))`.

**Exceptions :** Aucune. `random_bytes()` peut théoriquement lever `Exception` si la source d'entropie échoue (très rare en pratique).

**Exemple :**

```php
$pkce = $generator->generate();

// $pkce['verifier']  → 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk'
// $pkce['challenge'] → 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM'
```

**Détail du calcul du challenge :**

```php
$challenge = base64UrlEncode(
    hash('sha256', $verifier, binary: true)
);
```

Conforme à la RFC 7636 (S256).

---

### `generateState(): string`

Génère une valeur aléatoire utilisée comme `state` OAuth (protection CSRF).

**Retourne :** `string` — 32 octets aléatoires encodés en base64url (~43 caractères)

**Exemple :**

```php
$state = $generator->generateState();
// → 'zzRpnjPGjOeJdbjdV5alU-QnzvQU5QrAUWuM8-V0TIM'
```

---

### `generateNonce(): string`

Génère une valeur aléatoire utilisée comme `nonce` OpenID Connect (protection anti-rejeu).

**Retourne :** `string` — 32 octets aléatoires encodés en base64url (~43 caractères)

**Exemple :**

```php
$nonce = $generator->generateNonce();
// → 'iJzlhDvj-F6eTbs5LjsNrUB_x5Eu6jxqm2AMQeaCRRA'
```

## Cas d'utilisation

### Cas 1 : Préparation d'une redirection OAuth

Générer les trois valeurs avant de construire l'URL Google.

```php
$pkce  = $generator->generate();
$state = $generator->generateState();
$nonce = $generator->generateNonce();

// → Persister {code_verifier: $pkce['verifier'], nonce: $nonce} dans le state store
// → Utiliser $pkce['challenge'], $state, $nonce dans l'URL Google
```

### Cas 2 : Vérification de la conformité PKCE

S'assurer que le `code_challenge` correspond bien au hash SHA256 du verifier.

```php
$pkce = $generator->generate();

$expectedChallenge = rtrim(
    strtr(base64_encode(hash('sha256', $pkce['verifier'], true)), '+/', '-_'),
    '='
);

assert($pkce['challenge'] === $expectedChallenge);
```

## Flux d'exécution

```
generate()
    ↓
length = config.getGooglePkceVerifierLength()  // défaut 64
    ↓
verifier = base64url( random_bytes(length) )
    ↓
challenge = base64url( sha256(verifier, raw=true) )
    ↓
{ verifier, challenge }
```

```
generateState()
    ↓
base64url( random_bytes(32) )

generateNonce()
    ↓
base64url( random_bytes(32) )
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Source d'entropie indisponible | `Exception` | `random_bytes(): Could not gather sufficient random data` (rare) |
| `pkce_verifier_length` = 0 | Aucune | `random_bytes(0)` retourne `''` — verifier vide |

**Recommandation :** s'assurer que `pkce_verifier_length` est au moins égal à **32** (RFC 7636 préconise 43-128 caractères après encodage base64url).

## Intégration

Utilisé exclusivement par `GoogleOAuthService::authorizationRedirect()`.

```
GoogleOAuthService::authorizationRedirect()
    → GooglePkceGenerator::generate()
        → {verifier, challenge}
    → GooglePkceGenerator::generateState()
    → GooglePkceGenerator::generateNonce()
    → GoogleStateStoreInterface::put(state, GoogleStateRecord{verifier, nonce, expires_at}, ttl)
```

La classe ne persiste **rien**. Elle ne fait que produire des valeurs. Toute persistance est déléguée au state store.

## Performance

- **Complexité** : O(1) pour chaque méthode.
- **Coût cryptographique** : négligeable (~microsecondes).
- **Allocations** : une seule chaîne par appel.
- **Aucune E/S, aucun cache nécessaire.**

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ✅ Complet |

`random_bytes()` est disponible depuis PHP 7.0.

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Configs\AuthenticationKitConfig;
use AndyDefer\AuthenticationKit\Google\Services\GooglePkceGenerator;

/** @var AuthenticationKitConfig $config */
$generator = new GooglePkceGenerator(config: $config);

// 1. Génération PKCE
$pkce = $generator->generate();
// → ['verifier' => '...', 'challenge' => '...']

// 2. Génération state + nonce
$state = $generator->generateState();
$nonce = $generator->generateNonce();

// 3. Vérification manuelle de la conformité S256
$recomputed = rtrim(
    strtr(base64_encode(hash('sha256', $pkce['verifier'], true)), '+/', '-_'),
    '='
);

var_dump($recomputed === $pkce['challenge']); // true

// 4. Les trois valeurs sont URL-safe (aucun caractère +, /, =)
var_dump(preg_match('/[+\/=]/', $pkce['verifier']) === 0);   // true
var_dump(preg_match('/[+\/=]/', $pkce['challenge']) === 0);  // true
var_dump(preg_match('/[+\/=]/', $state) === 0);              // true
var_dump(preg_match('/[+\/=]/', $nonce) === 0);              // true
```

## Détails d'implémentation

### Encodage base64url

```php
private function base64UrlEncode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}
```

Conforme à la **RFC 4648 §5** :

- `+` → `-`
- `/` → `_`
- Suppression du padding `=` final.

Ces valeurs peuvent être utilisées **directement** dans une query string ou un JWT sans échappement supplémentaire.

### Longueur des valeurs

| Valeur | Source | Longueur brute | Longueur base64url |
|--------|--------|----------------|--------------------|
| `verifier` | `random_bytes(pkce_verifier_length)` (défaut 64) | 64 octets | ~86 caractères |
| `challenge` | `sha256(verifier)` | 32 octets | 43 caractères |
| `state` | `random_bytes(32)` | 32 octets | 43 caractères |
| `nonce` | `random_bytes(32)` | 32 octets | 43 caractères |

## Voir aussi

- `GoogleOAuthService` — Consommateur principal
- `GoogleStateStoreInterface` — Persistance de `code_verifier` + `nonce`
- `AuthenticationKitConfig` — Fournit `pkce_verifier_length`
- RFC 7636 — Proof Key for Code Exchange (PKCE)
- RFC 4648 §5 — Base64url Encoding