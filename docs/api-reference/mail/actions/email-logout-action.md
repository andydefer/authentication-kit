# EmailLogoutAction - Référence Technique

## Description

Action de déconnexion qui invalide le token d'authentification d'un utilisateur. Elle valide le token fourni, révoque le token via Nemesis, supprime automatiquement le cookie si configuré, et journalise le résultat de l'opération.

## Hiérarchie / Implémentations

```
AbstractAction
    └── EmailLogoutAction [final]
```

## Rôle principal

Cette action est le **point d'entrée principal** pour la déconnexion des utilisateurs. Elle orchestre :

1. La validation du token d'authentification
2. La vérification de l'expiration du token
3. La récupération de l'utilisateur associé
4. La délégation à `MailAuthenticationService` pour la révocation
5. La suppression automatique du cookie (si configuré)
6. La journalisation du succès ou de l'échec
7. La gestion des erreurs avec des codes standardisés

## Dépendances

| Dépendance | Rôle |
|------------|------|
| `NemesisInterface` | Recherche et révocation des tokens |
| `LogRepositoryInterface` | Journalisation des événements |

## API / Méthodes publiques

### `handle(AbstractRecord $record): ResponseFactory`

La méthode principale qui traite la requête de déconnexion.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `EmailLogoutAuthRecord` | Record contenant le token à révoquer |

**Retourne :** `ResponseFactory` - Réponse HTTP :
- Succès : `204 No Content`
- Échec : `ErrorResponseData` avec code d'erreur et message

**Exceptions :** Aucune exception n'est levée directement - toutes les erreurs retournent des réponses JSON structurées.

### `before(AbstractRecord $record): void`

Prépare l'action en validant le record et le modèle.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `EmailLogoutAuthRecord` | Record contenant les données de la requête |

**Exceptions :** 
- `InvalidArgumentException` si le record n'est pas du type attendu
- `InvalidArgumentException` si le modèle n'existe pas
- `InvalidArgumentException` si le modèle n'implémente pas `MailAuthenticatable`

### `after(bool $success, ?Exception $error, AbstractRecord $record): void`

Journalise le résultat de la tentative de déconnexion.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Indique si l'opération a réussi |
| `$error` | `Exception|null` | L'exception si une erreur est survenue |
| `$record` | `AbstractRecord` | Le record original de la requête |

## Flux d'exécution

```
Requête entrante (EmailLogoutAuthRecord)
    ↓
1. before() - Validation du record et du modèle
    ├── Record invalide → InvalidArgumentException
    ├── Modèle inexistant → InvalidArgumentException
    └── Interface non implémentée → InvalidArgumentException
    ↓
2. handle() - Traitement principal
    ├── Validation du record
    │   └── Invalide → 500 (INVALID_RECORD_TYPE)
    ├── Recherche du token par hash
    │   ├── Non trouvé → 401 (INVALID_TOKEN)
    │   └── Trouvé → Continue
    ├── Vérification de l'expiration
    │   ├── Expiré → 401 (TOKEN_EXPIRED)
    │   └── Valide → Continue
    ├── Récupération de l'utilisateur
    │   ├── tokenable manquant → 401 (INVALID_TOKEN)
    │   └── Utilisateur non trouvé → 401 (AUTHENTICATABLE_NOT_FOUND)
    ├── Appel à MailAuthenticationService::logout()
    │   ├── Exception → 500 (LOGOUT_EXCEPTION)
    │   ├── Échec → 500 (LOGOUT_FAILED)
    │   └── Succès → Continue
    └── Succès → 204 No Content
    ↓
3. after() - Journalisation
    ├── Succès → logoutSuccess()
    └── Échec → logoutFailure()
```

## Cas d'utilisation

### Cas 1 : Déconnexion standard

**Problème** : L'utilisateur soumet son token pour se déconnecter.

**Solution** : L'action révoque le token et retourne une réponse 204.

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLogoutAuthRecord;
use App\Models\User;

// Création du record avec le token
$record = EmailLogoutAuthRecord::from([
    'model_type' => User::class,
    'token' => 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...',
]);

// Exécution de l'action
$action = app(EmailLogoutAction::class);
$response = $action->handle($record);

// Réponse : 204 No Content
```

### Cas 2 : Déconnexion avec suppression du cookie

**Problème** : L'application web stocke le token dans un cookie.

**Solution** : L'action révoque le token ET le middleware supprime le cookie.

```php
<?php

declare(strict_types=1);

// Configuration
'store_token_in_cookie' => true,

// L'action révoque le token
$action->handle($record); // 204

// Le middleware ValidateMailAuthenticatableMiddleware supprime le cookie
// Cookie: my_auth_token=deleted; expires=Thu, 01 Jan 1970 00:00:00 GMT
```

### Cas 3 : Token invalide ou expiré

**Problème** : L'utilisateur soumet un token déjà révoqué ou expiré.

**Solution** : L'action retourne une erreur 401 appropriée.

```php
<?php

declare(strict_types=1);

// Token inexistant
$record = EmailLogoutAuthRecord::from([
    'model_type' => User::class,
    'token' => 'invalid-token-123',
]);

$response = $action->handle($record);
// 401 - "Invalid token"

// Token expiré
// Le token en base a expires_at < now()
$response = $action->handle($record);
// 401 - "Token has expired"
```

### Cas 4 : Utilisateur supprimé

**Problème** : L'utilisateur associé au token a été supprimé (soft delete).

**Solution** : L'action retourne une erreur 401 car l'utilisateur n'est pas trouvé.

```php
<?php

declare(strict_types=1);

// L'utilisateur a été soft-deleted
$user->delete();

$record = EmailLogoutAuthRecord::from([
    'model_type' => User::class,
    'token' => $plainToken,
]);

$response = $action->handle($record);
// 401 - "Authenticatable not found"
```

## Gestion des erreurs

| Situation | Code HTTP | ErrorCode | Message |
|-----------|-----------|-----------|---------|
| Record invalide | 500 | `INVALID_RECORD_TYPE` | `Invalid record type` |
| Token non trouvé | 401 | `INVALID_TOKEN` | `Invalid token` |
| Token expiré | 401 | `TOKEN_EXPIRED` | `Token has expired` |
| Tokenable manquant | 401 | `INVALID_TOKEN` | `Invalid token` |
| Utilisateur non trouvé | 401 | `AUTHENTICATABLE_NOT_FOUND` | `Authenticatable not found` |
| Exception lors de la révocation | 500 | `LOGOUT_EXCEPTION` | `An error occurred during logout: {message}` |
| Échec de la révocation | 500 | `LOGOUT_FAILED` | `Logout failed` |

## Validation en amont

L'action **délègue** la validation à `before()` qui vérifie :

1. Le record est de type `EmailLogoutAuthRecord`
2. La classe du modèle existe
3. Le modèle implémente `MailAuthenticatable`

```php
<?php

// ✅ Valide
$record = EmailLogoutAuthRecord::from([
    'model_type' => User::class, // User implémente MailAuthenticatable
    'token' => 'token-123',
]);

// ❌ Invalide - Record incorrect
$record = EmailRegisterAuthRecord::from([...]);
// InvalidArgumentException: Invalid record type

// ❌ Invalide - Modèle inexistant
$record = EmailLogoutAuthRecord::from([
    'model_type' => 'NonExistentClass',
    'token' => 'token-123',
]);
// InvalidArgumentException: Model NonExistentClass does not exist

// ❌ Invalide - Interface non implémentée
$record = EmailLogoutAuthRecord::from([
    'model_type' => stdClass::class,
    'token' => 'token-123',
]);
// InvalidArgumentException: Model stdClass must implement MailAuthenticatable
```

## Intégration

### Avec le middleware

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLogoutRequest;

// Le middleware validate.mail.authenticatable valide model_type
Route::middleware(['validate.mail.authenticatable'])->post('/api/logout', action_route(
    EmailLogoutRequest::class,
    EmailLogoutAction::class
));
```

### Avec l'authentification Bearer

```php
<?php

declare(strict_types=1);

// Requête
POST /api/logout
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...

// Corps
{
    "model_type": "App\\Models\\User",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}

// Réponse (succès)
HTTP/1.1 204 No Content
```

### Avec les tests

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;

// 1. Création d'un utilisateur et login pour obtenir un token
$user = TestUserMail::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('Password123!'),
]);

$loginPayload = [
    'model_type' => TestUserMail::class,
    'email' => 'test@example.com',
    'password' => 'Password123!',
];

$loginResponse = $this->postJson('/api/login', $loginPayload);
$token = $loginResponse->json('token');

// 2. Déconnexion
$logoutPayload = [
    'model_type' => TestUserMail::class,
    'token' => $token,
];

$logoutResponse = $this->postJson('/api/logout', $logoutPayload, [
    'Authorization' => 'Bearer '.$token,
]);

$logoutResponse->assertStatus(204);

// 3. Vérification que le token est révoqué
$meResponse = $this->postJson('/api/me', [
    'model_type' => TestUserMail::class,
], [
    'Authorization' => 'Bearer '.$token,
]);

$meResponse->assertStatus(401);
$meResponse->assertJson(['errorCode' => 'UNAUTHENTICATED']);
```

## Performance

- **Complexité** : O(1) - requêtes DB optimisées
- **Hash du token** : SHA-256 en O(1)
- **Recherche du token** : Index sur `token_hash`
- **Révocation** : Mise à jour d'un enregistrement
- **Logs** : Écriture asynchrone via LogRepository
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

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLogoutAuthRecord;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LogoutController extends Controller
{
    public function logout(Request $request, EmailLogoutAction $action)
    {
        // 1. Récupération du token
        $token = $request->bearerToken();
        
        if ($token === null) {
            return response()->json([
                'error' => 'Token required'
            ], 401);
        }

        // 2. Validation du model_type
        $validated = $request->validate([
            'model_type' => 'required|string',
        ]);

        // 3. Construction du record
        $record = EmailLogoutAuthRecord::from([
            'model_type' => $validated['model_type'],
            'token' => $token,
        ]);

        // 4. Exécution de l'action
        $response = $action->handle($record);

        // 5. Retour de la réponse
        return $response;
    }
}

// Exemple d'utilisation dans une route API
Route::post('/api/logout', [LogoutController::class, 'logout'])
    ->middleware(['validate.mail.authenticatable']);

// Requête : POST /api/logout
// Headers : Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
// Body : { "model_type": "App\\Models\\User" }
//
// Réponse (succès) : 204 No Content
//
// Réponse (échec - token invalide) :
// {
//     "message": "Invalid token",
//     "status": 401,
//     "errorCode": "INVALID_TOKEN"
// }
//
// Réponse (échec - token expiré) :
// {
//     "message": "Token has expired",
//     "status": 401,
//     "errorCode": "TOKEN_EXPIRED"
// }
```

## Voir aussi

- `EmailLogoutAuthRecord` - Record de données pour la déconnexion
- `ErrorResponseData` - Réponse d'erreur
- `ErrorCode` - Codes d'erreur standardisés
- `ErrorType` - Types d'erreur pour les logs
- `MailAuthenticationService::logout()` - Service sous-jacent
- `NemesisInterface` - Gestion des tokens
- `ValidateMailAuthenticatableMiddleware` - Middleware de validation