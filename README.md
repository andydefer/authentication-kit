# Authentication Kit - Documentation Complète

## 📖 Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Préparation du modèle](#préparation-du-modèle)
5. [Routes et API](#routes-et-api)
6. [API Reference](#api-reference)
7. [Le service d'authentification](#le-service-dauthentication)
8. [Extension du service](#extension-du-service)
9. [Actions internes](#actions-internes)
10. [Logs et journalisation](#logs-et-journalisation)
11. [Gestion des erreurs](#gestion-des-erreurs)
12. [Exemples d'utilisation](#exemples-dutilisation)
13. [Sécurité](#sécurité)
14. [Migration de versions](#migration-de-versions)
15. [Structure du package](#structure-du-package)

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
| Tokens uniquement en Bearer | ✅ Support Bearer + Cookies |
| Pas d'endpoint utilisateur courant | ✅ Route `/me` intégrée |

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

## ⚙️ Configuration

### Fichier de configuration

```php
// config/authentication-kit.php
return [
    /**
     * Nom du token d'authentification
     * Utilisé comme nom du cookie et pour l'identification des tokens
     */
    'token_name' => env('AUTH_KIT_TOKEN_NAME', 'authentication-kit'),

    /**
     * Limite de taux pour la réinitialisation de mot de passe
     * Nombre de tentatives autorisées par période
     */
    'password_reset_rate_limit' => env('AUTH_KIT_PASSWORD_RESET_RATE_LIMIT', 3),

    /**
     * Limite de taux pour la vérification d'email
     * Nombre de tentatives autorisées par période
     */
    'email_verification_rate_limit' => env('AUTH_KIT_EMAIL_VERIFICATION_RATE_LIMIT', 5),

    /**
     * Stockage du token dans un cookie
     * Si true, le token est automatiquement stocké dans un cookie après login/register
     * Utile pour les applications web avec sessions
     */
    'store_token_in_cookie' => env('AUTH_KIT_STORE_TOKEN_IN_COOKIE', true),
];
```

### Variables d'environnement

```env
# .env
AUTH_KIT_TOKEN_NAME=my_auth_token
AUTH_KIT_PASSWORD_RESET_RATE_LIMIT=3
AUTH_KIT_EMAIL_VERIFICATION_RATE_LIMIT=5
AUTH_KIT_STORE_TOKEN_IN_COOKIE=true
```

### Configuration des cookies (Nemesis)

```php
// config/nemesis.php
'web' => [
    'login_route' => '/login',
    'dashboard_route' => '/dashboard',
    'cookie_name' => 'auth_token',          // Nom du cookie
    'cookie_secure' => env('COOKIE_SECURE', true),    // HTTPS uniquement
    'cookie_httponly' => true,               // Non accessible en JS
    'cookie_samesite' => 'lax',              // Protection CSRF
],
```

---

## 🏗️ Préparation du modèle

**Votre modèle n'a besoin d'implémenter QUE `MailAuthenticatable`.**

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

    /**
     * Formats the entity for API responses.
     * Cette méthode est requise par l'interface MustNemesis.
     */
    public function nemesisFormat(): AbstractData;
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

    protected $hidden = ['password'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

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

---

## 🗺️ Routes et API

### Définition des routes

```php
<?php

use AndyDefer\Actions\Http\Requests\EmptyRequest;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Actions\GetCurrentUserAction;
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
 * Routes publiques - Authentification par email
 */
Route::middleware(['validate.mail.authenticatable'])->group(function (): void {

    // Inscription
    Route::post('/register', action_route(
        EmailRegisterRequest::class,
        EmailRegisterAction::class
    ))->name('register');

    // Connexion
    Route::post('/login', action_route(
        EmailLoginRequest::class,
        EmailLoginAction::class
    ))->name('login');

    // Demande de réinitialisation de mot de passe
    Route::post('/forgot-password', action_route(
        SendPasswordResetLinkRequest::class,
        SendPasswordResetLinkAction::class
    ))->name('password.email');

    // Confirmation de réinitialisation
    Route::post('/reset-password', action_route(
        ResetPasswordRequest::class,
        ResetPasswordAction::class
    ))->name('password.update');

    // Vérification d'email
    Route::post('/email/verify', action_route(
        VerifyEmailRequest::class,
        VerifyEmailAction::class
    ))->name('verification.verify');

    /*
     * Routes protégées - Nécessitent un token d'authentification
     */
    Route::middleware(['nemesis.token'])->group(function (): void {

        // Déconnexion
        Route::post('/logout', action_route(
            EmailLogoutRequest::class,
            EmailLogoutAction::class
        ))->name('logout');

        // Envoi OTP de vérification email
        Route::post('/email/verification', action_route(
            SendEmailVerificationRequest::class,
            SendEmailVerificationAction::class
        ))->name('verification.send');

        // Renvoi OTP de vérification email
        Route::post('/email/resend', action_route(
            ResendEmailVerificationRequest::class,
            ResendEmailVerificationAction::class
        ))->name('verification.resend');
    });
});

/*
 * Route de l'utilisateur courant
 * Supporte à la fois Bearer token et Cookie
 * Pas de middleware requis - l'action gère elle-même l'authentification
 */
Route::post('/me', action_route(
    EmptyRequest::class,
    GetCurrentUserAction::class
))->name('me');
```

### Tableau récapitulatif des routes

| Route | Méthode | Auth | Support Cookie | Description |
|-------|---------|------|----------------|-------------|
| `/register` | POST | ❌ | ✅ (si with_token) | Inscription utilisateur |
| `/login` | POST | ❌ | ✅ (si configuré) | Connexion |
| `/logout` | POST | ✅ | ✅ (supprime cookie) | Déconnexion |
| `/me` | POST | ✅ | ✅ | Utilisateur courant |
| `/forgot-password` | POST | ❌ | ❌ | Demande reset OTP |
| `/reset-password` | POST | ❌ | ❌ | Réinitialisation |
| `/email/verify` | POST | ❌ | ❌ | Vérification email |
| `/email/verification` | POST | ✅ | ❌ | Envoi OTP vérification |
| `/email/resend` | POST | ✅ | ❌ | Renvoi OTP vérification |

---

## 🍪 Support des cookies

Le package supporte le stockage automatique des tokens d'authentification dans les cookies, rendant l'intégration avec les applications web plus fluide.

### Configuration

```php
// config/authentication-kit.php
'store_token_in_cookie' => env('AUTH_KIT_STORE_TOKEN_IN_COOKIE', true),
```

### Comment ça fonctionne

| Événement | Comportement |
|-----------|--------------|
| **Connexion** | Le token est automatiquement stocké dans un cookie sécurisé |
| **Inscription avec token** | Le cookie est défini si `with_token = true` |
| **Déconnexion** | Le cookie est automatiquement supprimé |
| **Requête `/me`** | Le token est lu depuis le cookie si pas de Bearer token |

### Priorité d'authentification

1. **Bearer token** (header `Authorization`) - **Prioritaire**
2. **Cookie token** - Utilisé si aucun Bearer token n'est présent

```php
// Exemple : Priorité Bearer > Cookie
// Headers: Authorization: Bearer token-api
// Cookie: auth_token=token-cookie
// → Utilise le Bearer token
```

### Configuration des cookies

```php
// config/nemesis.php
'web' => [
    'login_route' => '/login',
    'dashboard_route' => '/dashboard',
    'cookie_name' => 'auth_token',          // Nom du cookie
    'cookie_secure' => env('COOKIE_SECURE', true),    // HTTPS uniquement
    'cookie_httponly' => true,               // Non accessible en JS
    'cookie_samesite' => 'lax',              // Protection CSRF
],
```

---

## 📋 API Reference

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
        "createdAt": "2026-08-14T10:00:00+00:00",
        "updatedAt": "2026-08-14T10:00:00+00:00"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

**Erreur (422) :**
```json
{
    "message": "Validation error",
    "status": 422,
    "errorCode": "VALIDATION_ERROR",
    "errors": {
        "email": ["The email has already been taken."]
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
        "emailVerifiedAt": "2026-08-14T12:00:00+00:00",
        "createdAt": "2026-08-14T10:00:00+00:00",
        "updatedAt": "2026-08-14T12:00:00+00:00"
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

### 3. Utilisateur courant - `POST /me`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ❌ Non | Optionnel, non utilisé par l'action |

**Requête avec Bearer Token :**
```
POST /me
Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Requête avec Cookie :**
```
POST /me
Cookie: auth_token=eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...
```

**Réponse (200 OK) :**
```json
{
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "emailVerifiedAt": "2026-08-14T12:00:00+00:00",
    "createdAt": "2026-08-14T10:00:00+00:00",
    "updatedAt": "2026-08-14T12:00:00+00:00"
}
```

**Erreur - Non authentifié (401) :**
```json
{
    "message": "Unauthenticated",
    "status": 401,
    "errorCode": "UNAUTHENTICATED"
}
```

---

### 4. Déconnexion - `POST /logout`

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

**Erreur - Token expiré (401) :**
```json
{
    "message": "Token has expired",
    "status": 401,
    "errorCode": "TOKEN_EXPIRED"
}
```

---

### 5. Demande réinitialisation - `POST /forgot-password`

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
    "sentAt": "2026-08-14T12:00:00+00:00"
}
```

> 🔒 **Sécurité** : Retourne toujours 200, que l'utilisateur existe ou non.

---

### 6. Réinitialisation - `POST /reset-password`

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
    "resetAt": "2026-08-14T12:00:00+00:00"
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

### 7. Vérification email - `POST /email/verify`

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
    "verifiedAt": "2026-08-14T12:00:00+00:00",
    "alreadyVerified": false
}
```

**Réponse - Déjà vérifié :**
```json
{
    "message": "Email already verified",
    "email": "john@example.com",
    "verifiedAt": "2026-08-14T10:00:00+00:00",
    "alreadyVerified": true
}
```

---

### 8. Envoi OTP vérification - `POST /email/verification`

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
    "sentAt": "2026-08-14T12:00:00+00:00"
}
```

---

### 9. Renvoi OTP vérification - `POST /email/resend`

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
    "sentAt": "2026-08-14T12:00:00+00:00"
}
```

---

## 🧩 Le service d'authentification

### Qu'est-ce que `MailAuthenticationService` ?

C'est un service générique qui orchestre toute la logique d'authentification :

```php
$authService = MailAuthenticationService::for(User::class);
```

### Méthodes publiques

| Méthode | Description |
|---------|-------------|
| `register(AbstractRecord $record)` | Crée un nouvel utilisateur |
| `login(string $email, string $password)` | Authentifie un utilisateur |
| `logout(Authenticatable&Model $user, string $token)` | Révoque un token |
| `sendPasswordResetOtp(string $email)` | Envoie un OTP de réinitialisation |
| `resetPassword(string $email, string $code, string $password)` | Réinitialise le mot de passe |
| `sendEmailVerificationOtp(Authenticatable $user)` | Envoie un OTP de vérification |
| `verifyEmail(string $email, string $code)` | Vérifie l'email |
| `resendEmailVerificationOtp(Authenticatable $user)` | Renvoie un OTP de vérification |
| `isEmailVerified(Authenticatable $user)` | Vérifie si l'email est vérifié |
| `userExists(string $email)` | Vérifie l'existence d'un utilisateur |

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

        // 4. Logger
        Log::info('New user registered', ['user_id' => $user->id]);
    }

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

        // 4. Logger
        Log::info('User logged in', ['user_id' => $user->id]);
    }

    protected function beforeLogout(Authenticatable&Model $authenticatable, string $plainToken): void
    {
        Log::info('Logout attempt', ['user_id' => $authenticatable->id]);
    }

    protected function afterLogout(Authenticatable&Model $authenticatable): void
    {
        // 1. Supprimer la session
        $this->clearUserSession($authenticatable);

        // 2. Journaliser
        Log::info('Logout successful', ['user_id' => $authenticatable->id]);
    }

    protected function beforeSendPasswordResetOtp(string $email): void
    {
        $user = $this->findUserByEmail($email);
        
        if ($user && $user->prevent_password_reset) {
            throw new \RuntimeException('Password reset is not allowed for this account');
        }
    }

    protected function afterSendPasswordResetOtp(string $email, bool $success): void
    {
        if (! $success) {
            $this->notifyAdmin('Password reset failed for: ' . $email);
        }
    }

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

    protected function afterResetPassword(Model&Authenticatable $user): void
    {
        // 1. Invalider toutes les sessions
        $user->tokens()->delete();

        // 2. Notifier l'utilisateur
        $this->sendPasswordChangedNotification($user);

        // 3. Logger
        Log::alert('Password reset', ['user_id' => $user->id]);
    }

    protected function beforeVerifyEmail(string $email, string $code): void
    {
        $user = $this->findUserByEmail($email);
        
        if ($user && $user->email_verified_at !== null) {
            throw new \RuntimeException('Email already verified');
        }
    }

    protected function afterVerifyEmail(Model&Authenticatable $user): void
    {
        // 1. Activer le compte
        $user->is_active = true;
        $user->save();

        // 2. Envoyer une notification
        $this->sendWelcomeVerificationNotification($user);

        // 3. Logger
        Log::info('Email verified', ['user_id' => $user->id]);
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
        $blockedIps = ['192.168.1.1', '10.0.0.1'];
        return in_array($ip, $blockedIps);
    }

    private function isEmailBlocked(?string $email): bool
    {
        $blockedDomains = ['spam.com', 'blocked.com'];
        $domain = substr(strrchr($email, '@'), 1);
        return in_array($domain, $blockedDomains);
    }

    private function isIpAllowed(string $ip): bool
    {
        return true;
    }

    private function isPasswordCompromised(string $password): bool
    {
        return false;
    }

    private function sendWelcomeEmail(Model&Authenticatable $user): void {}
    private function sendWelcomeVerificationNotification(Model&Authenticatable $user): void {}
    private function sendPasswordChangedNotification(Model&Authenticatable $user): void {}
    private function notifyAdmin(string $message): void {}
    private function createUserSession(Model&Authenticatable $user): void {}
    private function clearUserSession(Model&Authenticatable $user): void {}
    private function clearFailedAttempts(Model&Authenticatable $user): void {}
}
```

---

## ⚙️ Actions internes

Le package utilise le pattern **Action** pour organiser la logique métier de manière modulaire et testable.

### Structure d'une Action

```php
abstract class AbstractAction
{
    // Préparation - validation des données
    protected function before(AbstractRecord $record): void {}
    
    // Traitement principal
    protected function handle(AbstractRecord $record): ResponseFactory {}
    
    // Nettoyage et journalisation
    protected function after(bool $success, ?Exception $error, AbstractRecord $record): void {}
}
```

### Actions disponibles

| Action | Description | Record | Auth |
|--------|-------------|--------|------|
| `EmailRegisterAction` | Inscription utilisateur | `EmailRegisterAuthRecord` | ❌ |
| `EmailLoginAction` | Connexion utilisateur | `EmailLoginAuthRecord` | ❌ |
| `EmailLogoutAction` | Déconnexion utilisateur | `EmailLogoutAuthRecord` | ✅ |
| `GetCurrentUserAction` | Récupération utilisateur courant | `EmptyRequest` | ✅ |
| `SendPasswordResetLinkAction` | Envoi OTP de réinitialisation | `SendPasswordResetLinkRecord` | ❌ |
| `ResetPasswordAction` | Réinitialisation mot de passe | `ResetPasswordRecord` | ❌ |
| `SendEmailVerificationAction` | Envoi OTP de vérification | `SendEmailVerificationRecord` | ✅ |
| `ResendEmailVerificationAction` | Renvoi OTP de vérification | `ResendEmailVerificationRecord` | ✅ |
| `VerifyEmailAction` | Vérification email | `VerifyEmailRecord` | ❌ |

### Flux d'exécution d'une Action

```
Requête entrante (Record)
    ↓
1. before() - Validation et préparation
    ↓
2. handle() - Traitement principal
    ├── Succès → Réponse positive
    └── Échec → ErrorResponseData
    ↓
3. after() - Journalisation
    ├── Succès → logSuccess()
    └── Échec → logFailure()
```

### Exemple : EmailLoginAction

```php
final class EmailLoginAction extends AbstractAction
{
    protected function before(AbstractRecord $record): void
    {
        // Extrait les données du record
        $this->modelClass = $record->model_type;
        $this->ip = $record->ip;
        $this->userAgent = $record->user_agent;
    }

    protected function handle(AbstractRecord $record): ResponseFactory
    {
        // 1. Validation des identifiants
        $email = $record->data->get('email');
        $password = $record->data->get('password');
        
        if ($email === null || $password === null) {
            return $this->errorResponse(ErrorCode::MISSING_CREDENTIALS);
        }

        // 2. Tentative de connexion via le service
        $service = $this->modelClass::getMailAuthService();
        $token = $service->login($email, $password);

        if ($token === null) {
            return $this->errorResponse(ErrorCode::INVALID_CREDENTIALS);
        }

        // 3. Création du token via Nemesis
        [$tokenModel, $plainToken] = $this->nemesis->createWithPlainToken(
            new NemesisTokenRecord(...),
            $authenticatable
        );

        // 4. Réponse de succès
        return ResponseFactory::json(new AuthLoginData(...), 200);
    }

    protected function after(bool $success, ?Exception $error, AbstractRecord $record): void
    {
        if ($this->success) {
            $this->logRepository->loginSuccess(...);
        } else {
            $this->logRepository->loginFailure(...);
        }
    }
}
```

### Middleware associé

Le package fournit un middleware qui valide automatiquement le champ `model_type` :

```php
// ValidateMailAuthenticatableMiddleware

public function handle(Request $request, Closure $next): Response
{
    $modelType = $request->input('model_type');

    // 1. Validation de la présence
    if ($modelType === null) {
        return $this->errorResponse('MODEL_TYPE_REQUIRED', 400);
    }

    // 2. Validation de l'existence
    if (! class_exists($modelType)) {
        return $this->errorResponse('MODEL_NOT_FOUND', 500);
    }

    // 3. Validation de l'interface
    if (! in_array(MailAuthenticatable::class, class_implements($modelType))) {
        return $this->errorResponse('INVALID_MODEL', 500);
    }

    // 4. Liaison du service
    app()->bind(MailAuthenticationInterface::class, function () use ($modelType) {
        return MailAuthenticationService::for($modelType);
    });

    // 5. Exécution de la requête
    $response = $next($request);

    // 6. Ajout des cookies si configuré
    if ($this->config->shouldStoreTokenInCookie()) {
        foreach (Cookie::getQueuedCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }

    return $response;
}
```

---

## 📊 Logs et journalisation

### Événements journalisés

| Événement | Méthode | Données |
|-----------|---------|---------|
| Inscription réussie | `logRegistrationSuccess()` | authId, modelClass, withToken |
| Inscription échouée | `logRegistrationFailure()` | modelClass, error, errorType |
| Connexion réussie | `loginSuccess()` | authId, modelClass, email |
| Connexion échouée | `loginFailure()` | modelClass, email, error, errorType |
| Déconnexion réussie | `logoutSuccess()` | authId, modelClass, email |
| Déconnexion échouée | `logoutFailure()` | modelClass, email, error, errorType |
| Reset envoyé | `logPasswordResetLinkSent()` | email, success, error |
| Reset échoué | `logPasswordResetFailure()` | email, error, errorType |
| Vérification réussie | `logVerificationSuccess()` | email, modelClass, alreadyVerified |
| Vérification échouée | `logVerificationFailure()` | email, modelClass, error, errorType |

### Structure des logs

```json
{
    "event": "user_login_success",
    "auth_id": 1,
    "model_type": "App\\Models\\User",
    "email": "john@example.com",
    "timestamp": "2026-08-14T10:00:00+00:00",
    "ip": "192.168.1.100",
    "user_agent": "Mozilla/5.0 ..."
}
```

### Implémentation personnalisée du LogRepository

```php
<?php

use AndyDefer\AuthenticationKit\Mail\Contracts\Repositories\LogRepositoryInterface;
use AndyDefer\AuthenticationKit\Enums\ErrorType;

class CustomLogRepository implements LogRepositoryInterface
{
    public function logRegistrationSuccess(
        int $authId,
        string $modelClass,
        bool $withToken,
    ): void {
        \Log::info('User registered', [
            'auth_id' => $authId,
            'model_class' => $modelClass,
            'with_token' => $withToken,
        ]);
    }

    public function logRegistrationFailure(
        string $modelClass,
        string $error,
        ErrorType $errorType,
    ): void {
        \Log::warning('Registration failed', [
            'model_class' => $modelClass,
            'error' => $error,
            'error_type' => $errorType->value,
        ]);
    }

    public function loginFailure(
        string $modelClass,
        string $email,
        string $error,
        ErrorType $errorType,
    ): void {
        \Log::warning('Login failed', [
            'model_class' => $modelClass,
            'email' => $email,
            'error' => $error,
            'error_type' => $errorType->value,
        ]);
    }

    // ... autres méthodes
}
```

---

## 🏷️ Gestion des erreurs

### ErrorCode (Réponses API)

Le package utilise l'énumération `ErrorCode` pour standardiser les réponses d'erreur.

| Code | HTTP | Description |
|------|------|-------------|
| `INVALID_RECORD_TYPE` | 500 | Type de record invalide |
| `MISSING_CREDENTIALS` | 400 | Identifiants manquants |
| `INVALID_CREDENTIALS` | 401 | Identifiants invalides |
| `AUTHENTICATABLE_NOT_FOUND` | 401 | Utilisateur non trouvé |
| `INVALID_TOKEN` | 401 | Token invalide |
| `TOKEN_EXPIRED` | 401 | Token expiré |
| `VALIDATION_ERROR` | 422 | Erreur de validation |
| `MODEL_NOT_FOUND` | 500 | Modèle introuvable |
| `INVALID_MODEL` | 500 | Modèle invalide |
| `REGISTRATION_ERROR` | 500 | Erreur d'inscription |
| `LOGIN_ERROR` | 500 | Erreur de connexion |
| `LOGOUT_FAILED` | 500 | Échec de déconnexion |
| `LOGOUT_EXCEPTION` | 500 | Exception lors de la déconnexion |
| `USER_FETCH_ERROR` | 500 | Erreur de récupération utilisateur |
| `VERIFICATION_OTP_RESEND_FAILED` | 500 | Échec renvoi OTP |
| `INVALID_RESET_OTP` | 400 | OTP de réinitialisation invalide |
| `RESET_PASSWORD_ERROR` | 500 | Erreur de réinitialisation |

### ErrorType (Logs)

L'énumération `ErrorType` est utilisée pour les logs, offrant une meilleure analyse.

| Type | Description |
|------|-------------|
| `user_not_found` | Utilisateur non trouvé |
| `invalid_credentials` | Identifiants invalides |
| `invalid_otp` | OTP invalide |
| `rate_limit_exceeded` | Limite de taux dépassée |
| `token_not_found` | Token non trouvé |
| `token_revoke_failed` | Échec de révocation |
| `validation_error` | Erreur de validation |
| `account_locked` | Compte verrouillé |
| `email_already_verified` | Email déjà vérifié |
| `invalid_email` | Email invalide |
| `password_too_weak` | Mot de passe trop faible |
| `invalid_token` | Token invalide |
| `token_expired` | Token expiré |
| `invalid_record_type` | Type de record invalide |
| `missing_credentials` | Identifiants manquants |

### Structure des erreurs

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
| **Cookies** | HttpOnly, Secure, SameSite | Configurables |
| **Token Storage** | Hashé en base, jamais stocké en clair | SHA-256 |

---

## 🧪 Tests

### Configuration des tests

```php
// tests/TestCase.php
use AndyDefer\AuthenticationKit\Tests\IntegrationTestCase;

class YourTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Configuration pour les tests
        $this->app['config']->set('authentication-kit.store_token_in_cookie', false);
        $this->app['config']->set('nemesis.web.cookie_name', 'nemesis_token');
    }
}
```

### Exemple de test complet

```php
<?php

use AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Models\TestUserMail;

final class LoginTest extends IntegrationTestCase
{
    public function test_login_success(): void
    {
        // 1. Créer un utilisateur de test
        $user = TestUserMail::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        // 2. Requête de connexion
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/login', $payload);

        // 3. Assertions
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'auth' => ['id', 'name', 'email'],
            'token',
        ]);
    }

    public function test_login_with_cookie(): void
    {
        // 1. Configuration cookie
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        // 2. Créer un utilisateur
        $user = TestUserMail::create([
            'name' => 'Cookie User',
            'email' => 'cookie@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        // 3. Requête de connexion
        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'cookie@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/login', $payload);

        // 4. Vérifier le cookie
        $response->assertStatus(200);
        $response->assertCookie('nemesis_token');
    }

    public function test_register_with_token(): void
    {
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
    }

    public function test_me_endpoint(): void
    {
        // 1. Login pour obtenir un token
        $loginPayload = [
            'model_type' => TestUserMail::class,
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];
        $loginResponse = $this->postJson('/api/login', $loginPayload);
        $token = $loginResponse->json('token');

        // 2. Requête /me
        $response = $this->postJson('/api/me', [
            'model_type' => TestUserMail::class,
        ], [
            'Authorization' => 'Bearer '.$token,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['email' => 'test@example.com']);
    }

    public function test_me_endpoint_with_cookie(): void
    {
        // 1. Configuration et login avec cookie
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $loginPayload = [
            'model_type' => TestUserMail::class,
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];
        $loginResponse = $this->postJson('/api/login', $loginPayload);
        $cookieValue = $this->getCookieValue($loginResponse, 'nemesis_token');

        // 2. Requête /me avec cookie
        $response = $this->call('POST', '/api/me', [
            'model_type' => TestUserMail::class,
        ], [
            'nemesis_token' => $cookieValue,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['email' => 'test@example.com']);
    }
}
```

---

## 🔄 Migration de versions

### De v1.11.0 à v1.13.1

#### 1. Renommage du middleware

**Avant :**
```php
use AndyDefer\AuthenticationKit\Mail\Http\Middleware\ValidateMailAuthenticatable;
```

**Après :**
```php
use AndyDefer\AuthenticationKit\Mail\Http\Middleware\ValidateMailAuthenticatableMiddleware;
```

#### 2. Interface LogRepositoryInterface

**Avant :**
```php
public function loginFailure(
    string $modelClass,
    string $email,
    string $error,
    string $errorClass,
): void;
```

**Après :**
```php
public function loginFailure(
    string $modelClass,
    string $email,
    string $error,
    ErrorType $errorType,
): void;
```

#### 3. Nouvelle configuration

Ajouter à `config/authentication-kit.php` :
```php
'store_token_in_cookie' => env('AUTH_KIT_STORE_TOKEN_IN_COOKIE', true),
```

#### 4. Nouvelle interface MustNemesis

Le modèle doit maintenant implémenter `nemesisFormat()` :

```php
public function nemesisFormat(): AbstractData
{
    return new YourData(
        id: $this->id,
        email: $this->email,
        // ...
    );
}
```

#### 5. Nouvelle route

Ajouter la route `/me` dans vos routes :

```php
Route::post('/me', action_route(
    EmptyRequest::class,
    GetCurrentUserAction::class
))->name('me');
```

---

## 📦 Structure du package

```
src/
├── Mail/
│   ├── Actions/
│   │   ├── EmailLoginAction.php          # Connexion
│   │   ├── EmailLogoutAction.php         # Déconnexion
│   │   ├── EmailRegisterAction.php       # Inscription
│   │   ├── GetCurrentUserAction.php      # Utilisateur courant
│   │   ├── ResetPasswordAction.php       # Réinitialisation
│   │   ├── SendPasswordResetLinkAction.php # Envoi OTP reset
│   │   ├── SendEmailVerificationAction.php # Envoi OTP vérif
│   │   ├── ResendEmailVerificationAction.php # Renvoi OTP
│   │   └── VerifyEmailAction.php         # Vérification email
│   ├── Contracts/
│   │   ├── MailAuthenticatable.php       # Interface du modèle
│   │   ├── MailAuthenticationInterface.php # Interface du service
│   │   └── Repositories/
│   │       └── LogRepositoryInterface.php # Interface des logs
│   ├── Services/
│   │   └── MailAuthenticationService.php # Service principal
│   ├── Repositories/
│   │   └── LogRepository.php             # Implémentation des logs
│   ├── Http/
│   │   └── Middleware/
│   │       └── ValidateMailAuthenticatableMiddleware.php
│   ├── Records/
│   │   ├── EmailLoginAuthRecord.php
│   │   ├── EmailLogoutAuthRecord.php
│   │   └── EmailRegisterAuthRecord.php
│   ├── Datas/
│   │   ├── AuthLoginData.php
│   │   ├── AuthRegisteredData.php
│   │   └── ErrorResponseData.php
│   ├── Enums/
│   │   ├── ErrorCode.php                 # Codes d'erreur API
│   │   ├── ErrorType.php                 # Types d'erreur logs
│   │   └── TokenSource.php               # Source des tokens
│   └── routes.php                        # Définition des routes
├── Configs/
│   └── AuthenticationKitConfig.php       # Configuration
└── AuthenticationKitServiceProvider.php  # Service Provider
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

    public function me(): array
    {
        $token = session('auth_token');
        
        $response = Http::withToken($token)->post(self::BASE_URL . '/me', [
            'model_type' => self::MODEL_TYPE,
        ]);

        return $response->json();
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

  async me(): Promise<{ success: boolean; data?: any; error?: ErrorResponse }> {
    try {
      const response = await fetch(`${API_URL}/me`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${this.token}`,
        },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
        }),
      });

      const result = await response.json();

      if (response.ok) {
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

### Composant Dashboard (avec utilisation de /me)

```tsx
// components/Dashboard.tsx

import React, { useEffect, useState } from 'react';
import { authService } from '../services/auth.service';

interface User {
  id: number;
  name: string;
  email: string;
  emailVerifiedAt: string | null;
  createdAt: string;
  updatedAt: string;
}

const Dashboard: React.FC = () => {
  const [user, setUser] = useState<User | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const fetchUser = async () => {
      setLoading(true);
      const result = await authService.me();
      
      if (result.success) {
        setUser(result.data);
      } else {
        setError(result.error?.message || 'Erreur de chargement');
      }
      
      setLoading(false);
    };

    fetchUser();
  }, []);

  const handleLogout = async () => {
    await authService.logout();
    window.location.href = '/login';
  };

  if (loading) {
    return <div>Chargement...</div>;
  }

  if (error || !user) {
    return <div>Erreur: {error || 'Utilisateur non trouvé'}</div>;
  }

  return (
    <div className="dashboard">
      <h1>Bienvenue {user.name} 👋</h1>
      <div className="user-info">
        <p><strong>Email:</strong> {user.email}</p>
        <p><strong>Vérifié:</strong> {user.emailVerifiedAt ? '✅ Oui' : '❌ Non'}</p>
        <p><strong>Inscrit le:</strong> {new Date(user.createdAt).toLocaleDateString()}</p>
      </div>
      <button onClick={handleLogout}>Déconnexion</button>
    </div>
  );
};

export default Dashboard;
```