# EmailLoginAction - Référence Technique

## Description

Action d'authentification qui gère la connexion des utilisateurs par email. Elle valide les identifiants, crée un token d'authentification via Nemesis, enregistre les métadonnées de la session (IP, user-agent, appareil) et journalise le résultat de la tentative.

## Hiérarchie / Implémentations

```
AbstractAction
    └── EmailLoginAction [final]
```

## Rôle principal

Cette action est le **point d'entrée principal** pour la connexion des utilisateurs. Elle orchestre :

1. La validation des identifiants (email + mot de passe)
2. La délégation à `MailAuthenticationService` pour l'authentification
3. La création du token d'authentification via Nemesis
4. L'enregistrement des métadonnées de la session (device, IP, browser)
5. La journalisation du succès ou de l'échec
6. La gestion des erreurs avec des codes standardisés

## Dépendances

| Dépendance | Rôle |
|------------|------|
| `NemesisInterface` | Création et gestion des tokens |
| `LogRepositoryInterface` | Journalisation des événements |
| `AgentInterface` | Détection des métadonnées du client (device, browser, OS) |
| `AuthenticationKitConfigInterface` | Configuration (nom du token, etc.) |

## API / Méthodes publiques

### `handle(AbstractRecord $record): ResponseFactory`

La méthode principale qui traite la requête de connexion.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `EmailLoginAuthRecord` | Record contenant les identifiants et métadonnées |

**Retourne :** `ResponseFactory` - Réponse HTTP contenant :
- Succès : `AuthLoginData` avec message, données utilisateur et token
- Échec : `ErrorResponseData` avec code d'erreur et message

**Exceptions :** Aucune exception n'est levée directement - toutes les erreurs retournent des réponses JSON structurées.

### `before(AbstractRecord $record): void`

Prépare l'action en extrayant les données du record.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `EmailLoginAuthRecord` | Record contenant les données de la requête |

**Exceptions :** `InvalidArgumentException` si le record n'est pas du type attendu

### `after(bool $success, ?Exception $error, AbstractRecord $record): void`

Journalise le résultat de la tentative de connexion.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Indique si l'opération a réussi |
| `$error` | `Exception|null` | L'exception si une erreur est survenue |
| `$record` | `AbstractRecord` | Le record original de la requête |

## Flux d'exécution

```
Requête entrante (EmailLoginAuthRecord)
    ↓
1. before() - Validation du record
    ↓
2. handle() - Traitement principal
    ├── Vérification des identifiants (email/password)
    │   ├── Manquants → 400 (MISSING_CREDENTIALS)
    │   └── Présents → Continue
    ├── Récupération du service d'authentification
    │   └── $modelClass::getMailAuthService()
    ├── Tentative de connexion
    │   ├── Échec → 401 (INVALID_CREDENTIALS)
    │   └── Succès → Continue
    ├── Récupération de l'utilisateur
    │   ├── Non trouvé → 401 (AUTHENTICATABLE_NOT_FOUND)
    │   └── Trouvé → Continue
    ├── Création du token via Nemesis
    │   └── Métadonnées : device, platform, browser, IP, user-agent
    └── Succès → 200 (AuthLoginData)
    ↓
3. after() - Journalisation
    ├── Succès → loginSuccess()
    └── Échec → loginFailure()
```

## Cas d'utilisation

### Cas 1 : Connexion standard

**Problème** : L'utilisateur soumet son email et mot de passe pour s'authentifier.

**Solution** : L'action valide les identifiants et retourne un token.

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLoginAuthRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use App\Models\User;

// Création du record
$record = EmailLoginAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'email' => 'john@example.com',
        'password' => 'Secret123!',
    ]),
    'ip' => request()->ip(),
    'user_agent' => request()->userAgent(),
]);

// Exécution de l'action
$action = app(EmailLoginAction::class);
$response = $action->handle($record);

// Réponse JSON
// {
//     "message": "Login successful",
//     "auth": { "id": 1, "name": "John", "email": "john@example.com" },
//     "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
// }
```

### Cas 2 : Connexion avec cookie

**Problème** : L'application web stocke le token dans un cookie pour les sessions.

**Solution** : L'action crée le token et le middleware l'ajoute au cookie.

```php
<?php

declare(strict_types=1);

// Configuration
'store_token_in_cookie' => true,
'cookie_name' => 'my_auth_token',

// La réponse inclut automatiquement le cookie
$response = $action->handle($record);
// Cookie: my_auth_token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...
```

### Cas 3 : Gestion des identifiants manquants

**Problème** : L'utilisateur soumet un formulaire incomplet.

**Solution** : L'action retourne une erreur 400 avec les champs manquants.

```php
<?php

declare(strict_types=1);

// Email manquant
$record = EmailLoginAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'password' => 'Secret123!',
    ]),
]);

$response = $action->handle($record);
// 400 - "Email and password are required"
// errors: { "email": ["The email field is required."] }

// Password manquant
$record = EmailLoginAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'email' => 'john@example.com',
    ]),
]);

$response = $action->handle($record);
// 400 - "Email and password are required"
// errors: { "password": ["The password field is required."] }
```

### Cas 4 : Connexion avec métadonnées enrichies

**Problème** : L'application doit connaître le type d'appareil pour la sécurité.

**Solution** : L'action capture automatiquement les métadonnées via `AgentInterface`.

```php
<?php

declare(strict_types=1);

$record = EmailLoginAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'email' => 'john@example.com',
        'password' => 'Secret123!',
    ]),
    'ip' => '192.168.1.100',
    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
]);

$response = $action->handle($record);

// Le token contient les métadonnées :
// {
//     "device_type": "desktop",
//     "platform": "Windows 10",
//     "browser": "Chrome",
//     "ip": "192.168.1.100",
//     "user_agent": "Mozilla/5.0..."
// }
```

## Gestion des erreurs

| Situation | Code HTTP | ErrorCode | Message |
|-----------|-----------|-----------|---------|
| Record invalide | 500 | `INVALID_RECORD_TYPE` | `Invalid record type` |
| Email ou password manquant | 400 | `MISSING_CREDENTIALS` | `Email and password are required` |
| Identifiants invalides | 401 | `INVALID_CREDENTIALS` | `Invalid credentials` |
| Utilisateur non trouvé | 401 | `AUTHENTICATABLE_NOT_FOUND` | `Authenticatable not found` |
| Erreur de validation | 422 | `VALIDATION_ERROR` | `Validation error` |
| Erreur générique | 500 | `LOGIN_ERROR` | `An error occurred during login` |

## Intégration

### Avec le middleware

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;

Route::middleware(['validate.mail.authenticatable'])->post('/api/login', action_route(
    EmailLoginRequest::class,
    EmailLoginAction::class
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

// Création d'un utilisateur de test
$user = TestUserMail::create([
    'name' => 'Test User',
    'email' => 'test@example.com',
    'password' => bcrypt('Password123!'),
]);

// Requête de test
$payload = [
    'model_type' => TestUserMail::class,
    'email' => 'test@example.com',
    'password' => 'Password123!',
];

$response = $this->postJson('/api/login', $payload);
$response->assertStatus(200);
$response->assertJsonStructure(['token', 'auth']);
```

## Performance

- **Complexité** : O(1) - requêtes DB optimisées (index sur email)
- **Métadonnées** : Détection via AgentInterface en O(1)
- **Token** : Création via Nemesis en O(1)
- **Logs** : Écriture asynchrone via LogRepository
- **Mémoire** : Allocation minimale - objets de réponse légers

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

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLoginAuthRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class LoginController extends Controller
{
    public function login(Request $request, EmailLoginAction $action)
    {
        // 1. Validation des champs requis
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
            'model_type' => 'required|string',
        ]);

        // 2. Construction du record
        $record = EmailLoginAuthRecord::from([
            'model_type' => $validated['model_type'],
            'data' => StrictDataObject::from([
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // 3. Exécution de l'action
        $response = $action->handle($record);

        // 4. Retour de la réponse (déjà formatée)
        return $response;
    }
}

// Exemple d'utilisation dans une route API
Route::post('/api/login', [LoginController::class, 'login']);

// Requête : POST /api/login
// {
//     "model_type": "App\\Models\\User",
//     "email": "john@example.com",
//     "password": "Secret123!"
// }

// Réponse (succès) :
// {
//     "message": "Login successful",
//     "auth": {
//         "id": 1,
//         "name": "John Doe",
//         "email": "john@example.com",
//         "emailVerifiedAt": "2026-08-14T10:00:00+00:00",
//         "createdAt": "2026-08-14T09:00:00+00:00",
//         "updatedAt": "2026-08-14T10:00:00+00:00"
//     },
//     "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
// }

// Réponse (échec - identifiants invalides) :
// {
//     "message": "Invalid credentials",
//     "status": 401,
//     "errorCode": "INVALID_CREDENTIALS"
// }

// Réponse (échec - champs manquants) :
// {
//     "message": "Email and password are required",
//     "status": 400,
//     "errorCode": "MISSING_CREDENTIALS",
//     "errors": {
//         "email": ["The email field is required."]
//     }
// }
```

## Voir aussi

- `EmailLoginAuthRecord` - Record de données pour la connexion
- `AuthLoginData` - Réponse de succès
- `ErrorResponseData` - Réponse d'erreur
- `ErrorCode` - Codes d'erreur standardisés
- `ErrorType` - Types d'erreur pour les logs
- `MailAuthenticationService` - Service d'authentification sous-jacent
- `NemesisInterface` - Gestion des tokens
- `AgentInterface` - Détection des métadonnées client