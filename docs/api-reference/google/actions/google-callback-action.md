# GoogleCallbackAction - Référence Technique

## Description

Action ADR qui traite le retour de Google sur le endpoint callback OAuth. Elle délègue au `GoogleOAuthService`, transforme les exceptions en réponses HTTP standardisées, et retourne les données du callback sous forme JSON.

## Hiérarchie

```
AbstractAction
    └── GoogleCallbackAction
```

Classe `final`. Utilise le cycle de vie `before → handle → after` d'`AbstractAction`.

## Rôle principal

Reçoit un `GoogleCallbackRecord` (code, state, erreurs éventuelles), appelle `GoogleOAuthService::callback()`, et convertit le résultat en `ResponseFactory`. C'est la **couche HTTP** du callback OAuth.

L'Action ne contient **aucune logique métier** : elle orchestre et adapte les erreurs.

## Installation

Enregistrée dans `GoogleServiceProvider` sous son FQCN pour être résolue via `action_route()`.

Route typique :

```php
Route::get('/api/google-callback', action_route(
    GoogleCallbackRequest::class,
    GoogleCallbackAction::class,
));
```

## API / Méthodes publiques

### `__construct(GoogleOAuthService $googleOAuth)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$googleOAuth` | `GoogleOAuthService` | Service qui exécute la logique OAuth |

**Exemple :**

```php
$action = new GoogleCallbackAction(
    googleOAuth: app(GoogleOAuthService::class),
);
```

---

### `run(AbstractRecord $record): ResponseFactory`

Point d'entrée hérité d'`AbstractAction`. Exécute `before()`, `handle()`, puis `after()`.

---

### `before(AbstractRecord $record): void` (protected)

Vérifie que le record est bien un `GoogleCallbackRecord`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Le record à valider |

**Exceptions :** `InvalidArgumentException` — si `$record` n'est pas un `GoogleCallbackRecord`.

**Exemple :**

```php
// Ne devrait jamais arriver via action_route(), mais protège contre un mauvais usage direct
$action->run(new SomeOtherRecord()); // → InvalidArgumentException
```

---

### `handle(AbstractRecord $record): ResponseFactory` (protected)

Exécute le callback OAuth et retourne la réponse HTTP.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Doit être un `GoogleCallbackRecord` |

**Retourne :** `ResponseFactory` — toujours, quelle que soit l'issue.

| Cas | Status HTTP | ErrorCode | Corps |
|-----|-------------|-----------|-------|
| Succès | 200 | — | `GoogleCallbackData` (JSON) |
| Record invalide | 500 | `INVALID_RECORD_TYPE` | Erreur standardisée |
| Erreur Google (`access_denied`, etc.) | 400 | `GOOGLE_PROVIDER_ERROR` | Erreur standardisée |
| Autre erreur `GoogleOAuthException` | 500 | `GOOGLE_CALLBACK_ERROR` | Erreur standardisée |
| Exception générique | 500 | `GOOGLE_CALLBACK_ERROR` | Erreur standardisée |

**Exemple :**

```php
$response = $action->run(new GoogleCallbackRecord(
    code: 'auth-code',
    state: 'state-abc',
));
```

## Cas d'utilisation

### Cas 1 : Callback réussi

Google redirige l'utilisateur avec un `code` et un `state` valides.

```php
// GET /api/google-callback?code=xxx&state=yyy

// Réponse 200 :
// {
//     "idToken": "eyJhbGciOiJSUzI1NiIs...",
//     "accessToken": "ya29.a0AfH6...",
//     "expiresIn": 3599,
//     "tokenType": "Bearer",
//     "scope": "openid email profile",
//     "claims": {
//         "sub": "108234...",
//         "email": "user@example.com",
//         "emailVerified": true,
//         "iss": "https://accounts.google.com",
//         "aud": "xxx.apps.googleusercontent.com",
//         "exp": 1791894290,
//         "iat": 1791890690,
//         "nonce": "nonce-xyz",
//         "name": "John Doe",
//         "picture": "https://lh3.googleusercontent.com/...",
//         "locale": "fr",
//         "hd": null
//     }
// }
```

### Cas 2 : Refus utilisateur

L'utilisateur clique sur « Refuser » dans la consent screen Google.

```php
// GET /api/google-callback?state=yyy&error=access_denied&error_description=The+user+denied+the+request

// Réponse 400 :
// {
//     "message": "The user denied the request",
//     "status": 400,
//     "errorCode": "GOOGLE_PROVIDER_ERROR",
//     "errors": null
// }
```

### Cas 3 : State invalide ou expiré

Le callback arrive avec un state inconnu (rejeu, CSRF, expiration).

```php
// Réponse 500 :
// {
//     "message": "Google OAuth state not found",
//     "status": 500,
//     "errorCode": "GOOGLE_CALLBACK_ERROR",
//     "errors": null
// }
```

## Flux d'exécution

```
action_route(GoogleCallbackRequest, GoogleCallbackAction)
    ↓
GoogleCallbackRequest::getRecord() → GoogleCallbackRecord
    ↓
GoogleCallbackAction::run(record)
    ↓
before(record)
    └── instanceof GoogleCallbackRecord ? ✓
    ↓
handle(record)
    ├── instanceof GoogleCallbackRecord ? 
    │     └── non → INVALID_RECORD_TYPE (500)
    │
    ├── try: googleOAuth.callback(record) → GoogleCallbackData
    │     └── ResponseFactory::json(data, 200)
    │
    ├── catch GoogleOAuthException:
    │     ├── isProviderError() → GOOGLE_PROVIDER_ERROR (400)
    │     └── sinon → GOOGLE_CALLBACK_ERROR (500)
    │
    └── catch Exception → GOOGLE_CALLBACK_ERROR (500)
    ↓
after(success, error, record)  // hook libre
    ↓
ResponseFactory
```

## Gestion des erreurs

| Situation | ErrorCode | Status HTTP | Message |
|-----------|-----------|-------------|---------|
| Record de mauvais type | `INVALID_RECORD_TYPE` | 500 | `Invalid record type` |
| Google renvoie `error` | `GOOGLE_PROVIDER_ERROR` | 400 | `error_description` de Google |
| State introuvable | `GOOGLE_CALLBACK_ERROR` | 500 | `Google OAuth state not found` |
| State expiré | `GOOGLE_CALLBACK_ERROR` | 500 | `Google OAuth state has expired` |
| Échange de code échoue | `GOOGLE_CALLBACK_ERROR` | 500 | `HTTP request failed: ...` |
| Vérification ID token échoue | `GOOGLE_CALLBACK_ERROR` | 500 | Message de `GoogleOAuthException` |

**Note :** l'Action ne distingue pas les sous-types d'erreur `GoogleOAuthException` internes — toutes tombent sous `GOOGLE_CALLBACK_ERROR` (500). Seul le cas `providerError` remonte à 400.

## Intégration

```
Route::get('/api/google-callback', action_route(...))
    ↓
GoogleCallbackRequest  → GoogleCallbackRecord
    ↓
GoogleCallbackAction
    ↓
GoogleOAuthService::callback()
    ↓
GoogleCallbackData
```

L'Action est enregistrée comme singleton dans `GoogleServiceProvider`. Elle reçoit `GoogleOAuthService` par injection. Le contrat avec `action_route()` est respecté : `handle()` retourne toujours un `ResponseFactory`.

## Performance

- **Complexité** : O(1) + coût du `GoogleOAuthService::callback()` (dominé par 2 appels HTTP).
- **Overhead de l'Action** : négligeable (~0,1 ms).
- **Aucun cache, aucune I/O directe** dans l'Action elle-même.

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

use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleCallbackRequest;
use Illuminate\Support\Facades\Route;

use function action_route;

Route::get('/api/google-callback', action_route(
    GoogleCallbackRequest::class,
    GoogleCallbackAction::class,
))->name('api.google-callback');
```

### Requête / Réponse

```bash
# Succès
curl "https://app.test/api/google-callback?code=4/0A...&state=zzRpnjPGjOeJdbjdV5alU-QnzvQU5QrAUWuM8-V0TIM"

# HTTP 200
{
    "idToken": "eyJhbGciOiJSUzI1NiIs...",
    "accessToken": "ya29.a0AfH6...",
    "expiresIn": 3599,
    "tokenType": "Bearer",
    "scope": "openid email profile",
    "claims": { "sub": "108234...", "email": "user@example.com", "emailVerified": true, ... }
}
```

```bash
# Refus utilisateur
curl "https://app.test/api/google-callback?state=yyy&error=access_denied&error_description=The+user+denied+the+request"

# HTTP 400
{
    "message": "The user denied the request",
    "status": 400,
    "errorCode": "GOOGLE_PROVIDER_ERROR",
    "errors": null
}
```

### Consommation côté client (frontend)

```js
// 1. Récupérer l'URL de redirection
const { url } = await fetch('/api/google-redirect', { method: 'POST' }).then(r => r.json());

// 2. Rediriger l'utilisateur
window.location.href = url;

// 3. Google redirige vers /api/google-callback?code=...&state=...
// 4. Le backend retourne :
//    - soit les tokens et claims (200)
//    - soit une erreur (400 ou 500)
```

## Voir aussi

- `GoogleOAuthService` — Logique métier exécutée par l'Action
- `GoogleCallbackRequest` — Transforme la requête HTTP en `GoogleCallbackRecord`
- `GoogleCallbackRecord` — Record d'entrée
- `GoogleCallbackData` — Data de sortie (retourné en JSON)
- `GoogleOAuthException` — Exception unique, avec `isProviderError()`
- `ErrorCode::GOOGLE_CALLBACK_ERROR` — 500, erreurs internes
- `ErrorCode::GOOGLE_PROVIDER_ERROR` — 400, erreurs Google
- `AbstractAction` — Cycle de vie `before → handle → after`