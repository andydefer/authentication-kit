```markdown
# Actions du Package AuthenticationKit Mail - Référence Technique

## Vue d'ensemble

Le package `AuthenticationKit Mail` fournit un ensemble d'actions pour gérer l'authentification par email. Chaque action suit le pattern **Action** (héritage de `AbstractAction`) et implémente le cycle de vie : `before()` → `handle()` → `after()`.

---

## 1. EmailLoginAction

### Description

Authentifie un utilisateur par email et mot de passe. Génère un token JWT en cas de succès.

### Record d'entrée

```json
{
    "model_type": "App\\Models\\User",
    "data": {
        "email": "john@example.com",
        "password": "secret123"
    },
    "ip": "192.168.1.1",
    "user_agent": "Mozilla/5.0"
}
```

### Réponse succès (200)

```json
{
    "message": "Login successful",
    "auth": {
        "id": 1,
        "email": "john@example.com",
        "name": "John Doe"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

### Codes d'erreur

| Code | Status | Message |
|------|--------|---------|
| `MISSING_CREDENTIALS` | 400 | Email and password are required |
| `INVALID_CREDENTIALS` | 401 | Invalid credentials |
| `AUTHENTICATABLE_NOT_FOUND` | 404 | Authenticatable not found |

### Exemple

```php
$action = new EmailLoginAction($nemesis, $logRepository, $agent, $config);

$record = new EmailLoginAuthRecord(
    model_type: 'App\\Models\\User',
    data: new StrictDataObject([
        'email' => 'john@example.com',
        'password' => 'secret123',
    ])
);

$response = $action->execute($record);
```

---

## 2. EmailRegisterAction

### Description

Crée un nouvel utilisateur. Peut générer un token d'authentification automatiquement.

### Record d'entrée

```json
{
    "model_type": "App\\Models\\User",
    "with_token": true,
    "data": {
        "name": "John Doe",
        "email": "john@example.com",
        "password": "secret123"
    },
    "ip": "192.168.1.1",
    "user_agent": "Mozilla/5.0"
}
```

### Réponse succès (201)

```json
{
    "message": "Registration successful",
    "auth": {
        "id": 1,
        "email": "john@example.com",
        "name": "John Doe"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9..."
}
```

### Codes d'erreur

| Code | Status | Message |
|------|--------|---------|
| `MODEL_NOT_FOUND` | 500 | Model X does not exist |
| `INVALID_MODEL` | 500 | Model X must implement MailAuthenticatable |

### Exemple

```php
$action = new EmailRegisterAction($nemesis, $logRepository, $agent, $config);

$record = new EmailRegisterAuthRecord(
    model_type: 'App\\Models\\User',
    with_token: true,
    data: new StrictDataObject([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'secret123',
    ])
);

$response = $action->execute($record);
```

---

## 3. EmailLogoutAction

### Description

Déconnecte un utilisateur en invalidant son token d'authentification.

### Record d'entrée

```json
{
    "model_type": "App\\Models\\User",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "ip": "192.168.1.1",
    "user_agent": "Mozilla/5.0"
}
```

### Réponse succès (204)

```
(Empty response body)
```

### Codes d'erreur

| Code | Status | Message |
|------|--------|---------|
| `INVALID_TOKEN` | 401 | Invalid token |
| `TOKEN_EXPIRED` | 401 | Token expired |
| `AUTHENTICATABLE_NOT_FOUND` | 404 | Authenticatable not found |
| `LOGOUT_EXCEPTION` | 500 | Logout failed: [exception message] |
| `LOGOUT_FAILED` | 500 | Logout failed |

### Exemple

```php
$action = new EmailLogoutAction($nemesis, $logRepository);

$record = new EmailLogoutAuthRecord(
    model_type: 'App\\Models\\User',
    token: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'
);

$response = $action->execute($record);
// Status 204 si succès
```

---

## 4. SendEmailVerificationAction

### Description

Envoie un OTP de vérification d'email à un utilisateur.

### Record d'entrée

```json
{
    "model_type": "App\\Models\\User",
    "auth_id": 1
}
```

### Réponse succès (200)

```json
{
    "message": "Verification OTP sent successfully",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00",
    "alreadyVerified": false
}
```

### Réponse déjà vérifié (200)

```json
{
    "message": "Email already verified",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00",
    "alreadyVerified": true
}
```

### Codes d'erreur

| Code | Status | Message |
|------|--------|---------|
| `MODEL_NOT_FOUND` | 500 | Model X does not exist |
| `AUTHENTICATABLE_NOT_FOUND` | 404 | Authenticatable not found |
| `VERIFICATION_OTP_SEND_FAILED` | 500 | Failed to send verification OTP |

### Exemple

```php
$action = new SendEmailVerificationAction($authService, $logRepository);

$record = new SendEmailVerificationRecord(
    model_type: 'App\\Models\\User',
    auth_id: 1
);

$response = $action->execute($record);
```

---

## 5. ResendEmailVerificationAction

### Description

Renvoie un OTP de vérification d'email à un utilisateur.

### Record d'entrée

```json
{
    "model_type": "App\\Models\\User",
    "auth_id": 1
}
```

### Réponse succès (200)

```json
{
    "message": "Verification OTP resent successfully",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00",
    "alreadyVerified": false
}
```

### Codes d'erreur

| Code | Status | Message |
|------|--------|---------|
| `MODEL_NOT_FOUND` | 500 | Model X does not exist |
| `AUTHENTICATABLE_NOT_FOUND` | 404 | Authenticatable not found |
| `VERIFICATION_OTP_RESEND_FAILED` | 500 | Failed to resend verification OTP |

### Exemple

```php
$action = new ResendEmailVerificationAction($authService, $logRepository);

$record = new ResendEmailVerificationRecord(
    model_type: 'App\\Models\\User',
    auth_id: 1
);

$response = $action->execute($record);
```

---

## 6. VerifyEmailAction

### Description

Vérifie l'email d'un utilisateur avec un OTP.

### Record d'entrée

```json
{
    "email": "john@example.com",
    "token": "123456",
    "model_type": "App\\Models\\User"
}
```

### Réponse succès (200)

```json
{
    "message": "Email verified successfully",
    "email": "john@example.com",
    "verifiedAt": "2024-01-01T12:00:00+00:00",
    "alreadyVerified": false
}
```

### Réponse déjà vérifié (200)

```json
{
    "message": "Email already verified",
    "email": "john@example.com",
    "verifiedAt": "2024-01-01T12:00:00+00:00",
    "alreadyVerified": true
}
```

### Codes d'erreur

| Code | Status | Message |
|------|--------|---------|
| `INVALID_VERIFICATION_OTP` | 400 | Invalid or expired verification OTP |
| `VERIFY_EMAIL_ERROR` | 500 | An error occurred while verifying email |

### Exemple

```php
$action = new VerifyEmailAction($authService, $logRepository);

$record = new VerifyEmailRecord(
    email: 'john@example.com',
    token: '123456',
    model_type: 'App\\Models\\User'
);

$response = $action->execute($record);
```

---

## 7. SendPasswordResetLinkAction

### Description

Envoie un OTP de réinitialisation de mot de passe par email.

⚠️ **Sécurité** : Retourne toujours 200 pour ne pas révéler l'existence d'un email.

### Record d'entrée

```json
{
    "email": "john@example.com"
}
```

### Réponse (200 - toujours)

```json
{
    "message": "Password reset OTP sent successfully",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00"
}
```

### Codes d'erreur

| Code | Status | Message |
|------|--------|---------|
| `RESET_LINK_ERROR` | 500 | An error occurred while sending the reset OTP |

### Exemple

```php
$action = new SendPasswordResetLinkAction($authService, $logRepository);

$record = new SendPasswordResetLinkRecord(
    email: 'john@example.com'
);

$response = $action->execute($record);
// Toujours 200, même si l'email n'existe pas
```

---

## 8. ResetPasswordAction

### Description

Réinitialise le mot de passe d'un utilisateur avec un OTP valide.

### Record d'entrée

```json
{
    "email": "john@example.com",
    "token": "123456",
    "password": "newSecret123",
    "password_confirmation": "newSecret123"
}
```

### Réponse succès (200)

```json
{
    "message": "Password reset successfully",
    "email": "john@example.com",
    "resetAt": "2024-01-01T12:00:00+00:00"
}
```

### Codes d'erreur

| Code | Status | Message |
|------|--------|---------|
| `PASSWORD_CONFIRMATION_MISMATCH` | 422 | Password confirmation does not match |
| `INVALID_RESET_OTP` | 400 | Invalid or expired reset OTP |
| `RESET_PASSWORD_ERROR` | 500 | An error occurred while resetting the password |

### Exemple

```php
$action = new ResetPasswordAction($authService, $logRepository);

$record = new ResetPasswordRecord(
    email: 'john@example.com',
    token: '123456',
    password: 'newSecret123',
    password_confirmation: 'newSecret123'
);

$response = $action->execute($record);
```

---

## Résumé des endpoints

| Action | Endpoint typique | Méthode | Record |
|--------|------------------|---------|--------|
| `EmailLoginAction` | `/api/login` | POST | `EmailLoginAuthRecord` |
| `EmailRegisterAction` | `/api/register` | POST | `EmailRegisterAuthRecord` |
| `EmailLogoutAction` | `/api/logout` | POST | `EmailLogoutAuthRecord` |
| `SendEmailVerificationAction` | `/api/email/verification` | POST | `SendEmailVerificationRecord` |
| `ResendEmailVerificationAction` | `/api/email/resend` | POST | `ResendEmailVerificationRecord` |
| `VerifyEmailAction` | `/api/email/verify` | POST | `VerifyEmailRecord` |
| `SendPasswordResetLinkAction` | `/api/password/forgot` | POST | `SendPasswordResetLinkRecord` |
| `ResetPasswordAction` | `/api/password/reset` | POST | `ResetPasswordRecord` |

---

## Cycle de vie d'une Action

```
1. before()  - Préparation et validation du record
     ↓
2. handle()  - Logique métier principale
     ↓
3. after()   - Journalisation et nettoyage
```

### Ordre d'exécution

```php
$action->execute($record);
// Équivalent à :
$action->before($record);
$response = $action->handle($record);
$action->after($success, $error, $record);
return $response;
```

---

## Journalisation (Logging)

Toutes les actions journalisent leurs tentatives via `LogRepositoryInterface` :

### Méthodes de logging

| Action | Succès | Échec |
|--------|--------|-------|
| `EmailLoginAction` | `loginSuccess()` | `loginFailure()` |
| `EmailRegisterAction` | `logRegistrationSuccess()` | `logRegistrationFailure()` |
| `EmailLogoutAction` | `logoutSuccess()` | `logoutFailure()` |
| `SendEmailVerificationAction` | `logVerificationSuccess()` | `logVerificationFailure()` |
| `ResendEmailVerificationAction` | `logVerificationSuccess()` | `logVerificationFailure()` |
| `VerifyEmailAction` | `logVerificationSuccess()` | `logVerificationFailure()` |
| `SendPasswordResetLinkAction` | `logPasswordResetLinkSent()` | (dans la même méthode) |
| `ResetPasswordAction` | `logPasswordResetSuccess()` | `logPasswordResetFailure()` |

---

## Sécurité

### SendPasswordResetLinkAction

- **Retourne toujours 200** pour éviter l'énumération d'emails
- **Ne journalise pas** les tentatives sur des emails inexistants

### VerifyEmailAction

- Normalise l'email en **lowercase + trim**
- Supporte les **SoftDeletes** avec `withTrashed()`
- Journalise toutes les erreurs dans `after()`

### EmailLogoutAction

- Vérifie l'expiration du token
- Valide le modèle implémente `MailAuthenticatable`
- Journalise les échecs sans condition

## Voir aussi

- `MailAuthenticationInterface` - Contrat du service d'authentification
- `LogRepositoryInterface` - Contrat du service de journalisation
- `NemesisInterface` - Contrat du service de gestion des tokens
```