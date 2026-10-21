# Authentication Kit - Documentation Complète

## 📖 Table des matières

1. [Introduction](#introduction)
2. [Installation](#installation)
3. [Configuration](#configuration)
4. [Préparation du modèle](#préparation-du-modèle)
5. [Routes et API](#routes-et-api)
6. [API Reference](#api-reference)
7. [Le service d'authentification](#le-service-dauthentification)
8. [Extension du service](#extension-du-service)
9. [Actions internes](#actions-internes)
10. [Google OAuth — Web](#google-oauth---web)
11. [Google OAuth — Android](#google-oauth---android)
12. [Logs et journalisation](#logs-et-journalisation)
13. [Gestion des erreurs](#gestion-des-erreurs)
14. [Exemples d'utilisation](#exemples-dutilisation)
15. [Sécurité](#sécurité)
16. [Migration de versions](#migration-de-versions)
17. [Structure du package](#structure-du-package)
18. [Restauration de cookie](#restauration-de-cookie)

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
| Pas de changement d'email sécurisé | ✅ Flux OTP sur nouvelle adresse |
| Pas d'action sensible protégée | ✅ Double authentification (2FA) |
| Cookie perdu par le navigateur | ✅ Endpoint `/restore-cookie` |
| Authentification Google OAuth | ✅ Flux complet PKCE + JWT (web) et ID-token-only (Android) |

---

## 🚀 Installation

```bash
composer require andydefer/authentication-kit
```

### Laravel

Le package s'enregistre automatiquement via `AuthenticationKitServiceProvider`, qui enregistre à son tour :

- `MailServiceProvider` — Authentification email / mot de passe
- `GoogleServiceProvider` — OAuth 2.0 Google

### Publier la configuration

```bash
php artisan vendor:publish --tag=authentication-kit-config
```

### Publier les routes

```bash
php artisan vendor:publish --tag=authentication-mail-kit-routes
php artisan vendor:publish --tag=authentication-google-kit-routes
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
     */
    'password_reset_rate_limit' => env('AUTH_KIT_PASSWORD_RESET_RATE_LIMIT', 3),

    /**
     * Limite de taux pour la vérification d'email
     */
    'email_verification_rate_limit' => env('AUTH_KIT_EMAIL_VERIFICATION_RATE_LIMIT', 5),

    /**
     * Limite de taux pour le changement d'email
     */
    'email_update_rate_limit' => env('AUTH_KIT_EMAIL_UPDATE_RATE_LIMIT', 3),

    /**
     * Limite de taux pour la double authentification (2FA)
     */
    'two_factor_rate_limit' => env('AUTH_KIT_TWO_FACTOR_RATE_LIMIT', 3),

    /**
     * Stockage du token dans un cookie
     */
    'store_token_in_cookie' => env('AUTH_KIT_STORE_TOKEN_IN_COOKIE', true),

    /**
     * Durée de vie du cookie d'authentification en minutes (défaut : 1 an)
     */
    'cookie_duration' => env('AUTH_KIT_COOKIE_DURATION', 525600),

    /**
     * Configuration Google OAuth 2.0 / OpenID Connect
     */
    'google' => [
        'client_id'     => env('AUTH_KIT_GOOGLE_CLIENT_ID', ''),
        'client_secret' => env('AUTH_KIT_GOOGLE_CLIENT_SECRET', ''),
        'redirect_uri'  => env('AUTH_KIT_GOOGLE_REDIRECT_URI', ''),

        'authorization_endpoint' => env(
            'AUTH_KIT_GOOGLE_AUTHORIZATION_ENDPOINT',
            'https://accounts.google.com/o/oauth2/v2/auth'
        ),
        'token_endpoint' => env(
            'AUTH_KIT_GOOGLE_TOKEN_ENDPOINT',
            'https://oauth2.googleapis.com/token'
        ),
        'jwks_endpoint' => env(
            'AUTH_KIT_GOOGLE_JWKS_ENDPOINT',
            'https://www.googleapis.com/oauth2/v3/certs'
        ),

        'scopes' => ['openid', 'email', 'profile'],

        'state_lifetime'       => env('AUTH_KIT_GOOGLE_STATE_LIFETIME', 600),
        'pkce_verifier_length' => env('AUTH_KIT_GOOGLE_PKCE_VERIFIER_LENGTH', 64),

        /**
         * Présentation du callback web (Authorization Code flow)
         * type: "view" (Blade) ou "inertia" (composant Inertia)
         * target: nom de la vue Blade ou nom du composant Inertia
         */
        'callback' => [
            'type'   => env('AUTH_KIT_GOOGLE_CALLBACK_TYPE', 'view'),
            'target' => env('AUTH_KIT_GOOGLE_CALLBACK_TARGET', 'auth.google-callback'),
        ],
    ],
];
```

### Variables d'environnement

```env
# .env
AUTH_KIT_TOKEN_NAME=my_auth_token
AUTH_KIT_PASSWORD_RESET_RATE_LIMIT=3
AUTH_KIT_EMAIL_VERIFICATION_RATE_LIMIT=5
AUTH_KIT_EMAIL_UPDATE_RATE_LIMIT=3
AUTH_KIT_TWO_FACTOR_RATE_LIMIT=3
AUTH_KIT_STORE_TOKEN_IN_COOKIE=true
AUTH_KIT_COOKIE_DURATION=525600

# Google OAuth
AUTH_KIT_GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
AUTH_KIT_GOOGLE_CLIENT_SECRET=xxx
AUTH_KIT_GOOGLE_REDIRECT_URI=https://app.test/auth/google/callback
AUTH_KIT_GOOGLE_STATE_LIFETIME=600
AUTH_KIT_GOOGLE_PKCE_VERIFIER_LENGTH=64
AUTH_KIT_GOOGLE_CALLBACK_TYPE=view
AUTH_KIT_GOOGLE_CALLBACK_TARGET=auth.google-callback
```

### Configuration des cookies (Nemesis)

```php
// config/nemesis.php
'web' => [
    'login_route' => '/login',
    'dashboard_route' => '/dashboard',
    'cookie_name' => 'auth_token',
    'cookie_secure' => env('COOKIE_SECURE', true),
    'cookie_httponly' => true,
    'cookie_samesite' => 'lax',
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
    public static function getMailAuthService(): MailAuthenticationInterface;
    public function getEmailVerifiedAt(): ?DateTimeVO;
    public static function generate(array $data): Model&Authenticatable;
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
use App\Datas\UserData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

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

### Définition des routes Mail

```php
<?php

use AndyDefer\Actions\Http\Requests\EmptyRequest;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Actions\GetCurrentUserAction;
use AndyDefer\AuthenticationKit\Mail\Actions\ResendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Actions\ResetPasswordAction;
use AndyDefer\AuthenticationKit\Mail\Actions\RestoreCookieAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendEmailUpdateOtpAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendPasswordResetLinkAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendTwoFactorOtpAction;
use AndyDefer\AuthenticationKit\Mail\Actions\UpdateEmailAction;
use AndyDefer\AuthenticationKit\Mail\Actions\VerifyEmailAction;
use AndyDefer\AuthenticationKit\Mail\Actions\VerifyTwoFactorOtpAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLogoutRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\ResendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\ResetPasswordRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\RestoreCookieRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendEmailUpdateOtpRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendPasswordResetLinkRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendTwoFactorOtpRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\UpdateEmailRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\VerifyEmailRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\VerifyTwoFactorOtpRequest;
use Illuminate\Support\Facades\Route;

Route::name('api.')->group(function (): void {

    Route::middleware(['validate.mail.authenticatable'])->group(function (): void {

        // Inscription
        Route::post('/email-register', action_route(
            EmailRegisterRequest::class,
            EmailRegisterAction::class
        ))->name('email-register');

        // Connexion
        Route::post('/email-login', action_route(
            EmailLoginRequest::class,
            EmailLoginAction::class
        ))->name('email-login');

        // Demande de réinitialisation de mot de passe
        Route::post('/send-password-link', action_route(
            SendPasswordResetLinkRequest::class,
            SendPasswordResetLinkAction::class
        ))->name('send-password-link');

        // Confirmation de réinitialisation
        Route::post('/reset-password', action_route(
            ResetPasswordRequest::class,
            ResetPasswordAction::class
        ))->name('reset-password');

        // Vérification d'email
        Route::post('/verify-email', action_route(
            VerifyEmailRequest::class,
            VerifyEmailAction::class
        ))->name('verify-email');

        // Restauration de cookie
        Route::post('/restore-cookie', action_route(
            RestoreCookieRequest::class,
            RestoreCookieAction::class
        ))->name('restore-cookie');

        Route::middleware(['nemesis.token'])->group(function (): void {

            // Déconnexion
            Route::post('/email-logout', action_route(
                EmailLogoutRequest::class,
                EmailLogoutAction::class
            ))->name('email-logout');

            // Envoi OTP de vérification email
            Route::post('/send-email-verification', action_route(
                SendEmailVerificationRequest::class,
                SendEmailVerificationAction::class
            ))->name('send-email-verification');

            // Renvoi OTP de vérification email
            Route::post('/resend-email-verification', action_route(
                ResendEmailVerificationRequest::class,
                ResendEmailVerificationAction::class
            ))->name('resend-email-verification');

            // Changement d'email
            Route::post('/send-email-update-otp', action_route(
                SendEmailUpdateOtpRequest::class,
                SendEmailUpdateOtpAction::class
            ))->name('send-email-update-otp');

            Route::patch('/update-email', action_route(
                UpdateEmailRequest::class,
                UpdateEmailAction::class
            ))->name('update-email');

            // Double authentification (2FA)
            Route::post('/send-two-factor-otp', action_route(
                SendTwoFactorOtpRequest::class,
                SendTwoFactorOtpAction::class
            ))->name('send-two-factor-otp');

            Route::post('/verify-two-factor-otp', action_route(
                VerifyTwoFactorOtpRequest::class,
                VerifyTwoFactorOtpAction::class
            ))->name('verify-two-factor-otp');
        });
    });

    // Utilisateur courant (pas de middleware, l'action gère)
    Route::post('/get-current-user', action_route(
        EmptyRequest::class,
        GetCurrentUserAction::class
    ))->name('get-current-user');
});
```

### Routes Google OAuth (web + Android)

```php
<?php

use AndyDefer\AuthenticationKit\Google\Actions\GoogleAndroidCallbackAction;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleCallbackAction;
use AndyDefer\AuthenticationKit\Google\Actions\GoogleRedirectAction;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleAndroidCallbackRequest;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleCallbackRequest;
use AndyDefer\AuthenticationKit\Google\Requests\GoogleRedirectRequest;
use Illuminate\Support\Facades\Route;

use function action_route;

/*
 * Web flow — browser navigation, view/Inertia rendering.
 */
Route::name('web.')->group(function (): void {
    Route::get('/google-callback', action_route(
        GoogleCallbackRequest::class,
        GoogleCallbackAction::class
    ))->name('google-callback');
});

/*
 * API flow — JSON responses.
 */
Route::name('api.')->group(function (): void {

    // Build the Google authorization URL (web flow)
    Route::post('/google-redirect', action_route(
        GoogleRedirectRequest::class,
        GoogleRedirectAction::class
    ))->name('google-redirect');

    // Handle the Android Credential Manager id_token (mobile flow)
    Route::post('/google-android-callback', action_route(
        GoogleAndroidCallbackRequest::class,
        GoogleAndroidCallbackAction::class
    ))->name('google-android-callback');
});
```

### Tableau récapitulatif des routes

#### Routes Mail

| Route | Méthode | Auth | Cookie | Description |
|-------|---------|------|--------|-------------|
| `/email-register` | POST | ❌ | ✅ si `with_token` | Inscription |
| `/email-login` | POST | ❌ | ✅ si configuré | Connexion |
| `/email-logout` | POST | ✅ | ✅ (supprime) | Déconnexion |
| `/get-current-user` | POST | ✅* | ✅ | Utilisateur courant |
| `/send-password-link` | POST | ❌ | ❌ | Demande reset |
| `/reset-password` | POST | ❌ | ❌ | Reset |
| `/verify-email` | POST | ❌ | ❌ | Vérification email |
| `/send-email-verification` | POST | ✅ | ❌ | Envoi OTP vérif |
| `/resend-email-verification` | POST | ✅ | ❌ | Renvoi OTP vérif |
| `/send-email-update-otp` | POST | ✅ | ❌ | OTP changement email |
| `/update-email` | PATCH | ✅ | ❌ | Confirmation changement |
| `/send-two-factor-otp` | POST | ✅ | ❌ | OTP 2FA |
| `/verify-two-factor-otp` | POST | ✅ | ❌ | Vérification 2FA |
| `/restore-cookie` | POST | ❌ | ✅ (écrit) | Restauration cookie |

\* Pas de middleware : l'Action gère elle-même l'authentification (Bearer ou cookie).

#### Routes Google

| Route | Méthode | Auth | Type de réponse | Description |
|-------|---------|------|-----------------|-------------|
| `/google-redirect` | POST | ❌ | JSON | Génère l'URL Google (web) |
| `/google-callback` | GET | ❌ | Vue / Inertia | Traite le callback Google (web) |
| `/google-android-callback` | POST | ❌ | JSON | Traite le callback Google Android |

---

## 🍪 Support des cookies

Le package supporte le stockage automatique des tokens d'authentification dans les cookies.

### Configuration

```php
// config/authentication-kit.php
'store_token_in_cookie' => env('AUTH_KIT_STORE_TOKEN_IN_COOKIE', true),
```

### Comportement

| Événement | Comportement |
|-----------|--------------|
| Connexion | Token stocké dans cookie sécurisé |
| Inscription avec `with_token=true` | Cookie défini |
| Déconnexion | Cookie supprimé |
| Requête `/get-current-user` | Cookie lu si pas de Bearer |
| `/restore-cookie` | Cookie réémis depuis plain token |

### Priorité d'authentification

1. **Bearer token** (header `Authorization`) — **Prioritaire**
2. **Cookie token** — Fallback

```php
// Priorité Bearer > Cookie
// Headers: Authorization: Bearer token-api
// Cookie: auth_token=token-cookie
// → Utilise le Bearer
```

---

## 📋 API Reference

### 1. Inscription - `POST /email-register`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ | FQCN du modèle |
| `with_token` | `boolean` | ❌ | Générer un token (défaut: false) |
| `remember_me` | `boolean` | ❌ | Cookie persistant (défaut: false) |
| `*` | `mixed` | ❌ | Champs passés au modèle |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "with_token": true,
    "remember_me": true,
    "name": "John Doe",
    "email": "john@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!"
}
```

**Réponse (201 Created) :**
```json
{
    "message": "User registered successfully with token",
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

### 2. Connexion - `POST /email-login`

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "email": "john@example.com",
    "password": "Password123!",
    "remember_me": true
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

### 3. Utilisateur courant - `POST /get-current-user`

**Requête avec Bearer :**
```
POST /api/get-current-user
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
```

**Requête avec Cookie :**
```
POST /api/get-current-user
Cookie: auth_token=eyJhbGciOiJIUzI1NiIs...
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

**Erreur (401) :**
```json
{
    "message": "Unauthenticated",
    "status": 401,
    "errorCode": "UNAUTHENTICATED"
}
```

---

### 4. Déconnexion - `POST /email-logout`

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "token": "eyJhbGciOiJIUzI1NiIs..."
}
```

**Réponse :** `204 No Content`

**Erreur - Token invalide (401) :**
```json
{
    "message": "Invalid token",
    "status": 401,
    "errorCode": "INVALID_TOKEN"
}
```

---

### 5. Demande réinitialisation - `POST /send-password-link`

**Requête :**
```json
{
    "email": "john@example.com",
    "model_type": "App\\Models\\User"
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

> 🔒 Retourne toujours 200, que l'utilisateur existe ou non.

---

### 6. Réinitialisation - `POST /reset-password`

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

### 7. Vérification email - `POST /verify-email`

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

### 8. Envoi OTP vérification - `POST /send-email-verification`

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

### 9. Renvoi OTP vérification - `POST /resend-email-verification`

Identique à `send-email-verification`.

```json
{
    "message": "Verification OTP resent successfully",
    "email": "john@example.com",
    "sentAt": "2026-08-14T12:00:00+00:00"
}
```

---

### 10. Changement d'email — Étape 1 - `POST /send-email-update-otp`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ | FQCN du modèle |
| `email` | `string` | ✅ | Nouvelle adresse email |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "email": "new-email@example.com"
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Email update code sent",
    "status": 200
}
```

**Erreur - Email pris ou rate limit (429) :**
```json
{
    "message": "Unable to send email update code",
    "status": 429,
    "errorCode": "EMAIL_UPDATE_SEND_FAILED",
    "errors": null
}
```

**Erreur - Modèle incompatible (422) :**
```json
{
    "message": "Model type mismatch",
    "status": 422,
    "errorCode": "MODEL_TYPE_MISMATCH",
    "errors": null
}
```

**Notes :**
- Le code est envoyé **uniquement sur la nouvelle adresse**.
- Purpose isolé : `email_update`.
- Rate limit : **3 envois / 600 s**.

---

### 11. Changement d'email — Étape 2 - `PATCH /update-email`

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "email": "new-email@example.com",
    "code": "483920"
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Email updated successfully. Please verify your new email.",
    "status": 200
}
```

**Erreur (400) :**
```json
{
    "message": "Invalid or expired code, or email already taken",
    "status": 400,
    "errorCode": "EMAIL_UPDATE_FAILED",
    "errors": null
}
```

**Notes :**
- OTP à **usage unique**.
- Après succès, `email_verified_at` est remis à `null`.
- L'ancienne adresse est immédiatement libérée.

---

### 12. 2FA — Étape 1 - `POST /send-two-factor-otp`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ | FQCN du modèle |
| `two_factor_purpose` | `string` | ✅ | Contexte de l'action sensible |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "two_factor_purpose": "change_password"
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Two-factor code sent",
    "status": 200
}
```

**Erreur (429) :**
```json
{
    "message": "Unable to send two-factor code",
    "status": 429,
    "errorCode": "TWO_FACTOR_SEND_FAILED",
    "errors": null
}
```

**Notes :**
- Chaque `purpose` a son espace isolé (`two_factor_{purpose}`).
- Rate limit : **3 envois / 300 s**.
- TTL : **5 minutes**.
- Purposes recommandés : `change_password`, `change_email`, `delete_account`, `approve_payment`, `change_2fa_settings`.

---

### 13. 2FA — Étape 2 - `POST /verify-two-factor-otp`

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "two_factor_purpose": "change_password",
    "code": "483920"
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Two-factor verification successful",
    "status": 200
}
```

**Erreur (400) :**
```json
{
    "message": "Invalid or expired two-factor code",
    "status": 400,
    "errorCode": "TWO_FACTOR_VERIFY_FAILED",
    "errors": null
}
```

**Notes :**
- OTP **consommé au premier succès**.
- Max **3 tentatives** par OTP.
- `two_factor_purpose` **strictement identique** à l'envoi.

Exemple d'intégration serveur :

```php
$verified = $service->verifyTwoFactorOtp(
    authenticatable: $user,
    purpose: 'change_password',
    code: $request->input('code'),
);

if (! $verified) {
    return response()->json(['error' => 'Invalid 2FA code'], 400);
}

// Action sensible autorisée
$user->password = Hash::make($request->input('new_password'));
$user->save();
```

---

### 14. Restauration de cookie - `POST /restore-cookie`

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ | FQCN du modèle |
| `token` | `string` | ✅ | Plain token (min 16 caractères) |
| `remember_me` | `boolean` | ❌ | Cookie persistant (défaut: false) |

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "remember_me": true
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Cookie restored successfully",
    "auth": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "emailVerifiedAt": "2026-08-14T12:00:00+00:00",
        "createdAt": "2026-08-14T10:00:00+00:00",
        "updatedAt": "2026-08-14T12:00:00+00:00"
    }
}
```

**Erreur - Token invalide ou expiré (401) :**
```json
{
    "message": "Invalid token",
    "status": 401,
    "errorCode": "INVALID_TOKEN"
}
```

**Erreur - Validation (422) :**
```json
{
    "message": "The token field is required.",
    "status": 422,
    "errorCode": "VALIDATION_ERROR",
    "errors": {
        "token": ["The token field is required."]
    }
}
```

**Notes :**
- **Route publique** : pas de middleware `nemesis.token`.
- **Aucun nouveau token** créé : réutilise le token existant.
- Vérifications : hash, existence, `tokenable_type`, non-révocation, non-expiration, existence du modèle.

---

### 15. Google Redirect - `POST /google-redirect`

**Requête :**
```json
{
    "state": "",
    "code_challenge": "",
    "nonce": ""
}
```

**Réponse (200 OK) :**
```json
{
    "url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=xxx.apps.googleusercontent.com&redirect_uri=https%3A%2F%2Fapp.test%2Fauth%2Fgoogle%2Fcallback&response_type=code&scope=openid%20email%20profile&state=zzRpnjPGjOeJdbjdV5alU-QnzvQU5QrAUWuM8-V0TIM&code_challenge=VibvCVqH41Ryvsn9LeUrYUiwkz1XGIBTzaoKyfIJiTQ&code_challenge_method=S256&nonce=iJzlhDvj-F6eTbs5LjsNrUB_x5Eu6jxqm2AMQeaCRRA"
}
```

**Notes :**
- Si `state`, `code_challenge` ou `nonce` sont vides, ils sont **générés côté serveur**.
- Le `code_verifier` et le `nonce` sont persistés dans le cache avec TTL `state_lifetime` (défaut 600 s).

---

### 16. Google Callback (web) - `GET /google-callback`

**Query params :**

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `code` | `string` | ✅* | Code d'autorisation Google |
| `state` | `string` | ✅ | State émis lors de la redirection |
| `error` | `string` | ❌ | Erreur Google (`access_denied`, etc.) |
| `error_description` | `string` | ❌ | Description de l'erreur |

\* Requis sauf si `error` présent.

**Réponse (200 OK) — Vue / Inertia :**

La réponse est rendue par la vue Blade ou le composant Inertia configuré dans `authentication-kit.google.callback.target`, avec la prop `success` ou `error` :

```php
// resources/views/auth/google-callback.blade.php
// La variable $success est une instance de GoogleCallbackData (succès)
echo $success->claims->getEmail();
echo $success->token;
```

Sur Inertia, le composant reçoit la prop `success` :

```tsx
export default function GoogleCallback({ success }: { success: GoogleCallbackData }) {
    // success.claims.email, success.claims.name, success.token, etc.
}
```

**Erreurs :**

| Cas | Type | ErrorCode |
|-----|------|-----------|
| Refus utilisateur | Vue avec `error` | `GOOGLE_PROVIDER_ERROR` |
| State inconnu | Vue avec `error` | `GOOGLE_STATE_MISMATCH` |
| State expiré | Vue avec `error` | `GOOGLE_STATE_EXPIRED` |
| Erreur token exchange | Vue avec `error` | `GOOGLE_CALLBACK_ERROR` |
| Erreur vérification ID token | Vue avec `error` | `GOOGLE_CALLBACK_ERROR` |
| Validation | 422 | `VALIDATION_ERROR` |

La prop `error` est un `ErrorResponseData` (`{ message, status, errorCode, errors }`).

---

### 17. Google Android Callback - `POST /google-android-callback`

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "id_token": "eyJhbGciOiJSUzI1NiIs..."
}
```

**Réponse (200 OK) — nouvel utilisateur :**
```json
{
    "claims": {
        "sub": "108234...",
        "email": "john@example.com",
        "emailVerified": true,
        "iss": "https://accounts.google.com",
        "aud": "xxx.apps.googleusercontent.com",
        "exp": 1791894290,
        "iat": 1791890690,
        "name": "John Doe",
        "picture": "https://lh3.googleusercontent.com/..."
    },
    "modelType": "App\\Models\\User",
    "auth": { "id": 62, "name": "John Doe", "email": "john@example.com" },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "isNewUser": true,
    "metadata": {}
}
```

**Réponse (200 OK) — utilisateur existant :**
```json
{
    "claims": { ... },
    "modelType": "App\\Models\\User",
    "auth": { "id": 62, "name": "John Doe", "email": "john@example.com" },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "isNewUser": false,
    "metadata": {}
}
```

**Erreurs :**

| Cas | Status | ErrorCode |
|-----|--------|-----------|
| Record invalide | 500 | `INVALID_RECORD_TYPE` |
| Email Google non vérifié | 500 | `GOOGLE_CALLBACK_ERROR` |
| `model_type` inexistant | 400 | `GOOGLE_MODEL_NOT_FOUND` |
| Modèle sans `MailAuthenticatable` | 400 | `GOOGLE_INVALID_MODEL` |
| Erreur signature / JWKS | 500 | `GOOGLE_CALLBACK_ERROR` |
| Validation | 422 | `VALIDATION_ERROR` |

---

## 🧩 Le service d'authentification

### `MailAuthenticationService`

Service générique orchestrant toute la logique d'authentification email.

```php
$authService = MailAuthenticationService::for(User::class);
```

### Méthodes publiques

| Méthode | Description |
|---------|-------------|
| `register(AbstractRecord $record)` | Crée un nouvel utilisateur |
| `login(string $email, string $password)` | Authentifie un utilisateur |
| `logout(Authenticatable&Model $user, string $token)` | Révoque un token |
| `restoreCookie(RestoreCookieAuthRecord $record)` | Restaure un cookie depuis un plain token |
| `sendPasswordResetOtp(string $email)` | Envoie OTP reset |
| `resetPassword(string $email, string $code, string $password)` | Reset mot de passe |
| `sendEmailVerificationOtp(Authenticatable $user)` | Envoie OTP vérif |
| `verifyEmail(string $email, string $code)` | Vérifie l'email |
| `resendEmailVerificationOtp(Authenticatable $user)` | Renvoie OTP |
| `isEmailVerified(Authenticatable $user)` | Vérifie email |
| `userExists(string $email)` | Vérifie existence |
| `sendEmailUpdateOtp(Authenticatable $user, string $newEmail)` | Envoie OTP changement |
| `updateEmail(Authenticatable $user, string $newEmail, string $code)` | Confirme changement |
| `sendTwoFactorOtp(Authenticatable $user, string $purpose)` | Envoie OTP 2FA |
| `verifyTwoFactorOtp(Authenticatable $user, string $purpose, string $code)` | Vérifie OTP 2FA |

### Hooks extensibles

| Hook | Quand | Cas d'usage |
|------|-------|-------------|
| `beforeRegister()` | Avant inscription | IP check, anti-spam |
| `afterRegister()` | Après inscription | Email bienvenue, profil |
| `beforeLogin()` | Avant connexion | Compte bloqué, 2FA |
| `afterLogin()` | Après connexion | Last login, sessions |
| `beforeLogout()` | Avant déconnexion | Journalisation |
| `afterLogout()` | Après déconnexion | Nettoyage sessions |
| `beforeRestoreCookie()` | Avant restauration | Vérifications préalables |
| `afterRestoreCookie()` | Après tentative | Audit, métriques |
| `beforeSendPasswordResetOtp()` | Avant OTP reset | Vérification email |
| `afterSendPasswordResetOtp()` | Après OTP reset | Notification admin |
| `beforeResetPassword()` | Avant reset | Validation supplémentaire |
| `afterResetPassword()` | Après reset | Invalidation sessions |
| `beforeVerifyEmail()` | Avant vérif | Vérifications supplémentaires |
| `afterVerifyEmail()` | Après vérif | Activation compte |
| `beforeSendEmailUpdateOtp()` | Avant OTP email | Vérifications métier |
| `afterSendEmailUpdateOtp()` | Après OTP email | Audit, notifications |
| `beforeUpdateEmail()` | Avant confirmation | Contrôles supplémentaires |
| `afterUpdateEmail()` | Après confirmation | Revérification, logs |
| `beforeSendTwoFactorOtp()` | Avant OTP 2FA | Vérifications d'état |
| `afterSendTwoFactorOtp()` | Après OTP 2FA | Audit |
| `beforeVerifyTwoFactorOtp()` | Avant vérif 2FA | Contrôles |
| `afterVerifyTwoFactorOtp()` | Après vérif 2FA | Journalisation |

### Template Method Pattern — Notifications

```php
protected function buildPasswordResetNotification(string $email, string $otp): NotificationMessageRecord
{
    return NotificationMessageRecord::from([
        'email' => $email,
        'subject' => 'Password Reset Code',
        'body' => "Your password reset code is: {$otp}",
    ]);
}

protected function buildEmailVerificationNotification(string $email, string $otp): NotificationMessageRecord
{
    return NotificationMessageRecord::from([
        'email' => $email,
        'subject' => 'Email Verification Code',
        'body' => "Your email verification code is: {$otp}",
    ]);
}

protected function buildEmailUpdateNotification(string $email, string $otp): NotificationMessageRecord
{
    return NotificationMessageRecord::from([
        'email' => $email,
        'subject' => 'Confirm Your New Email Address',
        'body' => "Use this code to confirm your new email: {$otp}",
    ]);
}

protected function buildTwoFactorNotification(string $email, string $otp, string $purpose): NotificationMessageRecord
{
    return NotificationMessageRecord::from([
        'email' => $email,
        'subject' => 'Your Two-Factor Authentication Code',
        'body' => "Your two-factor code for {$purpose} is: {$otp}",
    ]);
}
```

---

## 🔧 Extension du service

### Exemple : Service personnalisé complet

```php
<?php

declare(strict_types=1);

namespace App\Services;

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Records\NotificationMessageRecord;
use AndyDefer\AuthenticationKit\Mail\Records\RestoreCookieAuthRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Interfaces\Transformable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

final class CustomAuthService extends MailAuthenticationService
{
    // ============================================================
    // TEMPLATE METHOD - Notifications
    // ============================================================

    protected function buildPasswordResetNotification(string|Transformable $email, string|Transformable $otp): NotificationMessageRecord
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

    protected function buildEmailVerificationNotification(string|Transformable $email, string|Transformable $otp): NotificationMessageRecord
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

    protected function buildEmailUpdateNotification(string|Transformable $email, string|Transformable $otp): NotificationMessageRecord
    {
        $html = view('emails.email-update', [
            'otp' => $otp,
            'expires_in' => 10,
        ])->render();

        return NotificationMessageRecord::from([
            'email' => $email,
            'subject' => '✉️ Confirmez votre nouvelle adresse email',
            'body' => $html,
        ]);
    }

    protected function buildTwoFactorNotification(string|Transformable $email, string|Transformable $otp, string|Transformable $purpose): NotificationMessageRecord
    {
        $html = view('emails.two-factor', [
            'otp' => $otp,
            'purpose' => $purpose,
            'expires_in' => 5,
        ])->render();

        return NotificationMessageRecord::from([
            'email' => $email,
            'subject' => '🔒 Code de vérification à deux facteurs',
            'body' => $html,
        ]);
    }

    // ============================================================
    // HOOKS
    // ============================================================

    protected function beforeRegister(AbstractRecord $record): void
    {
        if ($this->isIpBlocked($record->ip)) {
            throw new \RuntimeException('IP blocked due to suspicious activity');
        }

        $email = $record->data->get('email');
        if ($this->isEmailBlocked($email)) {
            throw new \RuntimeException('This email address is not allowed');
        }
    }

    protected function afterRegister(Model&Authenticatable $user, AbstractRecord $record): void
    {
        $this->sendWelcomeEmail($user);

        $user->profile()->create([
            'bio' => $record->data->get('bio'),
            'age' => $record->data->get('age'),
            'phone' => $record->data->get('phone'),
        ]);

        $user->assignRole('user');

        Log::info('New user registered', ['user_id' => $user->id]);
    }

    protected function beforeLogin(string|Transformable $email, string|Transformable $password): void
    {
        $user = $this->findUserByEmail($email);

        if ($user && $user->is_locked) {
            throw new \RuntimeException('Account is locked. Please contact support.');
        }

        if (! $this->isIpAllowed(request()->ip())) {
            throw new \RuntimeException('Access denied from this IP address');
        }
    }

    protected function afterLogin(Model&Authenticatable $user): void
    {
        $user->last_login_at = now();
        $user->login_count = ($user->login_count ?? 0) + 1;
        $user->save();

        $this->createUserSession($user);
        $this->clearFailedAttempts($user);

        Log::info('User logged in', ['user_id' => $user->id]);
    }

    protected function beforeLogout(Authenticatable&Model $authenticatable, string|Transformable $plainToken): void
    {
        Log::info('Logout attempt', ['user_id' => $authenticatable->id]);
    }

    protected function afterLogout(Authenticatable&Model $authenticatable): void
    {
        $this->clearUserSession($authenticatable);
        Log::info('Logout successful', ['user_id' => $authenticatable->id]);
    }

    protected function beforeRestoreCookie(RestoreCookieAuthRecord $record): void
    {
        // Exemple : interdire la restauration hors des heures ouvrées
        // if (now()->hour < 6 || now()->hour > 22) {
        //     throw new \RuntimeException('Cookie restoration is not allowed at this time');
        // }
    }

    protected function afterRestoreCookie(RestoreCookieAuthRecord $record, ?Model $auth): void
    {
        if ($auth === null) {
            Log::warning('Cookie restoration failed', [
                'model_type' => $record->model_type,
            ]);

            return;
        }

        Log::info('Cookie restored', [
            'user_id' => $auth->getKey(),
            'model_type' => $record->model_type,
        ]);
    }

    protected function beforeSendPasswordResetOtp(string|Transformable $email): void
    {
        $user = $this->findUserByEmail($email);

        if ($user && $user->prevent_password_reset) {
            throw new \RuntimeException('Password reset is not allowed for this account');
        }
    }

    protected function afterSendPasswordResetOtp(string|Transformable $email, bool $success): void
    {
        if (! $success) {
            $this->notifyAdmin('Password reset failed for: ' . $email);
        }
    }

    protected function beforeResetPassword(string|Transformable $email, string|Transformable $code, string|Transformable $password): void
    {
        if (strlen($password) < 12) {
            throw new \RuntimeException('Password must be at least 12 characters');
        }

        if ($this->isPasswordCompromised($password)) {
            throw new \RuntimeException('This password has been compromised. Please choose another.');
        }
    }

    protected function afterResetPassword(Model&Authenticatable $user): void
    {
        $user->tokens()->delete();
        $this->sendPasswordChangedNotification($user);
        Log::alert('Password reset', ['user_id' => $user->id]);
    }

    protected function beforeVerifyEmail(string|Transformable $email, string|Transformable $code): void
    {
        $user = $this->findUserByEmail($email);

        if ($user && $user->email_verified_at !== null) {
            throw new \RuntimeException('Email already verified');
        }
    }

    protected function afterVerifyEmail(Model&Authenticatable $user): void
    {
        $user->is_active = true;
        $user->save();

        $this->sendWelcomeVerificationNotification($user);
        Log::info('Email verified', ['user_id' => $user->id]);
    }

    protected function beforeSendEmailUpdateOtp(Authenticatable&Model $authenticatable, string|Transformable $newEmail): void
    {
        if ($authenticatable->hasPendingEmailChange()) {
            throw new \RuntimeException('An email change is already pending');
        }
    }

    protected function afterSendEmailUpdateOtp(Authenticatable&Model $authenticatable, string|Transformable $newEmail): void
    {
        Log::info('Email update OTP sent', [
            'user_id' => $authenticatable->id,
            'new_email' => (string) $newEmail,
        ]);
    }

    protected function beforeUpdateEmail(Authenticatable&Model $authenticatable, string|Transformable $newEmail, string|Transformable $code): void
    {
        // Aucune vérification supplémentaire ici
    }

    protected function afterUpdateEmail(Authenticatable&Model $authenticatable, string|Transformable $newEmail): void
    {
        $this->sendEmailVerificationOtp($authenticatable);

        Log::alert('Email updated', [
            'user_id' => $authenticatable->id,
            'new_email' => (string) $newEmail,
        ]);
    }

    protected function beforeSendTwoFactorOtp(Authenticatable&Model $authenticatable, string|Transformable $purpose): void
    {
        if (! $authenticatable->two_factor_enabled) {
            throw new \RuntimeException('Two-factor authentication is not enabled for this account');
        }
    }

    protected function afterSendTwoFactorOtp(Authenticatable&Model $authenticatable, string|Transformable $purpose): void
    {
        Log::info('Two-factor OTP sent', [
            'user_id' => $authenticatable->id,
            'purpose' => (string) $purpose,
        ]);
    }

    protected function beforeVerifyTwoFactorOtp(Authenticatable&Model $authenticatable, string|Transformable $purpose, string|Transformable $code): void
    {
        // Aucune vérification supplémentaire ici
    }

    protected function afterVerifyTwoFactorOtp(Authenticatable&Model $authenticatable, string|Transformable $purpose): void
    {
        Log::info('Two-factor verification successful', [
            'user_id' => $authenticatable->id,
            'purpose' => (string) $purpose,
        ]);
    }

    // ============================================================
    // MÉTHODES PRIVÉES UTILITAIRES
    // ============================================================

    private function findUserByEmail(string|Transformable $email): ?Model
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->modelClass;

        return $modelClass::where('email', $email)->first();
    }

    private function isIpBlocked(?string $ip): bool
    {
        return in_array($ip, ['192.168.1.1', '10.0.0.1']);
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

### Personnalisation de la validation du mot de passe

```php
public static function getPasswordValidationRules(): array
{
    return [
        'password' => ['required', 'string', 'min:8', 'confirmed'],
    ];
}
```

**Exemple renforcé :**

```php
public static function getPasswordValidationRules(): array
{
    return [
        'password' => [
            'required',
            'string',
            'min:12',
            'confirmed',
            'regex:/[A-Z]/',
            'regex:/[a-z]/',
            'regex:/[0-9]/',
            'regex:/[@$!%*?&]/',
            'not_in:password,123456,admin',
        ],
    ];
}
```

---

## ⚙️ Actions internes

Pattern **Action** pour organiser la logique métier de manière modulaire et testable.

### Structure d'une Action

```php
abstract class AbstractAction
{
    protected function before(AbstractRecord $record): void {}
    protected function handle(AbstractRecord $record): ResponseFactory {}
    protected function after(bool $success, ?Exception $error, AbstractRecord $record): void {}
}
```

### Actions disponibles

| Action | Description | Record | Auth |
|--------|-------------|--------|------|
| `EmailRegisterAction` | Inscription | `EmailRegisterAuthRecord` | ❌ |
| `EmailLoginAction` | Connexion | `EmailLoginAuthRecord` | ❌ |
| `EmailLogoutAction` | Déconnexion | `EmailLogoutAuthRecord` | ✅ |
| `GetCurrentUserAction` | Utilisateur courant | `EmptyRequest` | ✅ |
| `SendPasswordResetLinkAction` | Envoi OTP reset | `SendPasswordResetLinkRecord` | ❌ |
| `ResetPasswordAction` | Reset | `ResetPasswordRecord` | ❌ |
| `SendEmailVerificationAction` | Envoi OTP vérif | `SendEmailVerificationRecord` | ✅ |
| `ResendEmailVerificationAction` | Renvoi OTP | `ResendEmailVerificationRecord` | ✅ |
| `VerifyEmailAction` | Vérification | `VerifyEmailRecord` | ❌ |
| `SendEmailUpdateOtpAction` | OTP changement email | `SendEmailUpdateOtpAuthRecord` | ✅ |
| `UpdateEmailAction` | Confirmation changement | `UpdateEmailAuthRecord` | ✅ |
| `SendTwoFactorOtpAction` | OTP 2FA | `SendTwoFactorOtpAuthRecord` | ✅ |
| `VerifyTwoFactorOtpAction` | Vérif 2FA | `VerifyTwoFactorOtpAuthRecord` | ✅ |
| `RestoreCookieAction` | Restauration cookie | `RestoreCookieAuthRecord` | ❌ |
| `GoogleRedirectAction` | URL Google | `GoogleRedirectRecord` | ❌ |
| `GoogleCallbackAction` | Callback Google (web) | `GoogleCallbackRecord` | ❌ |
| `GoogleAndroidCallbackAction` | Callback Google (Android) | `GoogleAndroidCallbackRecord` | ❌ |

### Flux d'exécution d'une Action

```
Requête entrante (Record)
    ↓
1. before() — Validation et préparation
    ↓
2. handle() — Traitement principal
    ├── Succès → Réponse positive
    └── Échec → ErrorResponseData
    ↓
3. after() — Journalisation
    ├── Succès → logSuccess()
    └── Échec → logFailure()
```

### Middleware `ValidateMailAuthenticatableMiddleware`

```php
public function handle(Request $request, Closure $next): Response
{
    $modelType = $request->input('model_type');

    // 1. Présence
    if ($modelType === null) {
        return $this->errorResponse('MODEL_TYPE_REQUIRED', 400);
    }

    // 2. Existence
    if (! class_exists($modelType)) {
        return $this->errorResponse('MODEL_NOT_FOUND', 500);
    }

    // 3. Interface
    if (! in_array(MailAuthenticatable::class, class_implements($modelType))) {
        return $this->errorResponse('INVALID_MODEL', 500);
    }

    // 4. Liaison du service
    app()->bind(MailAuthenticationInterface::class, function () use ($modelType) {
        return MailAuthenticationService::for($modelType);
    });

    // 5. Requête
    $response = $next($request);

    // 6. Cookies
    if ($this->config->shouldStoreTokenInCookie()) {
        foreach (Cookie::getQueuedCookies() as $cookie) {
            $response->headers->setCookie($cookie);
        }
    }

    return $response;
}
```

---

## 🔵 Google OAuth — Web

Le flow **Authorization Code + PKCE** du package implémente les endpoints `/google-redirect` (génère l'URL) et `/google-callback` (traite le retour navigateur).

### Composants

| Composant | Rôle |
|-----------|------|
| `GoogleOAuthService` | Orchestre le flow web et Android |
| `GooglePkceGenerator` | Génère `code_verifier`, `code_challenge`, `state`, `nonce` |
| `GoogleStateStore` | Persiste le state entre redirection et callback |
| `GoogleIdTokenVerifier` | Vérifie cryptographiquement l'ID token (JWKS + claims), produit un `GoogleIdTokenClaimsVO` |
| `GoogleCallbackVO` | VO passé à `onCallbackSuccess()` : tokens + claims + operation |
| `GoogleCallbackData` | Data retournée par `onCallbackSuccess()` (auth + token + metadata) |
| `GoogleRedirectAction` / `GoogleCallbackAction` | Actions HTTP du flow web |
| `GoogleRedirectRequest` / `GoogleCallbackRequest` | Validation HTTP |
| `GoogleOAuthException` | Exception unique (avec `isProviderError()`) |

### Flux complet

```
1. Client → POST /api/google-redirect
   ↓
   GoogleRedirectAction → GoogleOAuthService::authorizationRedirect()
   ↓
   - Génère PKCE (verifier + challenge)
   - Génère state + nonce
   - Persiste {code_verifier, nonce} dans GoogleStateStore (TTL 600s)
   - Retourne { url: "https://accounts.google.com/..." }

2. Client redirige l'utilisateur vers l'URL

3. Google redirige vers GET /google-callback?code=...&state=...
   ↓
   GoogleCallbackAction → GoogleOAuthService::callback()
   ↓
   - Consomme le state store (usage unique)
   - POST /token à Google avec code + code_verifier
   - Vérifie l'ID token via JWKS + claims → GoogleIdTokenClaimsVO
   - Construit un GoogleCallbackVO
   - Délègue à onCallbackSuccess(GoogleCallbackVO) → GoogleCallbackData|ErrorResponseData
   ↓
   GoogleCallbackAction rend la présentation configurée :
   - succès : view/inertia(['success' => GoogleCallbackData])
   - erreur  : view/inertia(['error'   => ErrorResponseData])
```

### Ce que le service NE fait PAS

Le service **s'arrête** à `GoogleCallbackVO` (tokens vérifiés + claims). Sont de la responsabilité de l'application, via l'override de `onCallbackSuccess()` :

- Résolution / création de l'utilisateur local.
- Génération du token Nemesis.
- Gestion des cookies ou sessions applicatives.
- Retour d'un `GoogleCallbackData` complet (auth + token + metadata).

### Garanties de sécurité

| Protection | Mécanisme |
|------------|-----------|
| Vol de code | PKCE S256 (`code_verifier` + `code_challenge`) |
| CSRF | `state` unique + usage unique + TTL |
| Rejeu d'ID token | `nonce` + `exp` |
| Token d'une autre app | Vérification `aud` = `client_id` |
| Émetteur usurpé | Vérification `iss` ∈ {`accounts.google.com`, `https://accounts.google.com`} |
| Signature forgée | Vérification RS256 via JWKS Google |
| Email non vérifié | Refus si `email_verified !== true` |

### Détails des actions Google (web)

#### `GoogleRedirectAction` - POST `/api/google-redirect`

Génère l'URL Google avec PKCE + state + nonce.

| Cas | Status | ErrorCode |
|-----|--------|-----------|
| Succès | 200 | — |
| Record invalide | 500 | `INVALID_RECORD_TYPE` |
| Erreur technique | 500 | `GOOGLE_REDIRECT_ERROR` |

#### `GoogleCallbackAction` - GET `/google-callback`

Traite le callback Google (web). **Ne retourne jamais de JSON** : toutes les erreurs sont rendues dans la vue / le composant Inertia configuré, via la prop `error`.

| Cas | Status | Prop `error.errorCode` |
|-----|--------|------------------------|
| Succès | 200 | — (prop `success` présente) |
| Record invalide | 200 | `INVALID_RECORD_TYPE` |
| Google renvoie une erreur | 200 | `GOOGLE_PROVIDER_ERROR` |
| State inconnu | 200 | `GOOGLE_STATE_MISMATCH` |
| State expiré | 200 | `GOOGLE_STATE_EXPIRED` |
| Échec token exchange | 200 | `GOOGLE_CALLBACK_ERROR` |
| Échec vérification ID token | 200 | `GOOGLE_CALLBACK_ERROR` |

---

## 🟢 Google OAuth — Android

Le flow web (Authorization Code + PKCE) exige une navigation navigateur. Les applications mobiles Android n'ont pas de WebView : elles utilisent **Credential Manager** qui retourne directement un `id_token` Google signé.

Le package expose donc un **second endpoint dédié** qui accepte cet `id_token` sans code exchange, sans PKCE, sans state.

### Composants Android

| Composant | Rôle |
|-----------|------|
| `GoogleAndroidCallbackVO` | VO passé à `onAndroidCallbackSuccess()` : claims + modelType |
| `GoogleAndroidCallbackData` | Data retournée par `onAndroidCallbackSuccess()` (claims + modelType + auth + token + isNewUser + metadata) |
| `GoogleAndroidCallbackAction` | Action HTTP JSON-only |
| `GoogleAndroidCallbackRequest` | Validation HTTP (`model_type`, `id_token`) |

Le service vérifie l'`id_token` et la conformité du `model_type`, produit un `GoogleAndroidCallbackVO` et délègue la décision métier (`login` ou `register`) à `onAndroidCallbackSuccess()`. **Aucune interface supplémentaire n'est imposée au modèle** : c'est l'application qui décide comment créer/retrouver l'utilisateur et émettre le token.

### Route Android

```php
Route::name('api.')->group(function (): void {

    Route::post('/google-android-callback', action_route(
        GoogleAndroidCallbackRequest::class,
        GoogleAndroidCallbackAction::class
    ))->name('google-android-callback');

});
```

Cette route doit être placée **dans le groupe API** (le flow Android retourne du JSON, jamais de page).

### Requête / Réponse

**Requête :**
```
POST /api/google-android-callback
Content-Type: application/json

{
    "model_type": "App\\Models\\User",
    "id_token": "eyJhbGciOiJSUzI1NiIs..."
}
```

**Réponse (200 OK) — nouvel utilisateur :**
```json
{
    "claims": {
        "sub": "108234...",
        "email": "john@example.com",
        "emailVerified": true,
        "iss": "https://accounts.google.com",
        "aud": "xxx.apps.googleusercontent.com",
        "exp": 1791894290,
        "iat": 1791890690,
        "name": "John Doe",
        "picture": "https://lh3.googleusercontent.com/..."
    },
    "modelType": "App\\Models\\User",
    "auth": { "id": 62, "name": "John Doe", "email": "john@example.com" },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "isNewUser": true,
    "metadata": {}
}
```

**Réponse (200 OK) — utilisateur existant :**
```json
{
    "claims": { ... },
    "modelType": "App\\Models\\User",
    "auth": { "id": 62, "name": "John Doe", "email": "john@example.com" },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "isNewUser": false,
    "metadata": {}
}
```

**Erreurs :**

| Cas | Status | ErrorCode |
|-----|--------|-----------|
| Record invalide | 500 | `INVALID_RECORD_TYPE` |
| Email Google non vérifié | 500 | `GOOGLE_CALLBACK_ERROR` |
| `model_type` inexistant | 400 | `GOOGLE_MODEL_NOT_FOUND` |
| Modèle sans `MailAuthenticatable` | 400 | `GOOGLE_INVALID_MODEL` |
| Erreur signature / JWKS | 500 | `GOOGLE_CALLBACK_ERROR` |
| Validation | 422 | `VALIDATION_ERROR` |

### Exemple d'override de `onAndroidCallbackSuccess()`

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Google\Datas\GoogleAndroidCallbackData;
use AndyDefer\AuthenticationKit\Google\Services\GoogleOAuthService;
use AndyDefer\AuthenticationKit\Google\VOs\GoogleAndroidCallbackVO;
use AndyDefer\DomainStructures\Utils\StrictAssociative;
use AndyDefer\Nemesis\Datas\ErrorResponseData;

final class AfyaGoogleOAuthService extends GoogleOAuthService
{
    public function onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData
    {
        $email = strtolower($vo->getClaims()->getEmail());

        $user = User::where('email', $email)->first();
        $isNewUser = $user === null;

        if ($isNewUser) {
            $user = User::create([
                'name'              => $vo->getClaims()->displayName(),
                'email'             => $email,
                'email_verified_at' => now(),
            ]);
        }

        [$token, $plainToken] = $this->nemesis->createWithPlainToken(
            /* record */,
            $user,
        );

        return new GoogleAndroidCallbackData(
            claims: $vo->getClaims(),
            modelType: $vo->getModelType(),
            auth: new StrictAssociative(
                action_normalizer_chain(true)->normalize($user->nemesisFormat()),
            ),
            token: $plainToken,
            isNewUser: $isNewUser,
        );
    }
}
```

### Flux complet du callback Android

```
1. Android : Credential Manager → id_token (JWT signé par Google)
   ↓
2. Android → POST /api/google-android-callback
   Body : { model_type, id_token }
   ↓
3. GoogleAndroidCallbackAction::handle()
   ↓
4. GoogleOAuthService::androidCallback()
   ↓
   - Vérifie id_token (JWKS Google + claims, sans nonce) → GoogleIdTokenClaimsVO
   - Vérifie claims->isEmailVerified() === true
   - Vérifie class_exists(model_type)
   - Vérifie is_subclass_of(MailAuthenticatable)
   - Construit GoogleAndroidCallbackVO(claims, modelType)
   - Délègue à onAndroidCallbackSuccess(GoogleAndroidCallbackVO)
   ↓
5. onAndroidCallbackSuccess() (application) :
   - Recherche l'utilisateur par email
   - Crée l'utilisateur si absent (décision applicative)
   - Émet le token Nemesis
   - Retourne GoogleAndroidCallbackData (ou ErrorResponseData)
   ↓
6. GoogleAndroidCallbackAction traduit le résultat en JSON
   ↓
7. Réponse JSON au client
```

### Garanties de sécurité Android

| Protection | Mécanisme |
|------------|-----------|
| Token forgé | Vérification RS256 via JWKS Google |
| Token d'une autre app | Vérification `aud` = `client_id` |
| Émetteur usurpé | Vérification `iss` = `accounts.google.com` |
| Token expiré | Vérification `exp` |
| Email non vérifié | Refus si `email_verified !== true` |
| Modèle incompatible | `class_exists` + `is_subclass_of(MailAuthenticatable)` |
| Fuite de données | L'application contrôle entièrement ce que `nemesisFormat()` expose |

### Ce que le flow Android NE fait PAS

Le service **ne crée pas** l'utilisateur et **n'émet pas** le token. Tout cela revient à l'application via `onAndroidCallbackSuccess()`, qui reçoit un `GoogleAndroidCallbackVO` et retourne un `GoogleAndroidCallbackData` complet.

- **Pas de cookie posé par défaut** : le client Android gère son stockage (SharedPreferences, DataStore, Keystore).
- **Pas de session** : Nemesis renvoie un token Bearer que le client enverra via `Authorization`.
- **Pas de nonce** : `expectedNonce: null` dans `verify()`. Le `id_token` Android n'est pas émis avec un nonce de session serveur.
- **Pas de state** : aucun state n'est consommé, aucun anti-replay côté serveur (le token Google a une durée de vie courte, et Nemesis émet un token d'usage propre).

### Comparaison flow web vs Android

| Aspect | Web (`/google-callback`) | Android (`/google-android-callback`) |
|--------|--------------------------|--------------------------------------|
| Méthode | GET (redirection Google) | POST (appel direct) |
| Input | `code` + `state` | `id_token` |
| Échange de code | Oui (avec PKCE) | Non |
| Nonce | Oui | Non (pas de session serveur) |
| State | Oui (usage unique) | Non |
| Réponse | Vue Blade / Inertia | JSON |
| VO reçu par `on*CallbackSuccess()` | `GoogleCallbackVO` | `GoogleAndroidCallbackVO` |
| Data retournée | `GoogleCallbackData` | `GoogleAndroidCallbackData` |
| Création utilisateur | Dans `onCallbackSuccess()` | Dans `onAndroidCallbackSuccess()` |
| Token émis | Dans `onCallbackSuccess()` | Dans `onAndroidCallbackSuccess()` |
| Cookie | Optionnel (`store_token_in_cookie`) | Non (Bearer uniquement) |
| `isNewUser` | Non exposé | Exposé dans la réponse |

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
| Email update réussi | `logEmailUpdateSuccess()` | authId, modelClass, newEmail |
| Email update échoué | `logEmailUpdateFailure()` | authId, modelClass, error, errorType |
| OTP 2FA envoyé | `logTwoFactorSent()` | authId, modelClass, purpose, success |
| OTP 2FA vérifié | `logTwoFactorVerified()` | authId, modelClass, purpose, success |

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

### ErrorCode — Mail

| Code | HTTP | Description |
|------|------|-------------|
| `INVALID_RECORD_TYPE` | 500 | Type de record invalide |
| `MISSING_CREDENTIALS` | 400 | Identifiants manquants |
| `INVALID_CREDENTIALS` | 401 | Identifiants invalides |
| `AUTHENTICATABLE_NOT_FOUND` | 404 | Utilisateur non trouvé |
| `INVALID_TOKEN` | 401 | Token invalide |
| `TOKEN_EXPIRED` | 401 | Token expiré |
| `VALIDATION_ERROR` | 422 | Erreur de validation |
| `MODEL_NOT_FOUND` | 422 | Modèle introuvable |
| `INVALID_MODEL` | 422 | Modèle invalide |
| `REGISTRATION_ERROR` | 422 | Erreur d'inscription |
| `LOGIN_ERROR` | 422 | Erreur de connexion |
| `LOGOUT_FAILED` | 422 | Échec de déconnexion |
| `LOGOUT_EXCEPTION` | 422 | Exception déconnexion |
| `USER_FETCH_ERROR` | 422 | Erreur récupération user |
| `VERIFICATION_OTP_RESEND_FAILED` | 422 | Échec renvoi OTP |
| `INVALID_RESET_OTP` | 400 | OTP reset invalide |
| `RESET_PASSWORD_ERROR` | 422 | Erreur reset |
| `MODEL_TYPE_MISMATCH` | 422 | Model type incohérent |
| `USER_FORMAT_ERROR` | 422 | Modèle n'implémente pas MustNemesis |
| `EMAIL_UPDATE_SEND_FAILED` | 429 | Envoi OTP email impossible |
| `EMAIL_UPDATE_SEND_ERROR` | 422 | Exception lors de l'envoi |
| `EMAIL_UPDATE_FAILED` | 400 | Confirmation email échouée |
| `EMAIL_UPDATE_ERROR` | 422 | Exception lors de la confirmation |
| `TWO_FACTOR_SEND_FAILED` | 429 | Envoi OTP 2FA impossible |
| `TWO_FACTOR_SEND_ERROR` | 422 | Exception lors de l'envoi 2FA |
| `TWO_FACTOR_VERIFY_FAILED` | 400 | Vérification 2FA échouée |
| `TWO_FACTOR_VERIFY_ERROR` | 422 | Exception lors de la vérif 2FA |

### ErrorCode — Google

| Code | HTTP | Description |
|------|------|-------------|
| `GOOGLE_REDIRECT_ERROR` | 500 | Échec génération URL Google |
| `GOOGLE_CALLBACK_ERROR` | 500 | Échec callback (state, exchange, verify) |
| `GOOGLE_CALLBACK_UNEXPECTED_ERROR` | 500 | Erreur inattendue dans le callback |
| `GOOGLE_STATE_MISMATCH` | 400 | State inconnu |
| `GOOGLE_STATE_EXPIRED` | 400 | State expiré |
| `GOOGLE_PROVIDER_ERROR` | 400 | Erreur remontée par Google (`access_denied`, etc.) |
| `GOOGLE_MODEL_NOT_FOUND` | 400 | `model_type` inexistant |
| `GOOGLE_INVALID_MODEL` | 400 | `model_type` sans `MailAuthenticatable` |

### ErrorType (Logs)

| Type | Description |
|------|-------------|
| `user_not_found` | Utilisateur non trouvé |
| `invalid_credentials` | Identifiants invalides |
| `invalid_otp` | OTP invalide |
| `rate_limit_exceeded` | Rate limit dépassé |
| `token_not_found` | Token non trouvé |
| `token_revoke_failed` | Échec révocation |
| `validation_error` | Erreur de validation |
| `account_locked` | Compte verrouillé |
| `email_already_verified` | Email déjà vérifié |
| `email_already_taken` | Email déjà utilisé |
| `invalid_email` | Email invalide |
| `password_too_weak` | Mot de passe faible |
| `invalid_token` | Token invalide |
| `token_expired` | Token expiré |
| `invalid_record_type` | Type de record invalide |
| `missing_credentials` | Identifiants manquants |
| `invalid_two_factor_purpose` | Purpose 2FA invalide |
| `two_factor_not_enabled` | 2FA non activée |
| `cookie_restore_failed` | Échec restauration cookie |

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

| Fonctionnalité | Description | Défaut |
|----------------|-------------|--------|
| **Rate Limiting** | Tentatives par période | 3 (reset) / 5 (vérif) / 3 (email update, 2FA) |
| **OTP Expiration** | Validité d'un OTP | 5 min (email) / 10 min (password, email update) / 5 min (2FA) |
| **OTP Max Attempts** | Tentatives par OTP | 3 |
| **Token Hash** | Algo de hachage | SHA-256 |
| **Réponse /send-password-link** | Ne révèle pas l'existence | Toujours 200 |
| **Logs** | Protection des données | Pas de logs pour emails inexistants |
| **Cookies** | HttpOnly, Secure, SameSite | Configurables |
| **Token Storage** | Hashé en base | SHA-256 |
| **Model type validation** | Vérification `model_type` | Oui |
| **Purpose isolation** | OTPs 2FA isolés | `two_factor_{context}` |
| **Email update proof** | OTP sur nouvelle adresse | Oui |
| **Reset of email_verified_at** | Revérification requise | Oui |
| **Restauration cookie** | Hash + type + expiration + propriétaire | Oui |
| **Route publique restore-cookie** | Seul secret = plain token | Oui |
| **Aucun nouveau token** | Réutilise le token | Oui |
| **Google PKCE** | code_verifier + code_challenge S256 | Obligatoire |
| **Google state** | Usage unique + TTL | Obligatoire (web) |
| **Google nonce** | Anti-rejeu ID token | Obligatoire (web) |
| **Google JWKS** | Vérification RS256 | Automatique |
| **Google `aud`** | Contrôle audience | Automatique |
| **Google `email_verified`** | Refus si non vérifié | Automatique |
| **Android modèle** | Double vérification | `class_exists` + `MailAuthenticatable` |
| **Android création** | Déléguée à `onAndroidCallbackSuccess()` | — |

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
        $user = TestUserMail::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $payload = [
            'model_type' => TestUserMail::class,
            'email' => 'test@example.com',
            'password' => 'Password123!',
        ];

        $response = $this->postJson('/api/email-login', $payload);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'message',
            'auth' => ['id', 'name', 'email'],
            'token',
        ]);
    }

    public function test_login_with_cookie(): void
    {
        $this->app['config']->set('authentication-kit.store_token_in_cookie', true);
        $this->refreshConfigService();

        $user = TestUserMail::create([
            'name' => 'Cookie User',
            'email' => 'cookie@example.com',
            'password' => bcrypt('Password123!'),
        ]);

        $response = $this->postJson('/api/email-login', [
            'model_type' => TestUserMail::class,
            'email' => 'cookie@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200);
        $response->assertCookie('nemesis_token');
    }
}
```

---

## 🔄 Migration de versions

### De v1.11.0 à v1.13.1

1. **Renommage du middleware** : `ValidateMailAuthenticatable` → `ValidateMailAuthenticatableMiddleware`
2. **Interface `LogRepositoryInterface`** : `string $errorClass` → `ErrorType $errorType`
3. **Nouvelles clés de config** : `store_token_in_cookie`, `email_update_rate_limit`, `two_factor_rate_limit`
4. **`MustNemesis`** : implémenter `nemesisFormat()`
5. **Nouvelle route `/me`**

### De v1.13.x à v1.14.0 — Restauration de cookie

Ajouter :

```php
Route::post('/restore-cookie', action_route(
    RestoreCookieRequest::class,
    RestoreCookieAction::class
))->name('restore-cookie');
```

Hooks : `beforeRestoreCookie`, `afterRestoreCookie`.

### De v1.14.x à v1.15.0 — Google OAuth

1. **Configuration** : ajouter le bloc `google` dans `config/authentication-kit.php`
2. **Nouvelles méthodes** dans `AuthenticationKitConfigInterface` (Google)
3. **Service Provider** : `GoogleServiceProvider` enregistré automatiquement
4. **Routes Google** : `/google-redirect`, `/google-callback`

### De v1.15.x à v1.16.0 — Google OAuth Android + VOs

1. **Nouveaux VOs** dans le namespace `Google\ValueObjects` :
   - `GoogleCallbackVO` — reçu par `onCallbackSuccess()`
   - `GoogleAndroidCallbackVO` — reçu par `onAndroidCallbackSuccess()`
   - `GoogleIdTokenClaimsVO` — claims vérifiés (remplace `GoogleIdTokenClaimsData`)

2. **Contrat `GoogleOAuthInterface`** :
   - `onCallbackSuccess(GoogleCallbackVO $vo): GoogleCallbackData|ErrorResponseData`
   - `onAndroidCallbackSuccess(GoogleAndroidCallbackVO $vo): GoogleAndroidCallbackData|ErrorResponseData`

3. **Data de sortie** :
   - `GoogleCallbackData` — retournée par `onCallbackSuccess()` (auth + token + isNewUser + metadata)
   - `GoogleAndroidCallbackData` — retournée par `onAndroidCallbackSuccess()` (claims + modelType + auth + token + isNewUser + metadata)

4. **Nouvelle route** : `POST /api/google-android-callback` (groupe API, JSON uniquement)

5. **`GoogleCallbackAction`** : retourne désormais toujours une vue ou Inertia (jamais JSON), avec prop `success` ou `error`

6. **Modèle cible Android** : implémente `MailAuthenticatable`. La résolution/création de l'utilisateur et l'émission du token sont déléguées à `onAndroidCallbackSuccess()`.

### Migration du callback web (breaking change)

Le callback web ne retourne plus de JSON. Si votre application consommait `GoogleCallbackData` en JSON via `/google-callback` :

1. Migrez vers `/google-android-callback` si vous êtes un client mobile.
2. Ou surchargez `GoogleOAuthService::onCallbackSuccess()` pour retourner un `GoogleCallbackData`, et configurez `callback.type` = `"view"` / `"inertia"` pour le rendu.
3. Ou configurez `callback.type` = `"view"` / `"inertia"` et consommez la prop `success` dans la vue.

---

## 📦 Structure du package

```
src/
├── AuthenticationKitServiceProvider.php
├── Configs/
│   └── AuthenticationKitConfig.php
├── Enums/
│   ├── ErrorCode.php
│   └── ErrorType.php
├── Mail/
│   ├── Actions/
│   │   ├── EmailLoginAction.php
│   │   ├── EmailLogoutAction.php
│   │   ├── EmailRegisterAction.php
│   │   ├── GetCurrentUserAction.php
│   │   ├── ResetPasswordAction.php
│   │   ├── RestoreCookieAction.php
│   │   ├── SendPasswordResetLinkAction.php
│   │   ├── SendEmailVerificationAction.php
│   │   ├── ResendEmailVerificationAction.php
│   │   ├── SendEmailUpdateOtpAction.php
│   │   ├── UpdateEmailAction.php
│   │   ├── SendTwoFactorOtpAction.php
│   │   └── VerifyEmailAction.php
│   ├── Contracts/
│   │   ├── MailAuthenticatable.php
│   │   ├── MailAuthenticationInterface.php
│   │   └── Repositories/
│   │       └── LogRepositoryInterface.php
│   ├── Datas/
│   │   ├── AuthLoginData.php
│   │   ├── AuthRegisteredData.php
│   │   ├── AuthCookieRestoredData.php
│   │   └── ErrorResponseData.php
│   ├── Http/Middleware/
│   │   └── ValidateMailAuthenticatableMiddleware.php
│   ├── Records/
│   │   ├── EmailLoginAuthRecord.php
│   │   ├── EmailLogoutAuthRecord.php
│   │   ├── EmailRegisterAuthRecord.php
│   │   ├── RestoreCookieAuthRecord.php
│   │   ├── SendEmailUpdateOtpAuthRecord.php
│   │   ├── UpdateEmailAuthRecord.php
│   │   ├── SendTwoFactorOtpAuthRecord.php
│   │   └── VerifyTwoFactorOtpAuthRecord.php
│   ├── Repositories/
│   │   └── LogRepository.php
│   ├── Requests/
│   │   ├── EmailLoginRequest.php
│   │   ├── EmailLogoutRequest.php
│   │   ├── EmailRegisterRequest.php
│   │   ├── RestoreCookieRequest.php
│   │   ├── SendEmailUpdateOtpRequest.php
│   │   ├── UpdateEmailRequest.php
│   │   ├── SendTwoFactorOtpRequest.php
│   │   └── VerifyTwoFactorOtpRequest.php
│   ├── Services/
│   │   └── MailAuthenticationService.php
│   ├── MailServiceProvider.php
│   └── routes.php
└── Google/
    ├── Actions/
    │   ├── GoogleAndroidCallbackAction.php
    │   ├── GoogleCallbackAction.php
    │   └── GoogleRedirectAction.php
    ├── Contracts/
    │   ├── GoogleOAuthInterface.php
    │   └── GoogleStateStoreInterface.php
    ├── Datas/
    │   ├── GoogleAndroidCallbackData.php
    │   ├── GoogleCallbackData.php
    │   ├── GoogleRedirectData.php
    │   └── GoogleTokensData.php
    ├── Enums/
    │   └── GoogleEndpoint.php
    ├── Exceptions/
    │   └── GoogleOAuthException.php
    ├── Records/
    │   ├── GoogleAndroidCallbackRecord.php
    │   ├── GoogleCallbackRecord.php
    │   ├── GoogleRedirectRecord.php
    │   └── GoogleStateRecord.php
    ├── Requests/
    │   ├── EmptyRequest.php
    │   ├── GoogleAndroidCallbackRequest.php
    │   ├── GoogleAuthorizationRedirectRequest.php
    │   ├── GoogleCallbackRequest.php
    │   ├── GoogleRedirectRequest.php
    │   └── GoogleTokenExchangeRequest.php
    ├── Responses/
    │   ├── Concerns/
    │   │   └── ReadsPayload.php
    │   ├── GoogleJwksResponse.php
    │   └── GoogleTokenExchangeResponse.php
    ├── Services/
    │   ├── GoogleIdTokenVerifier.php
    │   ├── GoogleOAuthService.php
    │   ├── GooglePkceGenerator.php
    │   └── GoogleStateStore.php
    ├── Structs/
    │   └── GoogleTokenExchangeBodyStruct.php
    ├── ValueObjects/
    │   ├── GoogleAndroidCallbackVO.php
    │   ├── GoogleCallbackVO.php
    │   └── GoogleIdTokenClaimsVO.php
    ├── GoogleServiceProvider.php
    └── routes.php
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
        return Http::post(self::BASE_URL . '/email-register', [
            'model_type' => self::MODEL_TYPE,
            ...$data,
        ])->json();
    }

    public function login(string $email, string $password): array
    {
        $response = Http::post(self::BASE_URL . '/email-login', [
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
        return Http::withToken(session('auth_token'))
            ->post(self::BASE_URL . '/get-current-user')
            ->json();
    }

    public function logout(): void
    {
        $token = session('auth_token');

        Http::withToken($token)->post(self::BASE_URL . '/email-logout', [
            'model_type' => self::MODEL_TYPE,
            'token' => $token,
        ]);

        session()->forget('auth_token');
    }

    public function forgotPassword(string $email): array
    {
        return Http::post(self::BASE_URL . '/send-password-link', [
            'email' => $email,
            'model_type' => self::MODEL_TYPE,
        ])->json();
    }

    public function resetPassword(string $email, string $token, string $password): array
    {
        return Http::post(self::BASE_URL . '/reset-password', [
            'model_type' => self::MODEL_TYPE,
            'email' => $email,
            'token' => $token,
            'password' => $password,
            'password_confirmation' => $password,
        ])->json();
    }

    public function sendVerification(int $userId): array
    {
        return Http::withToken(session('auth_token'))
            ->post(self::BASE_URL . '/send-email-verification', [
                'model_type' => self::MODEL_TYPE,
                'auth_id' => $userId,
            ])
            ->json();
    }

    public function verifyEmail(string $email, string $token): array
    {
        return Http::post(self::BASE_URL . '/verify-email', [
            'model_type' => self::MODEL_TYPE,
            'email' => $email,
            'token' => $token,
        ])->json();
    }

    public function sendEmailUpdateOtp(string $newEmail): array
    {
        return Http::withToken(session('auth_token'))
            ->post(self::BASE_URL . '/send-email-update-otp', [
                'model_type' => self::MODEL_TYPE,
                'email' => $newEmail,
            ])
            ->json();
    }

    public function updateEmail(string $newEmail, string $code): array
    {
        return Http::withToken(session('auth_token'))
            ->patch(self::BASE_URL . '/update-email', [
                'model_type' => self::MODEL_TYPE,
                'email' => $newEmail,
                'code' => $code,
            ])
            ->json();
    }

    public function sendTwoFactorOtp(string $purpose): array
    {
        return Http::withToken(session('auth_token'))
            ->post(self::BASE_URL . '/send-two-factor-otp', [
                'model_type' => self::MODEL_TYPE,
                'two_factor_purpose' => $purpose,
            ])
            ->json();
    }

    public function verifyTwoFactorOtp(string $purpose, string $code): array
    {
        return Http::withToken(session('auth_token'))
            ->post(self::BASE_URL . '/verify-two-factor-otp', [
                'model_type' => self::MODEL_TYPE,
                'two_factor_purpose' => $purpose,
                'code' => $code,
            ])
            ->json();
    }

    public function restoreCookie(string $plainToken, bool $rememberMe = true): array
    {
        return Http::post(self::BASE_URL . '/restore-cookie', [
            'model_type' => self::MODEL_TYPE,
            'token' => $plainToken,
            'remember_me' => $rememberMe,
        ])->json();
    }

    public function googleRedirect(): array
    {
        return Http::post(self::BASE_URL . '/google-redirect', [
            'state' => '',
            'code_challenge' => '',
            'nonce' => '',
        ])->json();
    }

    public function googleAndroidCallback(string $idToken): array
    {
        return Http::post(self::BASE_URL . '/google-android-callback', [
            'model_type' => self::MODEL_TYPE,
            'id_token' => $idToken,
        ])->json();
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
      const response = await fetch(`${API_URL}/email-register`, {
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
      const response = await fetch(`${API_URL}/email-login`, {
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
      const response = await fetch(`${API_URL}/get-current-user`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${this.token}`,
        },
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
      await fetch(`${API_URL}/email-logout`, {
        method: 'POST',
        credentials: 'include',
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

  async sendEmailUpdateOtp(newEmail: string): Promise<{ success: boolean; data?: any; error?: ErrorResponse }> {
    try {
      const response = await fetch(`${API_URL}/send-email-update-otp`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${this.token}`,
        },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
          email: newEmail,
        }),
      });

      const result = await response.json();
      return { success: response.ok, data: result, error: response.ok ? undefined : result };
    } catch (error) {
      return { success: false, error: error as ErrorResponse };
    }
  }

  async updateEmail(newEmail: string, code: string): Promise<{ success: boolean; data?: any; error?: ErrorResponse }> {
    try {
      const response = await fetch(`${API_URL}/update-email`, {
        method: 'PATCH',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${this.token}`,
        },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
          email: newEmail,
          code,
        }),
      });

      const result = await response.json();
      return { success: response.ok, data: result, error: response.ok ? undefined : result };
    } catch (error) {
      return { success: false, error: error as ErrorResponse };
    }
  }

  async sendTwoFactorOtp(purpose: string): Promise<{ success: boolean; data?: any; error?: ErrorResponse }> {
    try {
      const response = await fetch(`${API_URL}/send-two-factor-otp`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${this.token}`,
        },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
          two_factor_purpose: purpose,
        }),
      });

      const result = await response.json();
      return { success: response.ok, data: result, error: response.ok ? undefined : result };
    } catch (error) {
      return { success: false, error: error as ErrorResponse };
    }
  }

  async verifyTwoFactorOtp(purpose: string, code: string): Promise<{ success: boolean; data?: any; error?: ErrorResponse }> {
    try {
      const response = await fetch(`${API_URL}/verify-two-factor-otp`, {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${this.token}`,
        },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
          two_factor_purpose: purpose,
          code,
        }),
      });

      const result = await response.json();
      return { success: response.ok, data: result, error: response.ok ? undefined : result };
    } catch (error) {
      return { success: false, error: error as ErrorResponse };
    }
  }

  async restoreCookie(): Promise<{ success: boolean; data?: any; error?: ErrorResponse }> {
    const token = localStorage.getItem('auth_token');

    if (token === null) {
      return { success: false, error: { message: 'No token stored', status: 401, errorCode: 'NO_TOKEN' } };
    }

    try {
      const response = await fetch(`${API_URL}/restore-cookie`, {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          model_type: MODEL_TYPE,
          token,
          remember_me: true,
        }),
      });

      const result = await response.json();

      if (response.ok) {
        return { success: true, data: result };
      }

      localStorage.removeItem('auth_token');
      localStorage.removeItem('user');
      this.token = null;

      return { success: false, error: result };
    } catch (error) {
      return { success: false, error: error as ErrorResponse };
    }
  }

  async googleLogin(): Promise<void> {
    const redirectResponse = await fetch(`${API_URL}/google-redirect`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ state: '', code_challenge: '', nonce: '' }),
    });

    const { url } = await redirectResponse.json();

    window.location.href = url;
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
import React, { useState } from 'react';
import { authService } from '../services/auth.service';

const Login: React.FC = () => {
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    const result = await authService.login(email, password);

    if (result.success) {
      window.location.href = '/dashboard';
    } else {
      setError(result.error?.message || 'Erreur de connexion');
    }

    setLoading(false);
  };

  const handleGoogleLogin = async () => {
    await authService.googleLogin();
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
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            placeholder="john@example.com"
          />
        </div>

        <div className="form-group">
          <label htmlFor="password">Mot de passe</label>
          <input
            type="password"
            id="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            placeholder="••••••••"
          />
        </div>

        <button type="submit" disabled={loading}>
          {loading ? 'Connexion...' : 'Se connecter'}
        </button>

        <button type="button" onClick={handleGoogleLogin} className="google-login">
          Se connecter avec Google
        </button>
      </form>
    </div>
  );
};

export default Login;
```

### Composant Dashboard React

```tsx
import React, { useEffect, useState } from 'react';
import { authService } from '../services/auth.service';

const Dashboard: React.FC = () => {
  const [user, setUser] = useState<any>(null);
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

  if (loading) return <div>Chargement...</div>;
  if (error || !user) return <div>Erreur: {error || 'Utilisateur non trouvé'}</div>;

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

### Composant ChangePassword avec 2FA

```tsx
import React, { useState } from 'react';
import { authService } from '../services/auth.service';

const ChangePassword: React.FC = () => {
  const [step, setStep] = useState<'request' | 'verify'>('request');
  const [newPassword, setNewPassword] = useState('');
  const [code, setCode] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleRequest2FA = async () => {
    setLoading(true);
    setError(null);

    const result = await authService.sendTwoFactorOtp('change_password');

    if (result.success) {
      setStep('verify');
    } else {
      setError(result.error?.message || 'Impossible d\'envoyer le code 2FA');
    }

    setLoading(false);
  };

  const handleConfirm = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    setError(null);

    const verifyResult = await authService.verifyTwoFactorOtp('change_password', code);

    if (!verifyResult.success) {
      setError(verifyResult.error?.message || 'Code 2FA invalide');
      setLoading(false);
      return;
    }

    const response = await fetch('/api/change-password', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        Authorization: `Bearer ${authService.getToken()}`,
      },
      body: JSON.stringify({ new_password: newPassword }),
    });

    if (response.ok) {
      alert('Mot de passe modifié avec succès');
    } else {
      setError('Erreur lors du changement de mot de passe');
    }

    setLoading(false);
  };

  return (
    <div className="change-password">
      <h1>Changer le mot de passe</h1>

      {error && <div className="error">{error}</div>}

      {step === 'request' && (
        <>
          <p>Pour modifier votre mot de passe, une vérification 2FA est requise.</p>
          <button onClick={handleRequest2FA} disabled={loading}>
            {loading ? 'Envoi...' : 'Recevoir un code de vérification'}
          </button>
        </>
      )}

      {step === 'verify' && (
        <form onSubmit={handleConfirm}>
          <div className="form-group">
            <label>Nouveau mot de passe</label>
            <input
              type="password"
              value={newPassword}
              onChange={(e) => setNewPassword(e.target.value)}
              required
            />
          </div>
          <div className="form-group">
            <label>Code 2FA</label>
            <input
              type="text"
              value={code}
              onChange={(e) => setCode(e.target.value)}
              required
              maxLength={10}
              placeholder="483920"
            />
          </div>
          <button type="submit" disabled={loading}>
            {loading ? 'Vérification...' : 'Confirmer le changement'}
          </button>
        </form>
      )}
    </div>
  );
};

export default ChangePassword;
```

### Composant AuthBootstrap React (restauration automatique)

```tsx
import React, { useEffect, useState } from 'react';
import { authService } from '../services/auth.service';

const AuthBootstrap: React.FC<{ children: React.ReactNode }> = ({ children }) => {
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const bootstrap = async () => {
      const meResult = await authService.me();

      if (!meResult.success && authService.getToken() !== null) {
        await authService.restoreCookie();
      }

      setReady(true);
    };

    bootstrap();
  }, []);

  if (!ready) {
    return <div>Initialisation...</div>;
  }

  return <>{children}</>;
};

export default AuthBootstrap;
```

---

## 🍪 Restauration de cookie

### Le problème

Les navigateurs suppriment parfois les cookies d'authentification sans prévenir : nettoyage de vie privée, expiration implicite, politique de sécurité stricte, etc. L'utilisateur se retrouve déconnecté alors que son **token en clair est toujours valide** et toujours stocké côté client (localStorage pour les SPA, stockage natif pour les applications mobiles).

### La solution

`POST /restore-cookie` permet de **réémettre le cookie** à partir du token qu'il détient encore, sans reconnexion.

**Ce que le client envoie :**

- `model_type` : FQCN du modèle
- `token` : le plain token détenu
- `remember_me` (optionnel) : `true` pour un cookie persistant, `false` pour un cookie de session

**Ce que le serveur fait :**

1. Hache le token reçu (algorithme de `NemesisConfig::tokenConfig()->hash_algorithm`)
2. Retrouve le token via `NemesisInterface::findByHash()`
3. Vérifie que le token est **valide** (non révoqué, non expiré, `tokenable_type` cohérent)
4. Retrouve le modèle propriétaire
5. Réémet le cookie via `CookieTokenStorage::store()`

**Aucun nouveau token n'est créé.** Le cookie restauré porte exactement le même token.

### Route

```php
Route::middleware(['validate.mail.authenticatable'])->group(function (): void {
    Route::post('/restore-cookie', action_route(
        RestoreCookieRequest::class,
        RestoreCookieAction::class
    ))->name('restore-cookie');
});
```

La route est **publique** : c'est parce que le cookie a disparu que le client ne peut plus s'authentifier autrement.

### Requête / Réponse

**Requête :**
```json
{
    "model_type": "App\\Models\\User",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "remember_me": true
}
```

**Réponse (200 OK) :**
```json
{
    "message": "Cookie restored successfully",
    "auth": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "emailVerifiedAt": "2026-08-14T12:00:00+00:00",
        "createdAt": "2026-08-14T10:00:00+00:00",
        "updatedAt": "2026-08-14T12:00:00+00:00"
    }
}
```

**Erreur - Token invalide ou expiré (401) :**
```json
{
    "message": "Invalid token",
    "status": 401,
    "errorCode": "INVALID_TOKEN"
}
```

### Conditions de réussite

La restauration ne réussit que si **toutes** les conditions suivantes sont réunies :

| Condition | Vérifiée par |
|-----------|--------------|
| Cookie activé | `shouldStoreTokenInCookie()` |
| Token existe en base | `NemesisInterface::findByHash()` |
| `tokenable_type` correspond au modèle | Comparaison stricte |
| Token non révoqué | `$token->trashed()` |
| Token non expiré | `$token->expires_at->isPast()` |
| Utilisateur présent | `Model::find()` |

Toute condition non remplie → `null` → réponse `401` avec `errorCode: INVALID_TOKEN`.

### Comportement du cookie

| Paramètre | Effet |
|-----------|-------|
| `remember_me = true` | Cookie persistant (`getCookieDuration()`) |
| `remember_me = false` | Cookie de session |
| `store_token_in_cookie = false` | Aucun cookie, réponse 401 systématique |

### Intégration côté client

```typescript
async function bootstrapAuth(): Promise<void> {
    const token = localStorage.getItem('auth_token');

    if (token === null) return;

    const meResponse = await fetch('/api/get-current-user', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
    });

    if (meResponse.ok) return;

    // Cookie disparu → tenter la restauration
    const restoreResponse = await fetch('/api/restore-cookie', {
        method: 'POST',
        credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            model_type: 'App\\Models\\User',
            token,
            remember_me: true,
        }),
    });

    if (!restoreResponse.ok) {
        localStorage.removeItem('auth_token');
        localStorage.removeItem('user');
        window.location.href = '/login';
    }
}
```

### Sécurité

- **Route publique** : seul secret = plain token (déjà porteur de credentials). Aucune donnée sensible supplémentaire ne circule dans le body.
- **Pas de nouvelle ligne en base** : on réutilise le token existant.
- **Vérification stricte du `tokenable_type`** : impossible de restaurer un cookie pour un modèle avec un token qui ne lui appartient pas.
- **Rate limiting recommandé** : `->middleware('throttle:10,1')` si nécessaire.

### Différences avec `/get-current-user`

| Endpoint | Rôle |
|----------|------|
| `POST /get-current-user` | **Lit** le cookie ou le Bearer pour identifier l'utilisateur courant |
| `POST /restore-cookie` | **Écrit** un nouveau cookie à partir d'un plain token |