# MailAuthenticationService - Référence Technique

## Description

Service générique d'authentification par email qui fonctionne avec n'importe quel modèle Eloquent implémentant l'interface `MailAuthenticatable`. Il gère l'inscription, la connexion, la déconnexion, la réinitialisation de mot de passe et la vérification d'email avec support des tokens d'authentification (Bearer ou cookies) via Nemesis.

## Hiérarchie / Implémentations

```
MailAuthenticationInterface
    └── MailAuthenticationService [Template T of Model&MailAuthenticatable]
```

**Génériques :**
- `T` : Type du modèle Eloquent qui doit étendre `Model` et implémenter `MailAuthenticatable`

## Rôle principal

Ce service est le **cœur fonctionnel** du package d'authentification. Il orchestre toutes les opérations d'authentification en :

1. Gérant le cycle de vie des utilisateurs (inscription, connexion, déconnexion)
2. Créant et validant les tokens d'authentification via Nemesis
3. Gérant les OTP (One-Time Passwords) via `OtpService` pour la vérification email et la réinitialisation de mot de passe
4. Enregistrant tous les événements via `LogRepositoryInterface`
5. Stockant les tokens dans les cookies (optionnel)
6. Fournissant des **hooks extensibles** pour personnaliser le comportement
7. Supportant le **Template Method Pattern** pour la personnalisation des notifications

## Installation

```bash
# Le service est automatiquement disponible après installation du package
composer require andydefer/authentication-kit

# Configuration requise
php artisan vendor:publish --tag=authentication-kit-config
```

## API / Méthodes publiques

### `for(string $modelClass): static`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `class-string<T>` | Classe du modèle d'authentification |

**Retourne :** `static<T>` - Instance du service configurée pour le modèle donné

**Exceptions :** 
- `InvalidArgumentException` si la classe n'existe pas
- `InvalidArgumentException` si la classe n'implémente pas `MailAuthenticatable`

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use App\Models\User;

$service = MailAuthenticationService::for(User::class);
```

---

### `register(AbstractRecord $record): Model&Authenticatable`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `EmailRegisterAuthRecord` | Données d'inscription |

**Retourne :** `Model&Authenticatable` - L'utilisateur créé

**Exceptions :** 
- `InvalidArgumentException` si le record n'est pas du bon type
- `ValidationException` si les données de validation échouent

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$record = EmailRegisterAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]),
    'with_token' => true,
]);

$user = $service->register($record);
```

---

### `login(string $email, string $password): ?NemesisTokenRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |
| `$password` | `string` | Mot de passe en clair |

**Retourne :** `NemesisTokenRecord|null` - Record du token créé, ou `null` en cas d'échec

**Exemple :**
```php
<?php

declare(strict_types=1);

$token = $service->login('john@example.com', 'Password123!');

if ($token !== null) {
    // Le token est automatiquement stocké dans le cookie si configuré
    return response()->json(['token' => $token->token]);
}

return response()->json(['error' => 'Invalid credentials'], 401);
```

---

### `logout(Authenticatable&Model $authenticatable, string $plainToken): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | L'utilisateur authentifié |
| `$plainToken` | `string` | Le token en clair à révoquer |

**Retourne :** `bool` - `true` si la déconnexion a réussi, `false` sinon

**Exemple :**
```php
<?php

declare(strict_types=1);

use App\Models\User;

$user = User::find(1);
$result = $service->logout($user, 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...');

if ($result) {
    // Le cookie est automatiquement supprimé si configuré
    return response()->json(['message' => 'Logged out']);
}
```

---

### `sendPasswordResetOtp(string $email): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |

**Retourne :** `bool` - `true` si l'OTP a été envoyé, `false` sinon

**Exemple :**
```php
<?php

$success = $service->sendPasswordResetOtp('john@example.com');

if ($success) {
    return response()->json(['message' => 'Reset code sent']);
}
```

---

### `resetPassword(string $email, string $code, string $password): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |
| `$code` | `string` | Code OTP reçu |
| `$password` | `string` | Nouveau mot de passe |

**Retourne :** `bool` - `true` si le mot de passe a été réinitialisé

**Exemple :**
```php
<?php

$success = $service->resetPassword(
    'john@example.com',
    '123456',
    'NewPassword123!'
);

if ($success) {
    return response()->json(['message' => 'Password reset successful']);
}
```

---

### `sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | L'utilisateur à vérifier |

**Retourne :** `bool` - `true` si l'OTP a été envoyé ou si déjà vérifié

**Exemple :**
```php
<?php

use App\Models\User;

$user = User::find(1);
$success = $service->sendEmailVerificationOtp($user);

if ($success) {
    return response()->json(['message' => 'Verification code sent']);
}
```

---

### `verifyEmail(string $email, string $code): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |
| `$code` | `string` | Code OTP de vérification |

**Retourne :** `bool` - `true` si l'email a été vérifié

**Exemple :**
```php
<?php

$success = $service->verifyEmail('john@example.com', '654321');

if ($success) {
    return response()->json(['message' => 'Email verified']);
}
```

---

### `resendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | L'utilisateur à vérifier |

**Retourne :** `bool` - `true` si l'OTP a été renvoyé

**Exemple :**
```php
<?php

use App\Models\User;

$user = User::find(1);
$success = $service->resendEmailVerificationOtp($user);
```

---

### `isEmailVerified(Authenticatable&Model $authenticatable): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | L'utilisateur à vérifier |

**Retourne :** `bool` - `true` si l'email est vérifié

**Exemple :**
```php
<?php

use App\Models\User;

$user = User::find(1);
if ($service->isEmailVerified($user)) {
    // Accès autorisé
}
```

---

### `userExists(string $email): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email à vérifier |

**Retourne :** `bool` - `true` si l'utilisateur existe

**Exemple :**
```php
<?php

if ($service->userExists('john@example.com')) {
    return response()->json(['message' => 'User exists']);
}
```

## Cas d'utilisation

### Cas 1 : Inscription complète avec token et cookie

**Problème** : L'utilisateur s'inscrit et doit être automatiquement connecté sans étape supplémentaire.

**Solution** : Utiliser `with_token = true` et configurer le stockage des cookies.

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use App\Models\User;

// Configuration du cookie (config/authentication-kit.php)
'store_token_in_cookie' => true,

// Service
$service = MailAuthenticationService::for(User::class);

// Création du record
$record = EmailRegisterAuthRecord::from([
    'model_type' => User::class,
    'data' => StrictDataObject::from([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'SecurePass123!',
        'password_confirmation' => 'SecurePass123!',
    ]),
    'with_token' => true,
]);

// Inscription
$user = $service->register($record);

// Le token est automatiquement stocké dans le cookie
// L'utilisateur est connecté
return redirect()->to('/dashboard');
```

### Cas 2 : Connexion avec gestion d'erreurs

**Problème** : L'utilisateur tente de se connecter avec des identifiants invalides.

**Solution** : Gérer les différents cas d'échec.

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use App\Models\User;
use Illuminate\Http\Request;

class LoginController extends Controller
{
    public function login(Request $request)
    {
        $email = $request->input('email');
        $password = $request->input('password');

        $service = MailAuthenticationService::for(User::class);
        
        $token = $service->login($email, $password);

        if ($token === null) {
            // Vérification si l'utilisateur existe
            if (!$service->userExists($email)) {
                return response()->json([
                    'error' => 'User not found'
                ], 404);
            }

            // Si l'utilisateur existe, le mot de passe est incorrect
            return response()->json([
                'error' => 'Invalid password'
            ], 401);
        }

        // Connexion réussie
        return response()->json([
            'user' => $service->user,
            'token' => $token->token
        ]);
    }
}
```

### Cas 3 : Réinitialisation de mot de passe avec OTP

**Problème** : L'utilisateur a oublié son mot de passe et doit le réinitialiser en deux étapes.

**Solution** : Envoyer un OTP, puis valider et réinitialiser.

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use App\Models\User;

class PasswordResetController extends Controller
{
    public function requestReset(Request $request)
    {
        $email = $request->input('email');
        $service = MailAuthenticationService::for(User::class);
        
        // Étape 1 : Envoyer l'OTP
        $sent = $service->sendPasswordResetOtp($email);
        
        if (!$sent) {
            return response()->json([
                'error' => 'Unable to send reset code'
            ], 400);
        }
        
        return response()->json([
            'message' => 'Reset code sent to your email'
        ]);
    }
    
    public function confirmReset(Request $request)
    {
        $email = $request->input('email');
        $code = $request->input('code');
        $password = $request->input('password');
        
        $service = MailAuthenticationService::for(User::class);
        
        // Étape 2 : Valider l'OTP et réinitialiser
        $reset = $service->resetPassword($email, $code, $password);
        
        if (!$reset) {
            return response()->json([
                'error' => 'Invalid or expired reset code'
            ], 400);
        }
        
        return response()->json([
            'message' => 'Password reset successfully'
        ]);
    }
}
```

### Cas 4 : Vérification d'email en deux étapes

**Problème** : L'utilisateur doit vérifier son email avant d'accéder à certaines fonctionnalités.

**Solution** : Envoyer un OTP de vérification, puis valider.

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use App\Models\User;

class EmailVerificationController extends Controller
{
    public function sendVerification(Request $request)
    {
        $user = $request->user();
        $service = MailAuthenticationService::for(User::class);
        
        // Vérifier si déjà vérifié
        if ($service->isEmailVerified($user)) {
            return response()->json([
                'message' => 'Email already verified'
            ], 200);
        }
        
        // Envoyer l'OTP
        $sent = $service->sendEmailVerificationOtp($user);
        
        if (!$sent) {
            return response()->json([
                'error' => 'Unable to send verification code. Please try again later.'
            ], 429);
        }
        
        return response()->json([
            'message' => 'Verification code sent to your email'
        ]);
    }
    
    public function verify(Request $request)
    {
        $email = $request->input('email');
        $code = $request->input('code');
        
        $service = MailAuthenticationService::for(User::class);
        
        $verified = $service->verifyEmail($email, $code);
        
        if (!$verified) {
            return response()->json([
                'error' => 'Invalid or expired verification code'
            ], 400);
        }
        
        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    }
}
```

### Cas 5 : Personnalisation avec les hooks

**Problème** : Besoin d'ajouter des comportements spécifiques (logs, notifications, etc.).

**Solution** : Étendre le service et surcharger les hooks.

```php
<?php

declare(strict_types=1);

namespace App\Services;

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Records\NotificationMessageRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Mail;
use App\Mail\WelcomeEmail;
use App\Mail\PasswordChangedEmail;

class CustomAuthService extends MailAuthenticationService
{
    // ✅ Personnalisation des notifications
    protected function buildPasswordResetNotification(string $email, string $otp): NotificationMessageRecord
    {
        $html = view('emails.password-reset', [
            'email' => $email,
            'otp' => $otp,
            'expires_in' => 10,
        ])->render();

        return NotificationMessageRecord::from([
            'email' => $email,
            'subject' => '🔐 Réinitialisation de votre mot de passe - Afya Medical',
            'body' => $html,
        ]);
    }

    protected function buildEmailVerificationNotification(string $email, string $otp): NotificationMessageRecord
    {
        $html = view('emails.verify-email', [
            'email' => $email,
            'otp' => $otp,
            'expires_in' => 5,
        ])->render();

        return NotificationMessageRecord::from([
            'email' => $email,
            'subject' => '📧 Vérification de votre email - Afya Medical',
            'body' => $html,
        ]);
    }

    // ✅ Hook après inscription
    protected function afterRegister(Model&Authenticatable $user, AbstractRecord $record): void
    {
        // Envoyer un email de bienvenue
        Mail::to($user->email)->send(new WelcomeEmail($user));
        
        // Assigner un rôle par défaut
        if (method_exists($user, 'assignRole')) {
            $user->assignRole('user');
        }
        
        // Créer un profil
        $user->profile()->create([
            'bio' => $record->data->get('bio'),
            'phone' => $record->data->get('phone'),
        ]);
        
        // Logger l'événement
        \Log::info('New user registered', ['user_id' => $user->id, 'email' => $user->email]);
    }
    
    // ✅ Hook après connexion
    protected function afterLogin(Model&Authenticatable $user): void
    {
        // Mettre à jour la date de dernière connexion
        $user->last_login_at = now();
        $user->last_login_ip = request()->ip();
        $user->login_count = ($user->login_count ?? 0) + 1;
        $user->save();
        
        // Logger l'activité
        \Log::info('User logged in', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip()
        ]);
    }
    
    // ✅ Hook avant envoi d'OTP
    protected function beforeSendPasswordResetOtp(string $email): void
    {
        // Vérifier que l'email n'est pas banni
        if (BannedEmail::where('email', $email)->exists()) {
            throw new \Exception('This email address is banned');
        }
        
        // Vérifier le taux de demandes
        $attempts = Cache::get("reset_attempts_{$email}", 0);
        if ($attempts >= 5) {
            throw new \Exception('Too many reset attempts. Please try again later.');
        }
        
        Cache::increment("reset_attempts_{$email}", 60);
    }
    
    // ✅ Hook après réinitialisation de mot de passe
    protected function afterResetPassword(Model&Authenticatable $user): void
    {
        // Invalider tous les tokens existants
        $this->nemesis->revokeAllForUser($user);
        
        // Envoyer une notification
        Mail::to($user->email)->send(new PasswordChangedEmail($user));
        
        // Logger
        \Log::alert('Password reset', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => request()->ip()
        ]);
    }
    
    // ✅ Hook après vérification d'email
    protected function afterVerifyEmail(Model&Authenticatable $user): void
    {
        // Activer le compte
        $user->is_active = true;
        $user->save();
        
        // Envoyer une notification de bienvenue
        Mail::to($user->email)->send(new EmailVerifiedEmail($user));
        
        \Log::info('Email verified', [
            'user_id' => $user->id,
            'email' => $user->email
        ]);
    }
}

// Utilisation
$service = CustomAuthService::for(User::class);
```

## Flux d'exécution

### Inscription
```
EmailRegisterAuthRecord → Validation → Génération du modèle
                                    ↓
                            Création du token (si with_token)
                                    ↓
                            Stockage du cookie (si configuré)
                                    ↓
                            Log de succès
                                    ↓
                            Hooks afterRegister()
                                    ↓
                            Retour du modèle
```

### Connexion
```
Email + Password → Recherche de l'utilisateur
                ↓
        Vérification du mot de passe
                ↓
        Création du token via Nemesis
                ↓
        Stockage du cookie (si configuré)
                ↓
        Log de succès
                ↓
        Hook afterLogin()
                ↓
        Retour du token record
```

### Vérification d'email
```
Email + Code → Recherche de l'utilisateur
            ↓
    Vérification déjà effectuée ?
            ↓
    Vérification de l'OTP
            ↓
    Mise à jour de email_verified_at
            ↓
    Log de succès
            ↓
    Hook afterVerifyEmail()
            ↓
    Retour true
```

### Déconnexion
```
Plain Token → Recherche du token via Nemesis
            ↓
    Révocation du token
            ↓
    Suppression du cookie (si configuré)
            ↓
    Log de succès/échec
            ↓
    Hook afterLogout()
            ↓
    Retour booléen
```

## Template Method Pattern - Méthodes extensibles

Le service expose plusieurs méthodes protégées que vous pouvez surcharger :

| Méthode | Type | Description |
|---------|------|-------------|
| `buildPasswordResetNotification()` | Template | Construire la notification de réinitialisation |
| `buildEmailVerificationNotification()` | Template | Construire la notification de vérification email |
| `beforeRegister()` | Hook | Avant l'inscription |
| `afterRegister()` | Hook | Après l'inscription |
| `beforeLogin()` | Hook | Avant la connexion |
| `afterLogin()` | Hook | Après la connexion |
| `beforeLogout()` | Hook | Avant la déconnexion |
| `afterLogout()` | Hook | Après la déconnexion |
| `beforeSendPasswordResetOtp()` | Hook | Avant l'envoi OTP reset |
| `afterSendPasswordResetOtp()` | Hook | Après l'envoi OTP reset |
| `beforeResetPassword()` | Hook | Avant la réinitialisation |
| `afterResetPassword()` | Hook | Après la réinitialisation |
| `beforeVerifyEmail()` | Hook | Avant la vérification email |
| `afterVerifyEmail()` | Hook | Après la vérification email |

## Gestion des erreurs

| Situation | Exception / Log | Message |
|-----------|-----------------|---------|
| Modèle inexistant | `InvalidArgumentException` | `Model class {className} does not exist` |
| Modèle invalide | `InvalidArgumentException` | `Model {className} must implement MailAuthenticatable` |
| Record invalide | `InvalidArgumentException` | `Invalid record type` |
| Validation échouée | `ValidationException` | Messages de validation personnalisés |
| Rate limit dépassé | Log (`ErrorType::RATE_LIMIT_EXCEEDED`) | `Rate limit exceeded` |
| Utilisateur non trouvé | Log (`ErrorType::USER_NOT_FOUND`) | `User not found` |
| OTP invalide | Log (`ErrorType::INVALID_OTP`) | `Invalid or expired OTP` |
| Token non trouvé | Log (`ErrorType::TOKEN_NOT_FOUND`) | `Token not found` |
| Échec révocation | Log (`ErrorType::TOKEN_REVOKE_FAILED`) | `Failed to revoke token` |

## Intégration

### Avec les modèles Eloquent

Le modèle doit implémenter `MailAuthenticatable` :

```php
<?php

declare(strict_types=1);

namespace App\Models;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class User extends Model implements MailAuthenticatable
{
    protected $fillable = ['name', 'email', 'password', 'email_verified_at'];
    protected $hidden = ['password'];
    
    // Méthodes requises par MailAuthenticatable
    public static function getMailAuthService(): MailAuthenticationInterface
    {
        return MailAuthenticationService::for(self::class);
    }
    
    public function getEmailVerifiedAt(): ?DateTimeVO
    {
        return $this->email_verified_at 
            ? new DateTimeVO($this->email_verified_at->toIso8601String())
            : null;
    }
    
    public static function generate(array $data): Model&MailAuthenticatable
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8'],
        ]);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        return self::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
        ]);
    }
    
    public function nemesisFormat(): AbstractData
    {
        return new UserData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            emailVerifiedAt: $this->email_verified_at?->toIso8601String(),
            createdAt: $this->created_at?->toIso8601String(),
            updatedAt: $this->updated_at?->toIso8601String(),
        );
    }
}
```

### Avec la configuration

```php
// config/authentication-kit.php
return [
    'token_name' => 'my_auth_token',
    'password_reset_rate_limit' => 3, // Tentatives max
    'email_verification_rate_limit' => 5,
    'store_token_in_cookie' => true, // Stockage automatique
];
```

### Avec le middleware

```php
// routes/api.php
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Actions\GetCurrentUserAction;

Route::middleware(['validate.mail.authenticatable'])->group(function () {
    Route::post('/register', action_route(
        EmailRegisterRequest::class,
        EmailRegisterAction::class
    ));
    
    Route::post('/login', action_route(
        EmailLoginRequest::class,
        EmailLoginAction::class
    ));
});

Route::post('/me', action_route(
    EmptyRequest::class,
    GetCurrentUserAction::class
));
```

## Performance

- **Complexité** : O(1) pour la plupart des opérations
- **Base de données** : Une requête par opération principale
- **Caches** : Utilisation du cache Laravel pour les OTP
- **Tokens** : Génération en O(1), validation par hash SHA-256 en O(1)
- **Cookies** : Lecture/écriture sans impact significatif
- **Optimisation** : Les hooks sont optionnels et légers
- **Rate Limiting** : Géré par OtpService avec cache

## Compatibilité

| Version | Support | Détails |
|---------|---------|---------|
| PHP 8.1+ | ✅ Complet | Types génériques, énumérations |
| PHP 8.0 | ✅ Complet | Support complet |
| Laravel 12 | ✅ Complet | Framework supporté |
| Laravel 13 | ✅ Complet | Framework supporté |
| Laravel 14 | ✅ Complet | Framework supporté |
| Laravel 15 | ✅ Complet | Framework supporté |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLoginAuthRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AuthController extends Controller
{
    private MailAuthenticationService $service;
    
    public function __construct()
    {
        $this->service = MailAuthenticationService::for(User::class);
    }
    
    /**
     * Inscription d'un nouvel utilisateur
     */
    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8|confirmed',
        ]);
        
        $record = EmailRegisterAuthRecord::from([
            'model_type' => User::class,
            'data' => StrictDataObject::from($validated),
            'with_token' => true,
        ]);
        
        try {
            $user = $this->service->register($record);
            
            return response()->json([
                'user' => $user->nemesisFormat(),
                'message' => 'Registration successful'
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'errors' => $e->errors(),
                'message' => 'Validation failed'
            ], 422);
        }
    }
    
    /**
     * Connexion utilisateur
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);
        
        $token = $this->service->login(
            $validated['email'],
            $validated['password']
        );
        
        if ($token === null) {
            return response()->json([
                'error' => 'Invalid credentials'
            ], 401);
        }
        
        return response()->json([
            'token' => $token->token,
            'message' => 'Login successful'
        ]);
    }
    
    /**
     * Déconnexion
     */
    public function logout(Request $request)
    {
        $user = $request->user();
        $token = $request->bearerToken();
        
        if ($token === null) {
            return response()->json([
                'error' => 'Token required'
            ], 401);
        }
        
        $result = $this->service->logout($user, $token);
        
        if (!$result) {
            return response()->json([
                'error' => 'Logout failed'
            ], 500);
        }
        
        return response()->json([
            'message' => 'Logout successful'
        ]);
    }
    
    /**
     * Demande de réinitialisation de mot de passe
     */
    public function requestPasswordReset(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
        ]);
        
        $sent = $this->service->sendPasswordResetOtp($validated['email']);
        
        if (!$sent) {
            return response()->json([
                'error' => 'Unable to send reset code. Please try again later.'
            ], 429);
        }
        
        return response()->json([
            'message' => 'Reset code sent to your email'
        ]);
    }
    
    /**
     * Confirmation de réinitialisation de mot de passe
     */
    public function confirmPasswordReset(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
            'password' => 'required|min:8|confirmed',
        ]);
        
        $reset = $this->service->resetPassword(
            $validated['email'],
            $validated['code'],
            $validated['password']
        );
        
        if (!$reset) {
            return response()->json([
                'error' => 'Invalid or expired reset code'
            ], 400);
        }
        
        return response()->json([
            'message' => 'Password reset successful'
        ]);
    }
    
    /**
     * Vérification d'email
     */
    public function verifyEmail(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:6',
        ]);
        
        $verified = $this->service->verifyEmail(
            $validated['email'],
            $validated['code']
        );
        
        if (!$verified) {
            return response()->json([
                'error' => 'Invalid or expired verification code'
            ], 400);
        }
        
        return response()->json([
            'message' => 'Email verified successfully'
        ]);
    }
}
```

## Voir aussi

- `MailAuthenticatable` - Interface des modèles authentifiables
- `MailAuthenticationInterface` - Interface du service
- `ValidateMailAuthenticatableMiddleware` - Middleware de validation
- `LogRepository` - Gestion des logs d'authentification
- `NemesisInterface` - Gestion des tokens d'authentification
- `OtpService` - Gestion des OTP (One-Time Passwords)
- `CookieTokenStorageInterface` - Stockage des tokens dans les cookies
- `NotificationMessageRecord` - Record des notifications
- `ErrorType` - Types d'erreurs pour les logs