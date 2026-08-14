# GetCurrentUserAction - Référence Technique

## Description

Action qui récupère l'utilisateur actuellement authentifié à partir de la requête, en supportant à la fois les tokens Bearer et les cookies. Elle retourne les données formatées de l'utilisateur ou une erreur 401 si aucun utilisateur n'est authentifié.

## Hiérarchie / Implémentations

```
AbstractAction
    └── GetCurrentUserAction [final]
```

## Rôle principal

Cette action est le **point d'entrée** pour récupérer l'utilisateur authentifié. Elle orchestre :

1. La recherche du token dans la requête (Bearer token en priorité, puis cookie)
2. La validation du token (existence, expiration)
3. La récupération de l'utilisateur associé
4. La mise à jour de `last_used_at` du token
5. Le formatage des données utilisateur
6. La gestion des erreurs avec des codes standardisés

## Dépendances

| Dépendance | Rôle |
|------------|------|
| `CookieTokenStorageInterface` | Récupération du token depuis le cookie |
| `NemesisInterface` | Recherche, validation et mise à jour des tokens |

## API / Méthodes publiques

### `handle(AbstractRecord $record): ResponseFactory`

La méthode principale qui traite la requête et retourne l'utilisateur authentifié.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Record vide (EmptyRequest) |

**Retourne :** `ResponseFactory` - Réponse HTTP :
- Succès : `200 OK` avec les données formatées de l'utilisateur
- Échec : `ErrorResponseData` avec code d'erreur et message

**Exceptions :** Aucune exception n'est levée - toutes les erreurs retournent des réponses JSON structurées.

## Flux d'exécution

```
Requête entrante (EmptyRequest)
    ↓
1. Récupération du token
    ├── Bearer token présent ? → Oui, utilisation
    └── Non → Tentative depuis le cookie
    ↓
2. Token trouvé ?
    ├── Non → 401 (UNAUTHENTICATED)
    └── Oui → Continue
    ↓
3. Hash du token (SHA-256)
    ↓
4. Recherche du token en base
    ├── Non trouvé → 401 (UNAUTHENTICATED)
    └── Trouvé → Continue
    ↓
5. Vérification de l'expiration
    ├── Expiré → 401 (UNAUTHENTICATED)
    └── Valide → Continue
    ↓
6. Récupération de l'utilisateur (tokenable)
    ├── tokenable_type ou id manquant → 401
    ├── Utilisateur non trouvé → 401
    └── Trouvé → Continue
    ↓
7. Mise à jour de last_used_at
    ↓
8. Formatage des données utilisateur
    ├── Méthode nemesisFormat() manquante → 500
    └── Présente → Retour des données formatées
    ↓
200 OK - Données utilisateur
```

## Cas d'utilisation

### Cas 1 : Récupération avec Bearer Token

**Problème** : L'application utilise l'authentification via Bearer Token pour les appels API.

**Solution** : L'action extrait automatiquement le token du header `Authorization`.

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\GetCurrentUserAction;
use AndyDefer\Actions\Http\Requests\EmptyRequest;

// Requête avec Bearer Token
// Headers: Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...

$action = app(GetCurrentUserAction::class);
$record = new EmptyRequest();
$response = $action->handle($record);

// Réponse :
// {
//     "id": 1,
//     "name": "John Doe",
//     "email": "john@example.com",
//     "emailVerifiedAt": "2026-08-14T10:00:00+00:00",
//     "createdAt": "2026-08-14T09:00:00+00:00",
//     "updatedAt": "2026-08-14T10:00:00+00:00"
// }
```

### Cas 2 : Récupération avec Cookie

**Problème** : L'application web stocke le token dans un cookie pour les sessions.

**Solution** : L'action récupère le token depuis le cookie si aucun Bearer token n'est présent.

```php
<?php

declare(strict_types=1);

// Configuration
'store_token_in_cookie' => true,
'cookie_name' => 'nemesis_token',

// Requête avec cookie
// Cookie: nemesis_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...

$response = $action->handle($record);
// ✅ L'utilisateur est récupéré depuis le cookie
```

### Cas 3 : Priorité Bearer > Cookie

**Problème** : L'application peut recevoir à la fois un Bearer token et un cookie.

**Solution** : L'action donne la priorité au Bearer token.

```php
<?php

declare(strict_types=1);

// La requête contient les deux
// Headers: Authorization: Bearer token-api
// Cookie: nemesis_token=token-cookie

$response = $action->handle($record);
// ✅ Utilise le Bearer token (prioritaire)
```

### Cas 4 : Non authentifié

**Problème** : Aucun token n'est fourni dans la requête.

**Solution** : L'action retourne une erreur 401.

```php
<?php

declare(strict_types=1);

// Aucun Bearer token, aucun cookie
$response = $action->handle($record);

// Réponse :
// {
//     "message": "Unauthenticated",
//     "status": 401,
//     "errorCode": "UNAUTHENTICATED"
// }
```

### Cas 5 : Token expiré

**Problème** : Le token est valide mais a expiré.

**Solution** : L'action retourne une erreur 401.

```php
<?php

declare(strict_types=1);

// Token avec expires_at < now()
$response = $action->handle($record);

// Réponse :
// {
//     "message": "Unauthenticated",
//     "status": 401,
//     "errorCode": "UNAUTHENTICATED"
// }
```

## Gestion des erreurs

| Situation | Code HTTP | ErrorCode | Message |
|-----------|-----------|-----------|---------|
| Aucun token | 401 | `UNAUTHENTICATED` | `Unauthenticated` |
| Token non trouvé | 401 | `UNAUTHENTICATED` | `Unauthenticated` |
| Token expiré | 401 | `UNAUTHENTICATED` | `Unauthenticated` |
| Tokenable manquant | 401 | `UNAUTHENTICATED` | `Unauthenticated` |
| Utilisateur non trouvé | 401 | `UNAUTHENTICATED` | `Unauthenticated` |
| Méthode `nemesisFormat()` manquante | 500 | `USER_FORMAT_ERROR` | `User data format not available` |
| Exception générique | 500 | `USER_FETCH_ERROR` | `An error occurred while fetching the current user` |

## Intégration

### Avec les routes

```php
<?php

declare(strict_types=1);

use AndyDefer\Actions\Http\Requests\EmptyRequest;
use AndyDefer\AuthenticationKit\Mail\Actions\GetCurrentUserAction;

// Route sans middleware (l'action gère elle-même l'authentification)
Route::post('/me', action_route(
    EmptyRequest::class,
    GetCurrentUserAction::class
))->name('me');
```

### Avec le middleware web

L'action peut être combinée avec le middleware `nemesis.web` pour protéger des routes :

```php
<?php

declare(strict_types=1);

// Route protégée par le middleware
Route::middleware(['nemesis.web'])->get('/dashboard', function () {
    // L'utilisateur est déjà authentifié par le middleware
    return view('dashboard');
});

// Mais le middleware n'est PAS nécessaire pour /me
// L'action GetCurrentUserAction gère elle-même l'authentification
```

### Avec les tests

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;

public function test_me_returns_user_with_bearer_token()
{
    // 1. Connexion pour obtenir un token
    $user = TestUserMail::create([...]);
    $loginPayload = [...];
    $loginResponse = $this->postJson('/login', $loginPayload);
    $token = $loginResponse->json('token');

    // 2. Requête /me
    $response = $this->postJson('/me', [
        'model_type' => TestUserMail::class,
    ], [
        'Authorization' => 'Bearer '.$token,
    ]);

    $response->assertStatus(200);
    $response->assertJson([
        'id' => $user->id,
        'name' => $user->name,
        'email' => $user->email,
    ]);
}

public function test_me_returns_401_without_token()
{
    $response = $this->postJson('/me', [
        'model_type' => TestUserMail::class,
    ]);

    $response->assertStatus(401);
    $response->assertJson(['errorCode' => 'UNAUTHENTICATED']);
}

public function test_me_returns_user_with_cookie()
{
    // 1. Login avec cookie configuré
    $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
    $loginResponse = $this->postJson('/login', $loginPayload);
    $token = $this->getCookieValue($loginResponse, 'nemesis_token');

    // 2. Requête /me avec cookie
    $response = $this->call('POST', '/me', [
        'model_type' => TestUserMail::class,
    ], [
        'nemesis_token' => $token,
    ]);

    $response->assertStatus(200);
    $response->assertJson(['id' => $user->id]);
}
```

## Performance

- **Complexité** : O(1) - requêtes DB optimisées
- **Hash du token** : SHA-256 en O(1)
- **Recherche du token** : Index sur `token_hash`
- **Recherche de l'utilisateur** : Index sur `id` (clé primaire)
- **Mise à jour** : `updateLastUsed()` en O(1)
- **Formatage** : Dépend de l'implémentation du modèle
- **Mémoire** : Allocation minimale

## Compatibilité

| Version | Support | Détails |
|---------|---------|---------|
| PHP 8.1+ | ✅ Complet | Types, énumérations |
| PHP 8.0 | ✅ Complet | Support complet |
| Laravel 12 | ✅ Complet | Framework supporté |
| Laravel 13 | ✅ Complet | Framework supporté |
| Laravel 14 | ✅ Complet | Framework supporté |
| Laravel 15 | ✅ Complet | Framework supporté |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\Actions\Http\Requests\EmptyRequest;
use AndyDefer\AuthenticationKit\Mail\Actions\GetCurrentUserAction;
use App\Models\User;
use Illuminate\Routing\Controller;

class UserController extends Controller
{
    public function me(GetCurrentUserAction $action)
    {
        // 1. Exécution de l'action avec un record vide
        $record = new EmptyRequest();
        $response = $action->handle($record);

        // 2. Retour de la réponse (déjà formatée)
        return $response;
    }
}

// Exemple d'utilisation dans une route API
Route::post('/me', [UserController::class, 'me']);

// Requête (avec Bearer Token) : POST /me
// Headers : Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
// Body : { "model_type": "App\\Models\\User" }

// Réponse (succès) :
// {
//     "id": 1,
//     "name": "John Doe",
//     "email": "john@example.com",
//     "emailVerifiedAt": "2026-08-14T10:00:00+00:00",
//     "createdAt": "2026-08-14T09:00:00+00:00",
//     "updatedAt": "2026-08-14T10:00:00+00:00"
// }

// Réponse (non authentifié) :
// {
//     "message": "Unauthenticated",
//     "status": 401,
//     "errorCode": "UNAUTHENTICATED"
// }

// Réponse (erreur de format) :
// {
//     "message": "User data format not available",
//     "status": 500,
//     "errorCode": "USER_FORMAT_ERROR"
// }
```

## Voir aussi

- `EmptyRequest` - Record vide pour les actions sans données
- `CookieTokenStorageInterface` - Récupération des tokens depuis les cookies
- `NemesisInterface` - Gestion des tokens
- `ErrorCode` - Codes d'erreur standardisés
- `MailAuthenticatable::nemesisFormat()` - Formatage des données utilisateur
- `EmailLoginAction` - Action de connexion
- `EmailLogoutAction` - Action de déconnexion