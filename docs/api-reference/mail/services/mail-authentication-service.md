# MailAuthenticationService - Référence Technique

## Description

Service générique d'authentification par email qui pilote l'inscription, la connexion, la déconnexion, la vérification d'email, la réinitialisation de mot de passe, la mise à jour d'email et l'authentification à deux facteurs pour tout modèle Eloquent implémentant `MailAuthenticatable`.

## Hiérarchie / Implémentations

```
MailAuthenticationInterface
    └── MailAuthenticationService
            └── (classes utilisateur personnalisées)

Dépendances injectées :
    - NemesisInterface
    - OtpService
    - LogRepositoryInterface
    - AuthenticationKitConfigInterface
    - CookieTokenStorageInterface
```

## Rôle principal

Le service centralise toute la logique d'authentification multi-modèles. Il orchestre :

- La persistance des utilisateurs via le modèle
- La génération et la validation des OTP (`OtpService`)
- La création et la révocation des tokens (`Nemesis`)
- La journalisation des événements (`LogRepository`)
- L'envoi des notifications (email via `MailChannel`)
- Le stockage optionnel des tokens dans un cookie sécurisé

Le service n'est **pas** destiné à être instancié directement. Il doit être obtenu via la factory statique `for()`.

## Installation

```bash
composer require andydefer/authentication-kit
php artisan vendor:publish --tag=authentication-kit-config
```

## API / Méthodes publiques

### `for(string $modelClass): static`

Crée une instance du service pour une classe de modèle donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | FQCN du modèle Eloquent à utiliser |

**Retourne :** `static` - Instance du service liée au modèle

**Exceptions :**
- `InvalidArgumentException` si la classe n'existe pas
- `InvalidArgumentException` si la classe n'implémente pas `MailAuthenticatable`

**Exemple :**
```php
$service = MailAuthenticationService::for(User::class);
```

---

### `register(AbstractRecord $record): array`

Enregistre un nouvel utilisateur à partir d'un `EmailRegisterAuthRecord`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `EmailRegisterAuthRecord` | Données d'inscription validées |

**Retourne :** `array{user: Model&Authenticatable, token: NemesisToken|null, plain_token: string|null}`

**Exceptions :**
- `InvalidArgumentException` si le record n'est pas un `EmailRegisterAuthRecord`
- `ValidationException` si les données ne passent pas la validation

**Exemple :**
```php
$record = new EmailRegisterAuthRecord(
    model_type: User::class,
    data: new StrictDataObject([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]),
    with_token: true,
);

$result = $service->register($record);
$user = $result['user'];
$token = $result['plain_token'];
```

---

### `login(string $email, string $password): ?LoginResultRecord`

Authentifie un utilisateur par email et mot de passe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Adresse email |
| `$password` | `string` | Mot de passe en clair |

**Retourne :** `LoginResultRecord|null` - Résultat contenant le token, ou `null` si échec

**Exemple :**
```php
$result = $service->login('john@example.com', 'Password123!');

if ($result !== null) {
    echo $result->plain_token;
}
```

---

### `logout(Authenticatable&Model $authenticatable, string $plainToken): bool`

Révoque le token fourni et déconnecte l'utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | Utilisateur authentifié |
| `$plainToken` | `string` | Token en clair à révoquer |

**Retourne :** `bool` - `true` si la révocation a réussi

---

### `sendPasswordResetOtp(string $email): bool`

Envoie un OTP de réinitialisation de mot de passe par email.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Adresse email de l'utilisateur |

**Retourne :** `bool` - `true` si l'OTP a été envoyé

---

### `resetPassword(string $email, string $code, string $password): bool`

Réinitialise le mot de passe avec un OTP valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Adresse email |
| `$code` | `string` | Code OTP |
| `$password` | `string` | Nouveau mot de passe |

**Retourne :** `bool` - `true` si la réinitialisation a réussi

---

### `sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool`

Envoie un OTP de vérification d'email. Si l'email est déjà vérifié, retourne `true` sans envoyer.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | Utilisateur cible |

**Retourne :** `bool` - `true` si l'OTP a été envoyé ou si l'email était déjà vérifié

---

### `verifyEmail(string $email, string $code): bool`

Vérifie l'email avec un OTP.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Adresse email |
| `$code` | `string` | Code OTP |

**Retourne :** `bool` - `true` si l'email est vérifié (ou déjà vérifié)

---

### `resendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool`

Alias de `sendEmailVerificationOtp()`, prévu pour la clarté sémantique.

**Retourne :** `bool` - Même sémantique que `sendEmailVerificationOtp()`

---

### `isEmailVerified(Authenticatable&Model $authenticatable): bool`

Vérifie si l'email est vérifié.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | Utilisateur à vérifier |

**Retourne :** `bool` - `true` si `email_verified_at` est non-null

---

### `userExists(string|Transformable $email): bool`

Vérifie si un utilisateur existe pour l'email donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string\|Transformable` | Adresse email |

**Retourne :** `bool` - `true` si un enregistrement existe

---

### `sendEmailUpdateOtp(Authenticatable&Model $authenticatable, string $newEmail): bool`

Envoie un OTP sur la nouvelle adresse email pour confirmer le changement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | Utilisateur cible |
| `$newEmail` | `string` | Nouvelle adresse email souhaitée |

**Retourne :** `bool` - `true` si l'OTP a été envoyé

**Contraintes :**
- Retourne `false` si la nouvelle adresse est déjà utilisée
- Rate limit : par défaut 3 tentatives par fenêtre d'expiration

---

### `updateEmail(Authenticatable&Model $authenticatable, string $newEmail, string $code): bool`

Confirme le changement d'email avec un OTP. Réinitialise `email_verified_at` à `null`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | Utilisateur cible |
| `$newEmail` | `string` | Nouvelle adresse email |
| `$code` | `string` | Code OTP reçu |

**Retourne :** `bool` - `true` si l'email a été mis à jour

---

### `sendTwoFactorOtp(Authenticatable&Model $authenticatable, string $purpose): bool`

Envoie un OTP de vérification pour une action sensible.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | Utilisateur cible |
| `$purpose` | `string` | Contexte de l'action sensible (`change_email`, `change_password`, `delete_account`, etc.) |

**Retourne :** `bool` - `true` si l'OTP a été envoyé

**Note :** chaque `$purpose` a son propre espace d'OTP isolé via le préfixe `two_factor_{purpose}`.

---

### `verifyTwoFactorOtp(Authenticatable&Model $authenticatable, string $purpose, string $code): bool`

Vérifie un OTP de double authentification.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | Utilisateur cible |
| `$purpose` | `string` | Contexte de l'action sensible |
| `$code` | `string` | Code OTP à vérifier |

**Retourne :** `bool` - `true` si le code est valide

---

### `getPasswordValidationRules(): array`

Retourne les règles de validation du mot de passe.

**Retourne :** `array<string, array<int, mixed>>` - Règles de validation Laravel

**Exemple :**
```php
$rules = MailAuthenticationService::getPasswordValidationRules();
// ['password' => ['required', 'string', 'min:8', 'confirmed']]
```

---

## Hooks extensibles (protected)

Ces méthodes sont appelées automatiquement par le service. Les surcharger dans une sous-classe permet d'injecter de la logique métier.

| Hook | Appelé avant | Après (succès) |
|------|--------------|----------------|
| `beforeRegister()` / `afterRegister()` | `register()` | `register()` |
| `beforeLogin()` / `afterLogin()` | `login()` | `login()` |
| `beforeLogout()` / `afterLogout()` | `logout()` | `logout()` |
| `beforeSendPasswordResetOtp()` / `afterSendPasswordResetOtp()` | `sendPasswordResetOtp()` | `sendPasswordResetOtp()` (reçoit `$success`) |
| `beforeResetPassword()` / `afterResetPassword()` | `resetPassword()` | `resetPassword()` |
| `beforeVerifyEmail()` / `afterVerifyEmail()` | `verifyEmail()` | `verifyEmail()` |
| `beforeSendEmailUpdateOtp()` / `afterSendEmailUpdateOtp()` | `sendEmailUpdateOtp()` | `sendEmailUpdateOtp()` |
| `beforeUpdateEmail()` / `afterUpdateEmail()` | `updateEmail()` | `updateEmail()` |
| `beforeSendTwoFactorOtp()` / `afterSendTwoFactorOtp()` | `sendTwoFactorOtp()` | `sendTwoFactorOtp()` |
| `beforeVerifyTwoFactorOtp()` / `afterVerifyTwoFactorOtp()` | `verifyTwoFactorOtp()` | `verifyTwoFactorOtp()` |

---

## Méthodes de notification extensibles

Ces méthodes construisent le contenu du message envoyé. Les surcharger permet de personnaliser le sujet et le corps (HTML, vue Blade, markdown, etc.).

### `buildPasswordResetNotification(string|Transformable $email, string|Transformable $otp): NotificationMessageRecord`
### `buildEmailVerificationNotification(string|Transformable $email, string|Transformable $otp): NotificationMessageRecord`
### `buildEmailUpdateNotification(string|Transformable $email, string|Transformable $otp): NotificationMessageRecord`
### `buildTwoFactorNotification(string|Transformable $email, string|Transformable $otp, string|Transformable $purpose): NotificationMessageRecord`

---

## Cas d'utilisation

### Cas 1 : Cycle de vie complet d'un utilisateur

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use App\Models\User;

$service = MailAuthenticationService::for(User::class);

// Inscription
$record = new EmailRegisterAuthRecord(
    model_type: User::class,
    data: new StrictDataObject([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]),
    with_token: true,
);

$result = $service->register($record);
$user = $result['user'];

// Vérification email
$service->sendEmailVerificationOtp($user);
// (l'utilisateur reçoit le code par email)
$service->verifyEmail('john@example.com', '123456');

// Connexion ultérieure
$login = $service->login('john@example.com', 'Password123!');

// Déconnexion
$service->logout($user, $login->plain_token);
```

---

### Cas 2 : Changement d'email avec OTP

```php
<?php

$user = User::find(1);

// 1. Envoyer l'OTP sur la nouvelle adresse
$sent = $service->sendEmailUpdateOtp($user, 'new@example.com');

if (! $sent) {
    throw new RuntimeException('Email already in use or rate limited');
}

// 2. Confirmer avec le code reçu
$updated = $service->updateEmail($user, 'new@example.com', '123456');

// 3. L'email_verified_at est réinitialisé, il faut revérifier
$service->sendEmailVerificationOtp($user);
```

---

### Cas 3 : Double authentification avant action sensible

```php
<?php

$user = User::find(1);

// Demander un code 2FA pour le changement de mot de passe
$service->sendTwoFactorOtp($user, 'change_password');

// L'utilisateur reçoit le code par email
$code = $request->input('code');

if (! $service->verifyTwoFactorOtp($user, 'change_password', $code)) {
    return response()->json(['error' => 'Invalid 2FA code'], 400);
}

// Le code est valide : procéder au changement de mot de passe
$user->password = Hash::make($request->input('new_password'));
$user->save();
```

---

### Cas 4 : Personnalisation des notifications

```php
<?php

declare(strict_types=1);

namespace App\Services;

use AndyDefer\AuthenticationKit\Mail\Records\NotificationMessageRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\DomainStructures\Interfaces\Transformable;

final class BrandedAuthService extends MailAuthenticationService
{
    protected function buildTwoFactorNotification(
        string|Transformable $email,
        string|Transformable $otp,
        string|Transformable $purpose,
    ): NotificationMessageRecord {
        return NotificationMessageRecord::from([
            'email' => $email,
            'subject' => 'Votre code de sécurité',
            'body' => view('emails.auth.two-factor', [
                'otp' => $this->normalize($otp),
                'purpose' => $this->normalize($purpose),
            ])->render(),
        ]);
    }
}
```

---

## Flux d'exécution

### Changement d'email

```
sendEmailUpdateOtp()
    → beforeSendEmailUpdateOtp()
    → userExists(nouveau) ?
        ├─ true  → log failure + return false
        └─ false → isRateLimited() ?
            ├─ true  → log failure + return false
            └─ false → OtpService::create()
                       → buildEmailUpdateNotification()
                       → sendNotification()
                       → log success
                       → afterSendEmailUpdateOtp()
                       → return true

updateEmail()
    → beforeUpdateEmail()
    → OtpService::verify()
        ├─ false → log failure + return false
        └─ true  → update email + reset email_verified_at
                   → log success
                   → afterUpdateEmail()
                   → return true
```

### Double authentification

```
sendTwoFactorOtp($user, $purpose)
    → beforeSendTwoFactorOtp()
    → rateLimited() ? → return false
    → OtpService::create(purpose = "two_factor_{$purpose}")
    → buildTwoFactorNotification()
    → sendNotification()
    → afterSendTwoFactorOtp()
    → return true

verifyTwoFactorOtp($user, $purpose, $code)
    → beforeVerifyTwoFactorOtp()
    → OtpService::verify(purpose = "two_factor_{$purpose}")
    → log verified (success: bool)
    → if valid: afterVerifyTwoFactorOtp() + return true
    → return false
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Modèle inexistant | `InvalidArgumentException` | `Model class X does not exist` |
| Modèle non conforme | `InvalidArgumentException` | `Model X must implement ...MailAuthenticatable` |
| Record invalide pour `register()` | `InvalidArgumentException` | `Invalid record type` |
| Données invalides pour `register()` | `ValidationException` | Erreurs de validation Laravel |

**Note :** les autres méthodes ne lèvent pas d'exception sur échec métier. Elles retournent `false` ou `null` et laissent le `LogRepository` journaliser le détail (rate limit, email déjà pris, OTP invalide, etc.).

---

## Intégration

| Composant | Rôle |
|-----------|------|
| `NemesisInterface` | Génération et révocation des tokens d'authentification |
| `OtpService` | Création, vérification et rate limiting des OTP |
| `LogRepositoryInterface` | Journalisation de tous les événements d'authentification |
| `AuthenticationKitConfigInterface` | Lecture des rate limits et du comportement cookie |
| `CookieTokenStorageInterface` | Stockage du token en cookie HttpOnly |
| `NotifiableBuilder` + `MailChannel` | Envoi des notifications par email |
| `MailAuthenticatable` | Contrat du modèle (email, email_verified_at, generate, nemesisFormat) |

---

## Performance

- Chaque opération OTP implique **2 requêtes** (count pour le rate limit + insert).
- Le rate limit s'appuie sur un `COUNT` indexé par `identifier_type` + `identifier_id`, donc rapide.
- La journalisation est **synchrone** par défaut. En production à fort trafic, envisager de passer le logger en queue.
- Aucun cache interne : les tokens et OTP sont revalidés à chaque requête (sécurité prioritaire sur la performance).
- Le service est **stateless** et peut être réutilisé entre plusieurs modèles (une instance par modèle via `for()`).

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ⚠️ Non testé (types `readonly` requis) |
| PHP 8.0 | ❌ Incompatible |
| Laravel 12.x | ✅ Complet |
| Laravel 13.x | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use App\Models\User;

$service = MailAuthenticationService::for(User::class);

// 1. Inscription avec token
$registration = $service->register(new EmailRegisterAuthRecord(
    model_type: User::class,
    data: new StrictDataObject([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]),
    with_token: true,
));

$user = $registration['user'];

// 2. Vérification d'email
$service->sendEmailVerificationOtp($user);
$service->verifyEmail('john@example.com', '123456');

// 3. Connexion
$login = $service->login('john@example.com', 'Password123!');

// 4. Changement d'email protégé par OTP
$service->sendEmailUpdateOtp($user, 'new@example.com');
$service->updateEmail($user, 'new@example.com', '654321');

// 5. Action sensible protégée par 2FA
$service->sendTwoFactorOtp($user, 'change_password');
if ($service->verifyTwoFactorOtp($user, 'change_password', '111222')) {
    // Procéder au changement de mot de passe
    $user->password = Hash::make('NewPassword456!');
    $user->save();
}

// 6. Déconnexion
$service->logout($user, $login->plain_token);
```

---

## Voir aussi

- `MailAuthenticationInterface` - Contrat du service
- `LogRepositoryInterface` - Journalisation des événements
- `AuthenticationKitConfigInterface` - Configuration des rate limits
- `OtpService` - Moteur OTP sous-jacent (package `andydefer/laravel-otp`)
- `NemesisInterface` - Moteur de tokens (package `andydefer/laravel-nemesis`)
- `MailAuthenticatable` - Contrat des modèles authentifiables