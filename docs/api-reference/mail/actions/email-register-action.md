# EmailRegisterAction - Référence Technique

## Description

Action d'inscription qui crée un nouveau compte utilisateur via authentification par email. Elle gère la validation des données, la création du compte, la génération optionnelle d'un token d'authentification, et la journalisation de l'événement.

## Hiérarchie / Implémentations

```
AbstractAction
    └── EmailRegisterAction [final]
```

## Rôle principal

Cette action est le **point d'entrée principal** pour l'inscription des utilisateurs. Elle orchestre :

1. La validation du modèle et de l'interface `MailAuthenticatable`
2. La délégation à `MailAuthenticationService` pour la création du compte
3. La génération optionnelle d'un token d'authentification (avec métadonnées)
4. Le stockage automatique du token dans le cookie (si configuré)
5. La journalisation du succès ou de l'échec
6. La gestion des erreurs avec des codes standardisés

## Dépendances

| Dépendance | Rôle |
|------------|------|
| `NemesisInterface` | Création des tokens d'authentification |
| `LogRepositoryInterface` | Journalisation des événements |
| `AgentInterface` | Détection des métadonnées du client |
| `AuthenticationKitConfigInterface` | Configuration (nom du token, etc.) |

## API / Méthodes publiques

### `handle(AbstractRecord $record): ResponseFactory`

La méthode principale qui traite la requête d'inscription.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `EmailRegisterAuthRecord` | Record contenant les données d'inscription |

**Retourne :** `ResponseFactory` - Réponse HTTP :
- Succès : `AuthRegisteredData` avec message, données utilisateur et token optionnel
- Échec : `ErrorResponseData` avec code d'erreur et message

**Exceptions :** Aucune exception n'est levée directement - toutes les erreurs retournent des réponses JSON structurées.

### `before(AbstractRecord $record): void`

Prépare l'action en extrayant les données du record.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `EmailRegisterAuthRecord` | Record contenant les données de la requête |

**Exceptions :** `InvalidArgumentException` si le record n'est pas du type attendu

### `after(bool $success, ?Exception $error, AbstractRecord $record): void`

Journalise le résultat de la tentative d'inscription.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Indique si l'opération a réussi |
| `$error` | `Exception|null` | L'exception si une erreur est survenue |
| `$record` | `AbstractRecord` | Le record original de la requête |

## Flux d'exécution

```
Requête entrante (EmailRegisterAuthRecord)
    ↓
1. before() - Validation du record
    ↓
2. handle() - Traitement principal
    ├── Validation du record
    │   └── Invalide → 500 (INVALID_RECORD_TYPE)
    ├── Validation du modèle
    │   ├── N'existe pas → 500 (MODEL_NOT_FOUND)
    │   └── Interface non implémentée → 500 (INVALID_MODEL)
    ├── Appel à MailAuthenticationService::register()
    │   ├── ValidationException → 422 (VALIDATION_ERROR)
    │   └── Succès → Continue
    ├── Génération du token (si with_token = true)
    │   └── Métadonnées : device, platform, browser, IP, user-agent
    └── Succès → 201 (AuthRegisteredData)
    ↓
3. after() - Journalisation
    ├── Succès → logRegistrationSuccess()
    └── Échec → logRegistrationFailure()
```

## Cas d'utilisation

### Cas 1 : Inscription standard (sans token)

**Problème** : L'utilisateur crée un compte mais ne souhaite pas être connecté automatiquement.

**Solution** : L'action crée le compte sans générer de token.

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use App\Models\User;

// Création du record
$record = EmailRegisterAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]),
    'with_token' => false, // Pas de token généré
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);

// Exécution de l'action
$action = app(EmailRegisterAction::class);
$response = $action->handle($record);

// Réponse JSON
// {
//     "message": "Registration successful",
//     "auth": { "id": 1, "name": "John", "email": "john@example.com" },
//     "token": null
// }
```

### Cas 2 : Inscription avec token et cookie

**Problème** : L'utilisateur s'inscrit et doit être immédiatement connecté.

**Solution** : L'action génère un token qui sera automatiquement stocké dans le cookie.

```php
<?php

declare(strict_types=1);

// Configuration
'store_token_in_cookie' => true,

// Record avec with_token = true
$record = EmailRegisterAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]),
    'with_token' => true, // Token généré et stocké dans cookie
]);

$response = $action->handle($record);

// Réponse : token dans le body ET cookie
// Body: { "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..." }
// Cookie: auth_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Cas 3 : Validation des données

**Problème** : Les données soumises ne respectent pas les règles de validation.

**Solution** : L'action capture `ValidationException` et retourne les erreurs.

```php
<?php

declare(strict_types=1);

// Email déjà utilisé
$record = EmailRegisterAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'name' => 'John Doe',
        'email' => 'existing@example.com', // Déjà en base
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]),
]);

$response = $action->handle($record);
// 422 - "Validation error"
// errors: { "email": ["The email has already been taken."] }

// Mot de passe trop court
$record = EmailRegisterAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => '123', // 3 caractères
        'password_confirmation' => '123',
    ]),
]);

$response = $action->handle($record);
// 422 - "Validation error"
// errors: { "password": ["The password must be at least 8 characters."] }
```

### Cas 4 : Métadonnées enrichies

**Problème** : L'application doit savoir d'où vient l'inscription.

**Solution** : L'action capture automatiquement les métadonnées du client.

```php
<?php

declare(strict_types=1);

// La requête contient IP et User-Agent
$record = EmailRegisterAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([...]),
    'ip' => '192.168.1.100',
    'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0) AppleWebKit/605.1.15',
    'with_token' => true,
]);

// Le token contient les métadonnées :
// {
//     "device_type": "mobile",
//     "platform": "iOS 14",
//     "browser": "Safari",
//     "ip": "192.168.1.100",
//     "user_agent": "Mozilla/5.0 (iPhone; CPU iPhone OS 14_0)..."
// }
```

## Gestion des erreurs

| Situation | Code HTTP | ErrorCode | Message |
|-----------|-----------|-----------|---------|
| Record invalide | 500 | `INVALID_RECORD_TYPE` | `Invalid record type` |
| Modèle inexistant | 500 | `MODEL_NOT_FOUND` | `Model does not exist` |
| Interface non implémentée | 500 | `INVALID_MODEL` | `Model must implement MailAuthenticatable` |
| Erreur de validation | 422 | `VALIDATION_ERROR` | `Validation error` |
| Erreur générique | 500 | `REGISTRATION_ERROR` | `An error occurred during registration` |

## Validation en amont

L'action **délègue** la validation à `MailAuthenticationService::register()` qui applique les règles :

```php
<?php

// Règles de validation par défaut
[
    'email' => ['required', 'email', "unique:{$table}"],
    'password' => ['required', 'min:8', 'confirmed'],
];
```

Le modèle peut surcharger ces règles en redéfinissant la méthode `getValidationRules()` dans le modèle.

## Intégration

### Avec le middleware

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;

// Le middleware validate.mail.authenticatable valide model_type
Route::middleware(['validate.mail.authenticatable'])->post('/api/register', action_route(
    EmailRegisterRequest::class,
    EmailRegisterAction::class
));
```

### Avec le service d'authentification

L'action utilise `MailAuthenticationService` via la méthode statique du modèle :

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;

class User extends Model implements MailAuthenticatable
{
    // La méthode getMailAuthService() est fournie par l'interface
    public static function getMailAuthService(): MailAuthenticationInterface
    {
        return MailAuthenticationService::for(self::class);
    }
}
```

### Avec les tests

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;

public function test_register_with_token_and_cookie()
{
    $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
    
    $payload = [
        'model_type' => TestUserMail::class,
        'with_token' => true,
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ];
    
    $response = $this->postJson('/api/register', $payload);
    
    $response->assertStatus(201);
    $response->assertJsonStructure(['token', 'auth']);
    $response->assertCookie('nemesis_token');
}
```

## Performance

- **Complexité** : O(1) - requêtes DB optimisées
- **Validation** : Laravel Validator en O(n) sur les champs
- **Token** : Création via Nemesis en O(1)
- **Métadonnées** : Détection via AgentInterface en O(1)
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

use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RegisterController extends Controller
{
    public function register(Request $request, EmailRegisterAction $action)
    {
        // 1. Validation des champs requis
        $validated = $request->validate([
            'model_type' => 'required|string',
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'with_token' => 'sometimes|boolean',
        ]);

        // 2. Construction du record
        $record = EmailRegisterAuthRecord::from([
            'model_type' => $validated['model_type'],
            'data' => StrictDataObject::from([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'password_confirmation' => $validated['password'] . '_confirmation_placeholder',
            ]),
            'with_token' => $validated['with_token'] ?? true,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // 3. Exécution de l'action
        $response = $action->handle($record);

        // 4. Retour de la réponse
        return $response;
    }
}

// Exemple d'utilisation dans une route API
Route::post('/api/register', [RegisterController::class, 'register']);

// Requête : POST /api/register
// {
//     "model_type": "App\\Models\\User",
//     "name": "John Doe",
//     "email": "john@example.com",
//     "password": "Secret123!",
//     "password_confirmation": "Secret123!",
//     "with_token": true
// }

// Réponse (succès) :
// {
//     "message": "Registration successful",
//     "auth": {
//         "id": 1,
//         "name": "John Doe",
//         "email": "john@example.com",
//         "emailVerifiedAt": null,
//         "createdAt": "2026-08-14T10:00:00+00:00",
//         "updatedAt": "2026-08-14T10:00:00+00:00"
//     },
//     "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
// }

// Réponse (échec - validation) :
// {
//     "message": "Validation error",
//     "status": 422,
//     "errorCode": "VALIDATION_ERROR",
//     "errors": {
//         "email": ["The email has already been taken."]
//     }
// }

// Réponse (échec - modèle invalide) :
// {
//     "message": "Model NonExistentClass must implement MailAuthenticatable",
//     "status": 500,
//     "errorCode": "INVALID_MODEL"
// }
```

## Voir aussi

- `EmailRegisterAuthRecord` - Record de données pour l'inscription
- `AuthRegisteredData` - Réponse de succès
- `ErrorResponseData` - Réponse d'erreur
- `ErrorCode` - Codes d'erreur standardisés
- `ErrorType` - Types d'erreur pour les logs
- `MailAuthenticationService::register()` - Service sous-jacent
- `NemesisInterface` - Gestion des tokens
- `AgentInterface` - Détection des métadonnées client
- `ValidateMailAuthenticatableMiddleware` - Middleware de validation