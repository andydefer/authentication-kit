# MailAuthenticationService - Référence Technique

## Description

Service générique d'authentification par email. Il orchestre l'inscription, la connexion, la déconnexion, la réinitialisation de mot de passe et la vérification d'email pour n'importe quel modèle Eloquent implémentant `MailAuthenticatable`.

## Hiérarchie

```
MailAuthenticationInterface
    └── MailAuthenticationService
```

## Rôle principal

Ce service agit comme un **orchestrateur** qui coordonne :

- **Laravel Otp** : Génération et validation des OTP
- **Nemesis** : Gestion des tokens d'authentification
- **Laravel Notification** : Envoi des emails
- **LogRepositoryInterface** : Journalisation des événements
- **Modèle utilisateur** : Création et gestion des comptes

Il est totalement **découplé du modèle** et fonctionne avec n'importe quel modèle implémentant `MailAuthenticatable`.

---

## API / Méthodes publiques

### `for(string $modelClass): self`

Crée une instance du service pour un modèle spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `string` | Le FQCN du modèle (ex: `User::class`) |

**Retourne :** `self` - Une instance du service typée pour le modèle

**Exceptions :** `InvalidArgumentException` si la classe n'existe pas ou n'implémente pas `MailAuthenticatable`

**Exemple :**
```php
$authService = MailAuthenticationService::for(User::class);
```

---

### `register(AbstractRecord $record): Model&Authenticatable`

Inscrit un nouvel utilisateur.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Record d'inscription (`EmailRegisterAuthRecord`) |

**Retourne :** `Model&Authenticatable` - L'utilisateur créé

**Exceptions :** `InvalidArgumentException` si le type de record est invalide, `ValidationException` si les données sont invalides

**Exemple :**
```php
$record = new EmailRegisterAuthRecord(
    model_type: User::class,
    with_token: true,
    data: new StrictDataObject([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])
);

$user = $authService->register($record);
```

---

### `login(string $email, string $password): ?NemesisTokenRecord`

Authentifie un utilisateur avec email et mot de passe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |
| `$password` | `string` | Mot de passe de l'utilisateur |

**Retourne :** `NemesisTokenRecord|null` - Le token d'authentification ou null si échec

**Exemple :**
```php
$token = $authService->login('john@example.com', 'Password123!');

if ($token !== null) {
    echo 'Connexion réussie : ' . $token->getPlainText();
}
```

---

### `logout(Authenticatable&Model $authenticatable, string $plainToken): bool`

Déconnecte un utilisateur en révoquant son token.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | L'utilisateur connecté |
| `$plainToken` | `string` | Le token en clair à révoquer |

**Retourne :** `bool` - True si la déconnexion a réussi

**Exemple :**
```php
$success = $authService->logout($user, $plainToken);
```

---

### `sendPasswordResetOtp(string $email): bool`

Envoie un OTP de réinitialisation de mot de passe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |

**Retourne :** `bool` - True si l'OTP a été envoyé

**Exemple :**
```php
$authService->sendPasswordResetOtp('john@example.com');
```

---

### `resetPassword(string $email, string $code, string $password): bool`

Réinitialise le mot de passe avec un OTP valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |
| `$code` | `string` | Code OTP |
| `$password` | `string` | Nouveau mot de passe |

**Retourne :** `bool` - True si la réinitialisation a réussi

**Exemple :**
```php
$success = $authService->resetPassword(
    'john@example.com',
    '123456',
    'NewPassword123!'
);
```

---

### `sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool`

Envoie un OTP de vérification d'email.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | L'utilisateur |

**Retourne :** `bool` - True si l'OTP a été envoyé

**Exemple :**
```php
$authService->sendEmailVerificationOtp($user);
```

---

### `verifyEmail(string $email, string $code): bool`

Vérifie l'email avec un OTP valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |
| `$code` | `string` | Code OTP |

**Retourne :** `bool` - True si la vérification a réussi

**Exemple :**
```php
$success = $authService->verifyEmail('john@example.com', '123456');
```

---

### `resendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool`

Renvoie l'OTP de vérification d'email.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | L'utilisateur |

**Retourne :** `bool` - True si l'OTP a été renvoyé

---

### `isEmailVerified(Authenticatable&Model $authenticatable): bool`

Vérifie si l'email d'un utilisateur est vérifié.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Authenticatable&Model` | L'utilisateur |

**Retourne :** `bool` - True si l'email est vérifié

---

### `userExists(string $email): bool`

Vérifie si un utilisateur existe avec cet email.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email à vérifier |

**Retourne :** `bool` - True si l'utilisateur existe

---

## Cas d'utilisation

### Cas 1 : Authentification complète avec email et mot de passe

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLoginAuthRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$authService = MailAuthenticationService::for(User::class);

$record = new EmailLoginAuthRecord(
    model_type: User::class,
    data: new StrictDataObject([
        'email' => 'john@example.com',
        'password' => 'Password123!',
    ])
);

$user = $authService->register($record);
$token = $authService->login('john@example.com', 'Password123!');
```

### Cas 2 : Vérification d'email et réinitialisation de mot de passe

```php
$authService = MailAuthenticationService::for(User::class);

// Envoyer l'OTP de vérification
$authService->sendEmailVerificationOtp($user);

// Vérifier l'email
$verified = $authService->verifyEmail('john@example.com', '123456');

// Envoyer l'OTP de réinitialisation
$authService->sendPasswordResetOtp('john@example.com');

// Réinitialiser le mot de passe
$reset = $authService->resetPassword('john@example.com', '123456', 'NewPassword123!');
```

### Cas 3 : Extension avec des hooks personnalisés

```php
final class CustomAuthService extends MailAuthenticationService
{
    protected function beforeRegister(AbstractRecord $record): void
    {
        // Vérifier l'IP
        if ($this->isIpBlocked($record->ip)) {
            throw new \RuntimeException('IP blocked');
        }
    }

    protected function afterRegister(Model&Authenticatable $user, AbstractRecord $record): void
    {
        // Créer un profil
        $user->profile()->create([
            'bio' => $record->data->get('bio'),
        ]);
    }

    protected function afterLogin(Model&Authenticatable $user): void
    {
        // Mettre à jour la dernière connexion
        $user->last_login_at = now();
        $user->save();
    }
}
```

---

## Flux d'exécution

```
register()
    ↓
beforeRegister() [HOOK]
    ↓
Validation (email, password)
    ↓
model::generate($data)
    ↓
Log Registration Success
    ↓
afterRegister() [HOOK]
    ↓
Retourne l'utilisateur

login()
    ↓
beforeLogin() [HOOK]
    ↓
Recherche utilisateur par email
    ↓
Vérification mot de passe
    ↓
Log Login Success
    ↓
afterLogin() [HOOK]
    ↓
Création du token Nemesis
    ↓
Retourne le token
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Record invalide | `InvalidArgumentException` | `Invalid record type` |
| Modèle inexistant | `InvalidArgumentException` | `Model class X does not exist` |
| Modèle non compatible | `InvalidArgumentException` | `Model X must implement MailAuthenticatable` |
| Validation échoue | `ValidationException` | Erreurs de validation Laravel |
| Utilisateur non trouvé | - | Log uniquement |
| Mot de passe invalide | - | Log uniquement |
| OTP invalide | - | Log uniquement |
| Rate limit dépassé | - | Log uniquement |

---

## Intégration

### Dépendances injectées

| Dépendance | Rôle |
|------------|------|
| `NemesisInterface` | Gestion des tokens |
| `OtpService` | Gestion des OTP |
| `LogRepositoryInterface` | Journalisation |

### Dépendances du modèle

Le modèle doit implémenter :

- `MailAuthenticatable` : Interface d'authentification
- `generate(array $data)` : Création d'un utilisateur

---

## Performance

- **Recherche utilisateur** : Indexé par email → O(log n)
- **Vérification OTP** : O(1) avec cache
- **Création token** : O(1) avec chiffrement
- **Rate limiting** : O(1) avec cache

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 9.x | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Records\EmailLoginAuthRecord;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

// 1. Créer le service
$authService = MailAuthenticationService::for(User::class);

// 2. Inscription
$registerRecord = new EmailRegisterAuthRecord(
    model_type: User::class,
    with_token: true,
    data: new StrictDataObject([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ])
);

$user = $authService->register($registerRecord);

// 3. Connexion
$loginRecord = new EmailLoginAuthRecord(
    model_type: User::class,
    data: new StrictDataObject([
        'email' => 'john@example.com',
        'password' => 'Password123!',
    ])
);

$token = $authService->login('john@example.com', 'Password123!');

// 4. Vérification email
$authService->sendEmailVerificationOtp($user);
$verified = $authService->verifyEmail('john@example.com', '123456');

// 5. Déconnexion
$logout = $authService->logout($user, $token->getPlainText());
```

---

## Voir aussi

- `MailAuthenticatable` - Interface du modèle authentifiable
- `MailAuthenticationInterface` - Interface du service
- `LogRepositoryInterface` - Interface de journalisation
- `EmailRegisterAuthRecord` - Record d'inscription
- `EmailLoginAuthRecord` - Record de connexion