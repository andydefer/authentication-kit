# Authentication Kit - Documentation Complète

## 📖 Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Préparation du modèle](#préparation-du-modèle)
5. [Routes et API](#routes-et-api)
6. [Le service d'authentification](#le-service-dauthentication)
7. [Extension du service](#extension-du-service)
8. [Exemples d'utilisation](#exemples-dutilisation)
9. [Sécurité](#sécurité)
10. [Structure du package](#structure-du-package)

---

## 🎯 Introduction

**Authentication Kit** est un package PHP qui fournit un système d'authentification **headless**, **découplé du modèle** et **prêt à l'emploi**.

### L'idée principale

> Un système d'authentification qui ne connaît pas votre modèle.

Vous pouvez l'utiliser avec **n'importe quel modèle Eloquent** (User, Shop, CheckPoint, Admin, Client, Partner, etc.) sans avoir à réécrire la logique d'authentification.

### Pourquoi ce package ?

| Problème | Solution |
|----------|----------|
| Authentification liée à un seul modèle | ✅ Multi-modèles supportés |
| Code dupliqué pour chaque modèle | ✅ Service unique et générique |
| Pas de headless API | ✅ API-first, JSON uniquement |
| Dur à intégrer avec React/Kotlin/Swift | ✅ Routes REST standards |
| Pas de logging | ✅ Logging intégré |
| Pas de rate limiting | ✅ Rate limiting configurable |

---

## 🚀 Installation

```bash
composer require andydefer/authentication-kit
```

### Laravel

Le package s'enregistre automatiquement via `AuthenticationKitServiceProvider`.

### Publier la configuration

```bash
php artisan vendor:publish --tag=authentication-kit-config
```

### Publier les routes

```bash
php artisan vendor:publish --tag=authentication-kit-routes
```

---

## 🏗️ Préparation du modèle

**Donc votre modèle n'a besoin d'implémenter QUE `MailAuthenticatable`.**

### Interface `MailAuthenticatable`

```php
<?php

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;
use Illuminate\Database\Eloquent\Model;

interface MailAuthenticatable extends Authenticatable
{
    /**
     * Returns the authentication service instance.
     */
    public static function getMailAuthService(): MailAuthenticationInterface;

    /**
     * Gets the email verification timestamp.
     */
    public function getEmailVerifiedAt(): ?DateTimeVO;

    /**
     * Creates a new entity from validated data.
     */
    public static function generate(array $data): Model&Authenticatable;
}
```

### Exemple 1 : Modèle User

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
use Illuminate\Validation\ValidationException;

/**
 * User model with authentication capabilities.
 *
 * Implements MailAuthenticatable which extends Authenticatable → MustNemesis.
 */
final class User extends Model implements MailAuthenticatable
{
    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============================================================
    // MailAuthenticatable - méthodes requises
    // ============================================================

    /**
     * {@inheritDoc}
     */
    public static function getMailAuthService(): MailAuthenticationInterface
    {
        return MailAuthenticationService::for(self::class);
    }

    /**
     * {@inheritDoc}
     */
    public function getEmailVerifiedAt(): ?DateTimeVO
    {
        if ($this->email_verified_at === null) {
            return null;
        }

        return new DateTimeVO($this->email_verified_at->toIso8601String());
    }

    /**
     * {@inheritDoc}
     *
     * Crée un nouvel utilisateur à partir des données validées.
     * Le service a déjà validé email et password.
     */
    public static function generate(array $data): Model&MailAuthenticatable
    {
        // Validation des champs spécifiques au modèle
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'min:2', 'max:255'],
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

    // ============================================================
    // MustNemesis - format des données pour l'API
    // ============================================================

    /**
     * {@inheritDoc}
     */
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

### Data Object pour la réponse

```php
<?php

declare(strict_types=1);

namespace App\Models\Data;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

/**
 * Data transfer object for User API responses.
 */
final class UserData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $emailVerifiedAt,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}
}
```

### Exemple 2 : Modèle Shop (Boutique)

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
use Illuminate\Validation\ValidationException;

/**
 * Shop model with authentication capabilities.
 *
 * A shop can authenticate like a user, with its own specific fields.
 */
final class Shop extends Model implements MailAuthenticatable
{
    protected $table = 'shops';

    protected $fillable = [
        'name',
        'email',
        'password',
        'email_verified_at',
        'owner_name',
        'siret',
        'phone',
        'address',
        'is_active',
    ];

    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // ============================================================
    // MailAuthenticatable
    // ============================================================

    public static function getMailAuthService(): MailAuthenticationInterface
    {
        return MailAuthenticationService::for(self::class);
    }

    public function getEmailVerifiedAt(): ?DateTimeVO
    {
        if ($this->email_verified_at === null) {
            return null;
        }

        return new DateTimeVO($this->email_verified_at->toIso8601String());
    }

    /**
     * Crée une boutique à partir des données validées.
     *
     * Le service a déjà validé email et password.
     * Ici on valide les champs spécifiques à Shop.
     */
    public static function generate(array $data): Model&MailAuthenticatable
    {
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'min:2', 'max:255'],
            'owner_name' => ['required', 'string', 'min:2', 'max:255'],
            'siret' => ['required', 'string', 'size:14'],
            'phone' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return self::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'password' => Hash::make($data['password']),
            'owner_name' => $data['owner_name'],
            'siret' => $data['siret'],
            'phone' => $data['phone'],
            'address' => $data['address'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);
    }

    public function nemesisFormat(): AbstractData
    {
        return new ShopData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            ownerName: $this->owner_name,
            siret: $this->siret,
            phone: $this->phone,
            isActive: $this->is_active,
            emailVerifiedAt: $this->email_verified_at?->toIso8601String(),
            createdAt: $this->created_at?->toIso8601String(),
            updatedAt: $this->updated_at?->toIso8601String(),
        );
    }
}
```

### Data Object pour Shop

```php
<?php

declare(strict_types=1);

namespace App\Models\Data;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class ShopData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly string $ownerName,
        public readonly string $siret,
        public readonly string $phone,
        public readonly bool $isActive,
        public readonly ?string $emailVerifiedAt,
        public readonly ?string $createdAt,
        public readonly ?string $updatedAt,
    ) {}
}
```
---

## 🗺️ Routes et API

### Définition des routes

```php
<?php

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Actions\ResendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Actions\ResetPasswordAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendPasswordResetLinkAction;
use AndyDefer\AuthenticationKit\Mail\Actions\VerifyEmailAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLogoutRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\ResendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\ResetPasswordRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendPasswordResetLinkRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\VerifyEmailRequest;
use Illuminate\Support\Facades\Route;

/*
 * Public Authentication Routes
 */
Route::middleware(['validate.mail.authenticatable'])->group(function (): void {

    // Registration
    Route::post('/register', action_route(
        EmailRegisterRequest::class,
        EmailRegisterAction::class
    ))->name('register');

    // Login
    Route::post('/login', action_route(
        EmailLoginRequest::class,
        EmailLoginAction::class
    ))->name('login');

    // Password reset request
    Route::post('/forgot-password', action_route(
        SendPasswordResetLinkRequest::class,
        SendPasswordResetLinkAction::class
    ))->name('password.email');

    // Password reset confirmation
    Route::post('/reset-password', action_route(
        ResetPasswordRequest::class,
        ResetPasswordAction::class
    ))->name('password.update');

    // Email verification
    Route::post('/email/verify', action_route(
        VerifyEmailRequest::class,
        VerifyEmailAction::class
    ))->name('verification.verify');

    /*
     * Protected Authentication Routes
     * These routes require a valid Nemesis authentication token.
     */
    Route::middleware(['nemesis.token'])->group(function (): void {

        // Logout
        Route::post('/logout', action_route(
            EmailLogoutRequest::class,
            EmailLogoutAction::class
        ))->name('logout');

        // Send email verification OTP
        Route::post('/email/verification', action_route(
            SendEmailVerificationRequest::class,
            SendEmailVerificationAction::class
        ))->name('verification.send');

        // Resend email verification OTP
        Route::post('/email/resend', action_route(
            ResendEmailVerificationRequest::class,
            ResendEmailVerificationAction::class
        ))->name('verification.resend');
    });
});
```

---

## 📋 API Reference - Structure des requêtes et réponses

### 1. Inscription - `POST /register`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle |
| `with_token` | `boolean` | ❌ Non | Générer un token (défaut: false) |
| `*` | `mixed` | ❌ Non | Tous les autres champs sont passés au modèle |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "with_token": true,
    "name": "John Doe",
    "email": "john@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!"
}
```

**Réponse (201 Created) :**
```json
{
    "message": "Registration successful",
    "auth": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "emailVerifiedAt": null,
        "createdAt": "2024-01-01T10:00:00+00:00",
        "updatedAt": "2024-01-01T10:00:00+00:00"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Erreur (422) :**
```json
{
    "message": "The email field is required.",
    "status": 422,
    "errorCode": "VALIDATION_ERROR",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

---

### 2. Connexion - `POST /login`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle |
| `email` | `string` | ✅ Oui | Email de l'utilisateur |
| `password` | `string` | ✅ Oui | Mot de passe |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "email": "john@example.com",
    "password": "Password123!"
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Login successful",
    "auth": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "emailVerifiedAt": "2024-01-01T12:00:00+00:00",
        "createdAt": "2024-01-01T10:00:00+00:00",
        "updatedAt": "2024-01-01T12:00:00+00:00"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Erreur - Credentials manquants (400) :**
```json
{
    "message": "Email and password are required",
    "status": 400,
    "errorCode": "MISSING_CREDENTIALS",
    "errors": {
        "email": ["The email field is required."],
        "password": ["The password field is required."]
    }
}
```

**Erreur - Identifiants invalides (401) :**
```json
{
    "message": "Invalid credentials",
    "status": 401,
    "errorCode": "INVALID_CREDENTIALS"
}
```

---

### 3. Demande réinitialisation - `POST /forgot-password`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `email` | `string` | ✅ Oui | Email de l'utilisateur |

**Requête :**
```json
{
    "email": "john@example.com"
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Password reset OTP sent successfully",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00"
}
```

> 🔒 **Sécurité** : Retourne toujours 200, que l'utilisateur existe ou non.

---

### 4. Réinitialisation - `POST /reset-password`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle |
| `email` | `string` | ✅ Oui | Email de l'utilisateur |
| `token` | `string` | ✅ Oui | Code OTP |
| `password` | `string` | ✅ Oui | Nouveau mot de passe |
| `password_confirmation` | `string` | ✅ Oui | Confirmation |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "email": "john@example.com",
    "token": "123456",
    "password": "NewPassword123!",
    "password_confirmation": "NewPassword123!"
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Password reset successfully",
    "email": "john@example.com",
    "resetAt": "2024-01-01T12:00:00+00:00"
}
```

**Erreur - OTP invalide (400) :**
```json
{
    "message": "Invalid or expired reset OTP",
    "status": 400,
    "errorCode": "INVALID_RESET_OTP"
}
```

---

### 5. Vérification email - `POST /email/verify`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle |
| `email` | `string` | ✅ Oui | Email de l'utilisateur |
| `token` | `string` | ✅ Oui | Code OTP |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "email": "john@example.com",
    "token": "123456"
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Email verified successfully",
    "email": "john@example.com",
    "verifiedAt": "2024-01-01T12:00:00+00:00",
    "alreadyVerified": false
}
```

**Réponse - Déjà vérifié :**
```json
{
    "message": "Email already verified",
    "email": "john@example.com",
    "verifiedAt": "2024-01-01T10:00:00+00:00",
    "alreadyVerified": true
}
```

---

### 6. Envoi OTP vérification - `POST /email/verification`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle |
| `auth_id` | `integer` | ✅ Oui | ID de l'utilisateur |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "auth_id": 1
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Verification OTP sent successfully",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00"
}
```

---

### 7. Renvoi OTP vérification - `POST /email/resend`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle |
| `auth_id` | `integer` | ✅ Oui | ID de l'utilisateur |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "auth_id": 1
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Verification OTP resent successfully",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00"
}
```

---

### 8. Déconnexion - `POST /logout`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle |
| `token` | `string` | ✅ Oui | Token à révoquer |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Réponse (204 No Content)**

**Erreur - Token invalide (401) :**
```json
{
    "message": "Invalid token",
    "status": 401,
    "errorCode": "INVALID_TOKEN"
}
```
---

## 🧩 Le service d'authentification

### Qu'est-ce que `MailAuthenticationService` ?

C'est un service générique qui orchestre toute la logique d'authentification :

```php
$authService = MailAuthenticationService::for(User::class);
```

### Hooks extensibles

| Hook | Quand | Cas d'usage |
|------|-------|-------------|
| `beforeRegister()` | Avant inscription | IP check, anti-spam |
| `afterRegister()` | Après inscription | Email bienvenue, création profil |
| `beforeLogin()` | Avant connexion | Compte bloqué, 2FA |
| `afterLogin()` | Après connexion | Last login, sessions |
| `beforeLogout()` | Avant déconnexion | Journalisation |
| `afterLogout()` | Après déconnexion | Nettoyage sessions |
| `beforeSendPasswordResetOtp()` | Avant OTP reset | Vérification email |
| `afterSendPasswordResetOtp()` | Après OTP reset | Notification admin |
| `beforeResetPassword()` | Avant reset | Validation supplémentaire |
| `afterResetPassword()` | Après reset | Invalidation sessions |
| `beforeVerifyEmail()` | Avant vérif email | Vérifications supplémentaires |
| `afterVerifyEmail()` | Après vérif email | Activation compte |

---

## 🔧 Extension du service

### Exemple : Service personnalisé complet

```php
<?php

declare(strict_types=1);

namespace App\Services;

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Service d'authentification personnalisé avec des hooks métier.
 */
final class CustomAuthService extends MailAuthenticationService
{
    // ============================================================
    // HOOKS - Logique métier personnalisée
    // ============================================================

    /**
     * Vérification avant inscription.
     */
    protected function beforeRegister(AbstractRecord $record): void
    {
        // Vérifier si l'IP est bloquée
        if ($this->isIpBlocked($record->ip)) {
            throw new \RuntimeException('IP blocked due to suspicious activity');
        }

        // Vérifier si l'email est dans une liste noire
        $email = $record->data->get('email');
        if ($this->isEmailBlocked($email)) {
            throw new \RuntimeException('This email address is not allowed');
        }
    }

    /**
     * Après une inscription réussie.
     */
    protected function afterRegister(Model&Authenticatable $user, AbstractRecord $record): void
    {
        // 1. Envoyer un email de bienvenue
        $this->sendWelcomeEmail($user);

        // 2. Créer un profil utilisateur
        $user->profile()->create([
            'bio' => $record->data->get('bio'),
            'age' => $record->data->get('age'),
            'phone' => $record->data->get('phone'),
        ]);

        // 3. Attribuer un rôle par défaut
        $user->assignRole('user');


    }

    /**
     * Avant la connexion.
     */
    protected function beforeLogin(string $email, string $password): void
    {
        // Vérifier si le compte est verrouillé
        $user = $this->findUserByEmail($email);
        
        if ($user && $user->is_locked) {
            throw new \RuntimeException('Account is locked. Please contact support.');
        }

        // Vérifier si l'IP est autorisée
        if (! $this->isIpAllowed(request()->ip())) {
            throw new \RuntimeException('Access denied from this IP address');
        }
    }

    /**
     * Après une connexion réussie.
     */
    protected function afterLogin(Model&Authenticatable $user): void
    {
        // 1. Mettre à jour la dernière connexion
        $user->last_login_at = now();
        $user->login_count = ($user->login_count ?? 0) + 1;
        $user->save();

        // 2. Enregistrer la session
        $this->createUserSession($user);

        // 3. Nettoyer les tentatives échouées
        $this->clearFailedAttempts($user);
    }

    /**
     * Avant la déconnexion.
     */
    protected function beforeLogout(Authenticatable&Model $authenticatable, string $plainToken): void
    {
        // Journaliser la tentative
        Log::info('Tentative de déconnexion', [
            'user_id' => $authenticatable->id,
            'email' => $authenticatable->email,
        ]);
    }

    /**
     * Après une déconnexion réussie.
     */
    protected function afterLogout(Authenticatable&Model $authenticatable): void
    {
        // 1. Supprimer la session
        $this->clearUserSession($authenticatable);

        // 2. Journaliser
        Log::info('Déconnexion réussie', [
            'user_id' => $authenticatable->id,
            'email' => $authenticatable->email,
        ]);
    }

    /**
     * Avant l'envoi de l'OTP de réinitialisation.
     */
    protected function beforeSendPasswordResetOtp(string $email): void
    {
        $user = $this->findUserByEmail($email);
        
        if ($user && $user->prevent_password_reset) {
            throw new \RuntimeException('Password reset is not allowed for this account');
        }
    }

    /**
     * Après l'envoi de l'OTP de réinitialisation.
     */
    protected function afterSendPasswordResetOtp(string $email, bool $success): void
    {
        if (! $success) {
            // Notifier l'admin en cas d'échec
            $this->notifyAdmin('Password reset failed for: ' . $email);
        }
    }

    /**
     * Avant la réinitialisation du mot de passe.
     */
    protected function beforeResetPassword(string $email, string $code, string $password): void
    {
        // Valider que le mot de passe est assez fort
        if (strlen($password) < 12) {
            throw new \RuntimeException('Password must be at least 12 characters');
        }

        // Vérifier que le mot de passe n'est pas compromis
        if ($this->isPasswordCompromised($password)) {
            throw new \RuntimeException('This password has been compromised. Please choose another.');
        }
    }

    /**
     * Après une réinitialisation réussie.
     */
    protected function afterResetPassword(Model&Authenticatable $user): void
    {
        // 1. Invalider toutes les sessions
        $user->tokens()->delete();

        // 2. Notifier l'utilisateur
        $this->sendPasswordChangedNotification($user);

    }

    /**
     * Avant la vérification d'email.
     */
    protected function beforeVerifyEmail(string $email, string $code): void
    {
        $user = $this->findUserByEmail($email);
        
        if ($user && $user->email_verified_at !== null) {
            throw new \RuntimeException('Email already verified');
        }
    }

    /**
     * Après une vérification d'email réussie.
     */
    protected function afterVerifyEmail(Model&Authenticatable $user): void
    {
        // 1. Activer le compte
        $user->is_active = true;
        $user->save();

        // 2. Envoyer une notification
        $this->sendWelcomeVerificationNotification($user);
    }

    // ============================================================
    // MÉTHODES PRIVÉES UTILITAIRES
    // ============================================================

    private function findUserByEmail(string $email): ?Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;
        
        return $modelClass::where('email', $email)->first();
    }

    private function isIpBlocked(?string $ip): bool
    {
        // Logique de vérification d'IP
        $blockedIps = ['192.168.1.1', '10.0.0.1'];
        return in_array($ip, $blockedIps);
    }

    private function isEmailBlocked(?string $email): bool
    {
        $blockedDomains = ['spam.com', 'blocked.com'];
        $domain = substr(strrchr($email, '@'), 1);
        return in_array($domain, $blockedDomains);
    }

    private function isIpAllowed(): bool
    {
        // Vérification d'IP autorisée
        return true;
    }

    private function isPasswordCompromised(string $password): bool
    {
        // Vérification avec HaveIBeenPwned API
        return false;
    }

    private function sendWelcomeEmail(Model&Authenticatable $user): void
    {
        // Envoi d'email de bienvenue
    }

    private function sendWelcomeVerificationNotification(Model&Authenticatable $user): void
    {
        // Envoi de notification après vérification
    }

    private function sendPasswordChangedNotification(Model&Authenticatable $user): void
    {
        // Envoi de notification de changement de mot de passe
    }

    private function notifyAdmin(string $message): void
    {
        // Notification de l'admin
        Log::warning($message);
    }

    private function createUserSession(Model&Authenticatable $user): void
    {
        // Création de session
    }

    private function clearUserSession(Model&Authenticatable $user): void
    {
        // Nettoyage de session
    }

    private function clearFailedAttempts(Model&Authenticatable $user): void
    {
        // Nettoyage des tentatives échouées
    }
}
```

### Enregistrer le service personnalisé

```php
// Dans AppServiceProvider
use App\Services\CustomAuthService;

public function register(): void
{
    $this->app->bind(
        \AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticationInterface::class,
        CustomAuthService::class
    );
}
```

### Utiliser dans le modèle

```php
public static function getMailAuthService(): MailAuthenticationInterface
{
    return app(CustomAuthService::class);
}
```

---

## 📱 Exemples d'utilisation

### Exemple 1 : Laravel HTTP Client

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

class AuthService
{
    private const BASE_URL = 'http://localhost/api';
    private const MODEL_TYPE = 'App\\Models\\User';

    public function register(array $data): array
    {
        $response = Http::post(self::BASE_URL . '/register', [
            'model_type' => self::MODEL_TYPE,
            ...$data,
        ]);

        return $response->json();
    }

    public function login(string $email, string $password): array
    {
        $response = Http::post(self::BASE_URL . '/login', [
            'model_type' => self::MODEL_TYPE,
            'email' => $email,
            'password' => $password,
        ]);

        $data = $response->json();
        
        if ($response->successful()) {
            session(['auth_token' => $data['token']]);
        }

        return $data;
    }

    public function logout(): void
    {
        $token = session('auth_token');
        
        Http::withToken($token)->post(self::BASE_URL . '/logout', [
            'model_type' => self::MODEL_TYPE,
            'token' => $token,
        ]);

        session()->forget('auth_token');
    }

    public function forgotPassword(string $email): array
    {
        $response = Http::post(self::BASE_URL . '/forgot-password', [
            'email' => $email,
        ]);

        return $response->json();
    }

    public function resetPassword(string $email, string $token, string $password): array
    {
        $response = Http::post(self::BASE_URL . '/reset-password', [
            'model_type' => self::MODEL_TYPE,
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ]);

        return $response->json();
    }

    public function sendVerification(int $userId): array
    {
        $token = session('auth_token');

        $response = Http::withToken($token)->post(
            self::BASE_URL . '/email/verification',
            [
                'model_type' => self::MODEL_TYPE,
                'auth_id' => $userId,
            ]
        );

        return $response->json();
    }

    public function verifyEmail(string $email, string $token): array
    {
        $response = Http::post(self::BASE_URL . '/email/verify', [
            'model_type' => self::MODEL_TYPE,
            'email' => $email,
            'token' => $token,
        ]);

        return $response->json();
    }
}
```

---

### Exemple 2 : React / TypeScript

```typescript
// services/auth.service.ts

const API_URL = 'http://localhost/api';
const MODEL_TYPE = 'App\\Models\\User';

interface LoginResponse {
  message: string;
  auth: {
    id: number;
    name: string;
    email: string;
    emailVerifiedAt: string | null;
    createdAt: string;
    updatedAt: string;
  };
  token: string;
}

interface ErrorResponse {
  message: string;
  status: number;
  errorCode: string;
  errors?: Record<string, string[]>;
}

class AuthService {
  private token: string | null = null;

  constructor() {
    this.token = localStorage.getItem('auth_token');
  }

  async register(data: {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
  }): Promise<{ success: boolean; data?: any; error?: ErrorResponse }> {
    try {
      const response = await fetch(`${API_URL}/register`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
          with_token: true,
          ...data,
        }),
      });

      const result = await response.json();

      if (response.ok) {
        if (result.token) {
          localStorage.setItem('auth_token', result.token);
          this.token = result.token;
        }
        return { success: true, data: result };
      }

      return { success: false, error: result };
    } catch (error) {
      return { success: false, error: error as ErrorResponse };
    }
  }

  async login(email: string, password: string): Promise<{ success: boolean; data?: LoginResponse; error?: ErrorResponse }> {
    try {
      const response = await fetch(`${API_URL}/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
          email,
          password,
        }),
      });

      const result = await response.json();

      if (response.ok) {
        localStorage.setItem('auth_token', result.token);
        localStorage.setItem('user', JSON.stringify(result.auth));
        this.token = result.token;
        return { success: true, data: result };
      }

      return { success: false, error: result };
    } catch (error) {
      return { success: false, error: error as ErrorResponse };
    }
  }

  async logout(): Promise<void> {
    if (!this.token) return;

    try {
      await fetch(`${API_URL}/logout`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${this.token}`,
        },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
          token: this.token,
        }),
      });
    } finally {
      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      this.token = null;
    }
  }

  async forgotPassword(email: string): Promise<{ success: boolean; data?: any; error?: ErrorResponse }> {
    try {
      const response = await fetch(`${API_URL}/forgot-password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email }),
      });

      const result = await response.json();
      return { success: response.ok, data: result };
    } catch (error) {
      return { success: false, error: error as ErrorResponse };
    }
  }

  async resetPassword(
    email: string,
    token: string,
    password: string
  ): Promise<{ success: boolean; data?: any; error?: ErrorResponse }> {
    try {
      const response = await fetch(`${API_URL}/reset-password`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
          email,
          token,
          password,
          password_confirmation: password,
        }),
      });

      const result = await response.json();
      return { success: response.ok, data: result };
    } catch (error) {
      return { success: false, error: error as ErrorResponse };
    }
  }

  getToken(): string | null {
    return this.token;
  }

  isAuthenticated(): boolean {
    return !!this.token;
  }
}

export const authService = new AuthService();
```

### Composant Login React

```tsx
// components/Login.tsx

import React, { useState } from 'react';
import { authService } from '../services/auth.service';

interface LoginFormData {
  email: string;
  password: string;
}

const Login: React.FC = () => {
  const [formData, setFormData] = useState<LoginFormData>({
    email: '',
    password: '',
  });
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const { name, value } = e.target;
    setFormData(prev => ({ ...prev, [name]: value }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    const result = await authService.login(formData.email, formData.password);

    if (result.success) {
      // Rediriger vers le dashboard
      window.location.href = '/dashboard';
    } else {
      setError(result.error?.message || 'Erreur de connexion');
    }

    setLoading(false);
  };

  return (
    <div className="login-container">
      <form onSubmit={handleSubmit} className="login-form">
        <h1>Connexion</h1>
        
        {error && <div className="error">{error}</div>}

        <div className="form-group">
          <label htmlFor="email">Email</label>
          <input
            type="email"
            id="email"
            name="email"
            value={formData.email}
            onChange={handleChange}
            required
            placeholder="john@example.com"
          />
        </div>

        <div className="form-group">
          <label htmlFor="password">Mot de passe</label>
          <input
            type="password"
            id="password"
            name="password"
            value={formData.password}
            onChange={handleChange}
            required
            placeholder="••••••••"
          />
        </div>

        <button type="submit" disabled={loading}>
          {loading ? 'Connexion...' : 'Se connecter'}
        </button>
      </form>
    </div>
  );
};

export default Login;
```

---

## 🔒 Sécurité

| Fonctionnalité | Description | Valeur par défaut |
|----------------|-------------|-------------------|
| **Rate Limiting** | Nombre de tentatives par période | 3 (reset) / 5 (vérification) |
| **OTP Expiration** | Durée de validité d'un OTP | 5 min (email) / 10 min (password) |
| **OTP Max Attempts** | Nombre de tentatives par OTP | 3 |
| **Token Hash** | Algorithme de hachage des tokens | SHA-256 |
| **Réponse /forgot-password** | Ne révèle pas l'existence de l'utilisateur | Toujours 200 |
| **Logs** | Protection des données sensibles | Pas de logs pour emails inexistants |

---
