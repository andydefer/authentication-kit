# GoogleStateStore - Référence Technique

## Description

Implémentation du contrat `GoogleStateStoreInterface` basée sur `CacheServiceInterface`. Persiste et consomme les `GoogleStateRecord` (code_verifier, nonce, expiration) associés à un `state` OAuth.

## Hiérarchie

```
GoogleStateStoreInterface
    └── GoogleStateStore
```

Classe `final`, sans parent. Peut être remplacée via binding container.

## Rôle principal

Dans le flux OAuth Google, le `code_verifier` PKCE et le `nonce` OpenID Connect générés lors de la **redirection** doivent être retrouvés lors du **callback**. Le `state` (valeur aléatoire URL-safe) sert de clé.

Ce store :

1. **Persiste** ces valeurs entre les deux requêtes HTTP (redirection / callback).
2. **Consomme** la valeur lors du `pull` — **usage unique** (anti-replay).
3. **Délègue** l'implémentation du stockage au `CacheServiceInterface` (cache Laravel sous-jacent).

## Installation

Aucun prérequis. Dépend de :

- `andydefer/laravel-actions` (pour `CacheServiceInterface`)
- Un driver de cache Laravel configuré (Redis, Memcached, File, Array…)

## API / Méthodes publiques

### `__construct(CacheServiceInterface $cache)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$cache` | `CacheServiceInterface` | Implémentation concrète du cache (typiquement `CacheService`) |

**Exemple :**

```php
$store = new GoogleStateStore(
    cache: app(CacheServiceInterface::class),
);
```

---

### `put(string $state, GoogleStateRecord $record, int $ttlSeconds): void`

Persiste un state record associé à la clé `state`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$state` | `string` | La valeur de `state` OAuth (clé logique) |
| `$record` | `GoogleStateRecord` | Contient `code_verifier`, `nonce`, `expires_at` |
| `$ttlSeconds` | `int` | Durée de vie en secondes (`CacheService` clampe à 1 minimum) |

**Retourne :** `void`

**Exceptions :** Aucune (délégué au `CacheService` — peut lever si le driver cache échoue).

**Exemple :**

```php
$store->put('state-abc', new GoogleStateRecord(
    code_verifier: 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk',
    nonce: 'iJzlhDvj-F6eTbs5LjsNrUB_x5Eu6jxqm2AMQeaCRRA',
    expires_at: time() + 600,
), ttlSeconds: 600);
```

---

### `pull(string $state): ?GoogleStateRecord`

Récupère et **supprime** le state record associé à `state`. Usage unique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$state` | `string` | La valeur de `state` OAuth à chercher |

**Retourne :** `GoogleStateRecord|null` — Le record s'il existe, `null` sinon (ou si déjà consommé).

**Comportement :**

1. Vérifie l'existence via `CacheService::has()`.
2. Si absent → retourne `null` (sans toucher au cache).
3. Si présent → `CacheService::get()` pour hydrater le `GoogleStateRecord`.
4. `CacheService::forget()` pour supprimer la clé.
5. Retourne le record (ou `null` si l'hydratation a échoué).

**Exceptions :** Aucune.

**Exemple :**

```php
$record = $store->pull('state-abc');

if ($record === null) {
    // State inconnu ou déjà consommé → CSRF probable
    throw GoogleOAuthException::stateNotFound();
}

// $record->code_verifier, $record->nonce, $record->expires_at
```

## Cas d'utilisation

### Cas 1 : Persistance lors de la redirection

```php
$pkce  = $pkceGenerator->generate();
$nonce = $pkceGenerator->generateNonce();

$store->put(
    state: $state,
    record: new GoogleStateRecord(
        code_verifier: $pkce['verifier'],
        nonce: $nonce,
        expires_at: time() + 600,
    ),
    ttlSeconds: 600,
);

// → L'utilisateur est redirigé vers Google avec $state, $pkce['challenge'], $nonce
```

### Cas 2 : Consommation lors du callback

```php
$record = $store->pull($incomingState);

if ($record === null) {
    throw GoogleOAuthException::stateNotFound();
}

// → Utiliser $record->code_verifier pour l'échange de code
// → Utiliser $record->nonce pour la vérification de l'ID token
// → Le state est maintenant supprimé : rejouer la requête échouera
```

## Flux d'exécution

### put()

```
put(state, record, ttl)
    ↓
cache.put('auth_kit.google.state.' + state, record, ttl)
    ↓
(void)
```

### pull()

```
pull(state)
    ↓
key = 'auth_kit.google.state.' + state
    ↓
cache.has(key) ?
    ├── non  → return null
    └── oui  ↓
        cache.get(key, GoogleStateRecord::class)
            ↓
        cache.forget(key)
            ↓
        return record (ou null si hydratation échouée)
```

## Gestion des erreurs

| Situation | Retour | Note |
|-----------|--------|------|
| `state` inconnu | `null` | Cas normal après un state déjà consommé ou inexistant |
| `state` déjà consommé | `null` | Protection anti-replay |
| Driver cache indisponible | Exception remontée | Levée par le `CacheService` sous-jacent |
| `GoogleStateRecord` non hydratable | `null` | Le cache contient une donnée corrompue |

## Intégration

```
GoogleOAuthService
    ├── authorizationRedirect()
    │       └── GoogleStateStore::put(state, record, ttl)
    │
    └── callback()
            └── GoogleStateStore::pull(state)
                    ├── CacheService::has()
                    ├── CacheService::get()
                    └── CacheService::forget()
```

`GoogleStateStore` est enregistré dans le container sous `GoogleStateStoreInterface::class`, donc injecté automatiquement dans `GoogleOAuthService`.

## Détails d'implémentation

### Préfixe de clé

```php
private const CACHE_PREFIX = 'auth_kit.google.state.';
```

Toutes les clés sont préfixées pour éviter les collisions avec d'autres entrées de cache. Le préfixe complet est : `auth_kit.google.state.{state}`.

### Pourquoi `has` avant `get` ?

Le `CacheService` (package `laravel-actions`) exige que la valeur cachée implémente `Transformable`. Le `GoogleStateRecord` en hérite via `AbstractRecord`. La méthode `get()` retourne `null` si la clé est absente **ou** si la classe hydratée ne correspond pas. Le `has()` préalable évite un `get()` inutile et clarifie l'intention.

### Consommation atomique ?

**Non.** Entre le `has()` et le `forget()`, une fenêtre existe. Dans un contexte concurrent, deux requêtes avec le **même** state pourraient théoriquement réussir toutes les deux. En pratique :

- Le state est unique par flux OAuth.
- Un utilisateur ne rejoue pas le callback en parallèle.
- Le risque réel est nul pour un flux OAuth standard.

Pour une garantie stricte, utiliser `Cache::pull()` (atomique) — mais incompatible avec `CacheServiceInterface` actuel. Amélioration possible si nécessaire.

## Performance

- **Complexité** : O(1) par opération.
- **Coût réseau** : dépend du driver (Redis ≈ 0,1 ms ; File ≈ 1 ms ; Array ≈ 0 ms).
- **3 opérations** par `pull()` (has, get, forget) au lieu d'1 atomique. Sur un driver réseau, cela triple le round-trip.
- **Optimisation possible** : si `CacheService` expose un `pull()` atomique, l'utiliser directement.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ✅ Complet |

Dépend du support de `CacheServiceInterface` (package `andydefer/laravel-actions`).

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Actions\Contracts\CacheServiceInterface;
use AndyDefer\AuthenticationKit\Google\Records\GoogleStateRecord;
use AndyDefer\AuthenticationKit\Google\Services\GoogleStateStore;

/** @var CacheServiceInterface $cache */
$store = new GoogleStateStore(cache: $cache);

// ============================================================
// 1. Persistance (redirection)
// ============================================================
$store->put(
    state: 'abc123',
    record: new GoogleStateRecord(
        code_verifier: 'verifier-xyz',
        nonce: 'nonce-xyz',
        expires_at: time() + 600,
    ),
    ttlSeconds: 600,
);

// ============================================================
// 2. Consommation (callback) — première fois
// ============================================================
$record = $store->pull('abc123');

var_dump($record?->code_verifier); // 'verifier-xyz'
var_dump($record?->nonce);         // 'nonce-xyz'

// ============================================================
// 3. Consommation — deuxième fois (rejeu)
// ============================================================
$replay = $store->pull('abc123');

var_dump($replay); // null → le state a été consommé

// ============================================================
// 4. State inconnu
// ============================================================
var_dump($store->pull('unknown')); // null
```

## Voir aussi

- `GoogleStateStoreInterface` — Contrat implémenté
- `GoogleStateRecord` — Record persisté (`code_verifier`, `nonce`, `expires_at`)
- `CacheServiceInterface` — Abstraction du cache sous-jacent
- `GoogleOAuthService` — Consommateur principal
- `GooglePkceGenerator` — Génère les valeurs stockées