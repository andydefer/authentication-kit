# GoogleRedirectAction - Référence Technique

## Description

Action ADR qui génère l'URL de redirection Google pour initier le flux OAuth 2.0 + PKCE. Elle délègue au `GoogleOAuthService`, transforme les erreurs en réponses HTTP standardisées, et retourne l'URL sous forme JSON.

## Hiérarchie

```
AbstractAction
    └── GoogleRedirectAction
```

Classe `final`. Utilise le cycle de vie `before → handle → after` d'`AbstractAction`.

## Rôle principal

Reçoit un `GoogleRedirectRecord` (state, code_challenge, nonce optionnels), appelle `GoogleOAuthService::authorizationRedirect()`, et retourne l'URL Google en JSON. C'est la **couche HTTP** d'initialisation du flux.

L'Action ne contient **aucune logique métier** : elle orchestre et adapte les erreurs.

## Installation

Enregistrée dans `GoogleServiceProvider` sous son FQCN pour être résolue via `action_route()`.

Route typique :

```php
Route::post('/api/google-redirect', action_route(
    GoogleRedirectRequest::class,
    GoogleRedirectAction::class,
));
```

## API / Méthodes publiques

### `__construct(GoogleOAuthInterface $googleOAuth)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$googleOAuth` | `GoogleOAuthInterface` | Service qui exécute la logique OAuth |

**Note :** l'Action dépend de l'**interface** `GoogleOAuthInterface` (et non de `GoogleOAuthService` concret), ce qui facilite le mocking et le remplacement.

**Exemple :**

```php
$action = new GoogleRedirectAction(
    googleOAuth: app(GoogleOAuthInterface::class),
);
```

---

### `run(AbstractRecord $record): ResponseFactory`

Point d'entrée hérité d'`AbstractAction`. Exécute `before()`, `handle()`, puis `after()`.

---

### `before(AbstractRecord $record): void` (protected)

Vérifie que le record est bien un `GoogleRedirectRecord`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Le record à valider |

**Exceptions :** `InvalidArgumentException` — si `$record` n'est pas un `GoogleRedirectRecord`.

**Exemple :**

```php
// Ne devrait jamais arriver via action_route(), mais protège contre un mauvais usage direct
$action->run(new SomeOtherRecord()); // → InvalidArgumentException
```

---

### `handle(AbstractRecord $record): ResponseFactory` (protected)

Génère l'URL Google et retourne la réponse HTTP.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Doit être un `GoogleRedirectRecord` |

**Retourne :** `ResponseFactory` — toujours, quelle que soit l'issue.

| Cas | Status HTTP | ErrorCode | Corps |
|-----|-------------|-----------|-------|
| Succès | 200 | — | `GoogleRedirectData` (JSON : `{"url": "..."}`) |
| Record invalide | 500 | `INVALID_RECORD_TYPE` | Erreur standardisée |
| Erreur technique | 500 | `GOOGLE_REDIRECT_ERROR` | Erreur standardisée |

**Exemple :**

```php
$response = $action->run(new GoogleRedirectRecord(
    state: '',
    code_challenge: '',
    nonce: '',
));
```

## État interne

L'Action maintient une propriété mutable `private ?ErrorCode $error = null;` utilisée pour tracker le dernier type d'erreur. Cette propriété n'est **pas exploitée** dans le code actuel (pas de hook `after()` défini), mais peut servir pour du logging ou du monitoring.

## Cas d'utilisation

### Cas 1 : Initier le flux OAuth

Le frontend demande l'URL Google pour rediriger l'utilisateur.

```php
// POST /api/google-redirect
// Payload vide ou avec state/code_challenge/nonce pré-générés

// Réponse 200 :
// {
//     "url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=xxx.apps.googleusercontent.com&redirect_uri=https%3A%2F%2Fapp.test%2Fauth%2Fgoogle%2Fcallback&response_type=code&scope=openid%20email%20profile&state=zzRpnjPGjOeJdbjdV5alU-QnzvQU5QrAUWuM8-V0TIM&code_challenge=VibvCVqH41Ryvsn9LeUrYUiwkz1XGIBTzaoKyfIJiTQ&code_challenge_method=S256&nonce=iJzlhDvj-F6eTbs5LjsNrUB_x5Eu6jxqm2AMQeaCRRA"
// }
```

### Cas 2 : State pré-fourni

Un client peut fournir son propre state (par exemple pour le tracker côté frontend).

```php
// POST /api/google-redirect
// { "state": "my-custom-state", "nonce": "", "code_challenge": "" }

// Réponse 200 : l'URL contient state=my-custom-state
```

### Cas 3 : Erreur technique

Le state store (cache) est indisponible, empêchant la persistance du PKCE.

```php
// Réponse 500 :
// {
//     "message": "Cache driver unavailable",
//     "status": 500,
//     "errorCode": "GOOGLE_REDIRECT_ERROR",
//     "errors": null
// }
```

## Flux d'exécution

```
action_route(GoogleRedirectRequest, GoogleRedirectAction)
    ↓
GoogleRedirectRequest::getRecord() → GoogleRedirectRecord
    ↓
GoogleRedirectAction::run(record)
    ↓
before(record)
    └── instanceof GoogleRedirectRecord ? ✓
    ↓
handle(record)
    ├── instanceof GoogleRedirectRecord ?
    │     └── non → error = INVALID_RECORD_TYPE → ResponseFactory(500)
    │
    ├── try: googleOAuth.authorizationRedirect(record) → GoogleRedirectData
    │     └── ResponseFactory::json(data, 200)
    │
    └── catch Exception → error = GOOGLE_REDIRECT_ERROR → ResponseFactory(500)
    ↓
after(success, error, record)  // hook libre (non défini ici)
    ↓
ResponseFactory
```

## Gestion des erreurs

| Situation | ErrorCode | Status HTTP | Message |
|-----------|-----------|-------------|---------|
| Record de mauvais type | `INVALID_RECORD_TYPE` | 500 | `Invalid record type` |
| Erreur technique (cache, config, PKCE) | `GOOGLE_REDIRECT_ERROR` | 500 | Message de l'exception |
| Config Google manquante | `GOOGLE_REDIRECT_ERROR` | 500 | `An error occurred while building the Google authorization URL` |

**Note :** l'Action ne distingue **pas** les sous-types d'erreur — toute exception tombe sous `GOOGLE_REDIRECT_ERROR` (500). Aucun cas de 400 n'est retourné.

## Intégration

```
Route::post('/api/google-redirect', action_route(...))
    ↓
GoogleRedirectRequest  → GoogleRedirectRecord
    ↓
GoogleRedirectAction
    ↓
GoogleOAuthInterface::authorizationRedirect()
    ↓
GoogleRedirectData { url }
```

L'Action est enregistrée comme singleton dans `GoogleServiceProvider`. Elle dépend de l'interface `GoogleOAuthInterface`, résolue vers `GoogleOAuthService` par le container.

## Performance

- **Complexité** : O(1) + coût du `GoogleOAuthService::authorizationRedirect()` (~1 ms : génération aléatoire + 1 écriture cache).
- **Overhead de l'Action** : négligeable (~0,05 ms).
- **Aucun appel HTTP sortant** : c'est le callback qui effectue les appels réseau.
- **Aucun cache** dans l'Action elle-même.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ✅ Complet |

Dépend de `andydefer/laravel-actions` (`AbstractAction`, `ResponseFactory`).

## Exemple complet

### Enregistrement de la route

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Actions\GoogleRedirectAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleRedirectRequest;
use Illuminate\Support\Facades\Route;

use function action_route;

Route::post('/api/google-redirect', action_route(
    GoogleRedirectRequest::class,
    GoogleRedirectAction::class,
))->name('api.google-redirect');
```

### Requête / Réponse

```bash
# Requête minimale
curl -X POST "https://app.test/api/google-redirect" \
     -H "Content-Type: application/json" \
     -d '{}'

# HTTP 200
{
    "url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=xxx.apps.googleusercontent.com&redirect_uri=...&response_type=code&scope=openid%20email%20profile&state=...&code_challenge=...&code_challenge_method=S256&nonce=..."
}
```

```bash
# Requête avec state personnalisé
curl -X POST "https://app.test/api/google-redirect" \
     -H "Content-Type: application/json" \
     -d '{"state": "my-tracking-id", "nonce": "", "code_challenge": ""}'

# HTTP 200 — l'URL contient state=my-tracking-id
{
    "url": "https://accounts.google.com/o/oauth2/v2/auth?...&state=my-tracking-id&..."
}
```

### Consommation côté frontend

```js
// 1. Demander l'URL de redirection
const response = await fetch('/api/google-redirect', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({}),
});

const { url } = await response.json();

// 2. Rediriger l'utilisateur vers Google
window.location.href = url;

// 3. Google redirigera vers /api/google-callback après consentement
```

### Utilisation du state personnalisé

```js
// Le state peut servir à tracker la provenance
const state = crypto.randomUUID();

await fetch('/api/google-redirect', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ state, nonce: '', code_challenge: '' }),
});

// Le state reviendra dans le callback : /api/google-callback?code=...&state={votre-state}
```

## Voir aussi

- `GoogleOAuthInterface` — Contrat utilisé par l'Action
- `GoogleOAuthService` — Implémentation exécutée
- `GoogleRedirectRequest` — Transforme la requête HTTP en `GoogleRedirectRecord`
- `GoogleRedirectRecord` — Record d'entrée
- `GoogleRedirectData` — Data de sortie (retourné en JSON)
- `ErrorCode::GOOGLE_REDIRECT_ERROR` — 500, erreurs techniques
- `ErrorCode::INVALID_RECORD_TYPE` — 500, mauvais type de record
- `AbstractAction` — Cycle de vie `before → handle → after`