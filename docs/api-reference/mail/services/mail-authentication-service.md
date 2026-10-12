# MailAuthenticationService - Référence Technique

## Description

Service générique d'authentification par email, compatible avec tout modèle Eloquent implémentant `MailAuthenticatable`. Il orchestre l'inscription, la connexion, la déconnexion, la restauration de cookie, la réinitialisation de mot de passe, la vérification d'email, la mise à jour d'email et l'authentification à deux facteurs (2FA) via OTP.

## Hiérarchie / Implémentations

```
MailAuthenticationInterface
    └── MailAuthenticationService

Dépendances injectées :
    - NemesisInterface            (gestion des tokens)
    - OtpService                  (création/vérification des OTP)
    - LogRepositoryInterface      (journalisation)
    - AuthenticationKitConfigInterface  (configuration)
    - CookieTokenStorageInterface (stockage cookie)
    - NemesisConfigInterface      (algorithme de hash)
```

## Rôle principal

Ce service est le point d'entrée métier du package Authentication Kit. Il encapsule toute la logique d'authentification par email, indépendamment du modèle Eloquent utilisé. Chaque modèle concret fournit uniquement :

- ses règles de validation métier via `generate()` (au moment de l'inscription),
- son format d'exposition via `nemesisFormat()` (utilisé par les Actions HTTP),
- sa classe `getMailAuthService()` qui retourne une instance du service configurée pour ce modèle.

Le service expose des hooks `before*` / `after*` à surcharger dans une sous-classe applicative pour injecter de la logique métier sans modifier le cœur du package.

## Installation

Le service est résolu automatiquement via la façade statique `for()` :

```php
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;

$service = MailAuthenticationService::for(App\Models\User::class);
```

## API / Méthodes publiques

### `for(string $modelClass): static`

Fabrique une instance du service pour un modèle donné. Résout toutes les dépendances depuis le container Laravel.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelClass` | `class-string<T>` | FQCN du modèle Eloquent, doit implémenter `MailAuthenticatable` |

**Retourne :** `static<T>` - Instance du service configurée pour ce modèle

**Exceptions :** `InvalidArgumentException` si le modèle n'existe pas ou n'implémente pas `MailAuthenticatable`

**Exemple :**
```php
$service = MailAuthenticationService::for(\App\Models\User::class);
```

### `register(AbstractRecord $record): array`

Crée un nouveau compte utilisateur et, si demandé, émet un token Nemesis accompagné d'un cookie optionnel.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `EmailRegisterAuthRecord` | Données d'inscription (nom, email, mot de passe, `with_token`, `remember_me`) |

**Retourne :** `array{user: Model&Authenticatable, token: NemesisToken|null, plain_token: string|null}`

**Exceptions :** `InvalidArgumentException` si le record n'est pas un `EmailRegisterAuthRecord` ; `ValidationException` si la validation échoue

**Exemple :**
```php
$result = $service->register(new EmailRegisterAuthRecord(
    model_type: User::class,
    data: new StrictDataObject([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]),
    with_token: true,
    remember_me: true,
));

echo $result['plain_token'];
```

### `login(string $email, string $password, bool $rememberMe = false): ?LoginResultRecord`

Authentifie un utilisateur et émet un token. Le cookie est posé si la configuration l'autorise.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur (normalisé en minuscules) |
| `$password` | `string` | Mot de passe en clair |
| `$rememberMe` | `bool` | `true` pour un cookie persistant, `false` pour un cookie de session |

**Retourne :** `LoginResultRecord|null` - `null` si l'email est inconnu ou le mot de passe invalide

**Exemple :**
```php
$result = $service->login('john@example.com', 'Password123!', rememberMe: true);

if ($result !== null) {
    echo $result->plain_token;
}
```

### `logout(Authenticatable&Model $authenticatable, string $plainToken): bool`

Révoque un token et supprime le cookie associé.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Model&Authenticatable` | Utilisateur propriétaire du token |
| `$plainToken` | `string` | Token en clair à révoquer |

**Retourne :** `bool` - `true` en cas de succès, `false` si le token est introuvable ou la révocation a échoué

**Exemple :**
```php
$service->logout($user, $loginResult->plain_token);
```

### `restoreCookie(RestoreCookieAuthRecord $record): ?Model`

Restaure un cookie d'authentification à partir d'un token en clair encore détenu par le client. Utile quand le navigateur a supprimé le cookie mais que le client conserve toujours son token (localStorage, stockage natif).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `RestoreCookieAuthRecord` | FQCN du modèle, token en clair, `remember_me` optionnel |

**Retourne :** `Model&Authenticatable|null` - Le modèle authentifié si la restauration a réussi, `null` sinon

**Cas d'échec couverts :**
- cookie désactivé dans la configuration,
- token invalide,
- token révoqué,
- token expiré,
- `tokenable_type` incohérent avec le modèle demandé,
- utilisateur introuvable.

**Exemple :**
```php
$user = $service->restoreCookie(new RestoreCookieAuthRecord(
    model_type: User::class,
    token: $plainToken,
    remember_me: true,
));

if ($user !== null) {
    // Le cookie a été réémis, l'utilisateur est de nouveau authentifié
}
```

### `sendPasswordResetOtp(string $email): bool`

Génère un OTP de réinitialisation et l'envoie par email.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |

**Retourne :** `bool` - `true` si l'OTP a été envoyé, `false` sinon

**Exemple :**
```php
$service->sendPasswordResetOtp('john@example.com');
```

### `resetPassword(string $email, string $code, string $password): bool`

Vérifie un OTP et remplace le mot de passe.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |
| `$code` | `string` | Code OTP |
| `$password` | `string` | Nouveau mot de passe |

**Retourne :** `bool` - `true` si le mot de passe a été modifié

**Exemple :**
```php
$service->resetPassword('john@example.com', '123456', 'NewPassword123!');
```

### `sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool`

Envoie un OTP de vérification d'email. Retourne `true` immédiatement si l'email est déjà vérifié.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Model&Authenticatable` | Utilisateur cible |

**Retourne :** `bool` - `true` si l'OTP a été envoyé ou si l'email est déjà vérifié, `false` si le rate limit est atteint

**Exemple :**
```php
$service->sendEmailVerificationOtp($user);
```

### `verifyEmail(string $email, string $code): bool`

Vérifie un OTP et marque l'email comme vérifié (`email_verified_at`).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `string` | Email de l'utilisateur |
| `$code` | `string` | Code OTP |

**Retourne :** `bool` - `true` si l'email est vérifié

**Exemple :**
```php
$service->verifyEmail('john@example.com', '123456');
```

### `resendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool`

Alias de `sendEmailVerificationOtp()` pour la sémantique HTTP `resend`.

**Retourne :** `bool` - Même sémantique que `sendEmailVerificationOtp()`

### `isEmailVerified(Authenticatable&Model $authenticatable): bool`

Indique si l'email est vérifié.

**Retourne :** `bool` - `true` si `email_verified_at` est non nul

### `userExists(string|Transformable $email): bool`

Indique si un utilisateur existe pour cet email.

**Retourne :** `bool` - `true` si l'utilisateur existe

### `sendEmailUpdateOtp(Authenticatable&Model $authenticatable, string $newEmail): bool`

Envoie un OTP sur la nouvelle adresse pour valider le changement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Model&Authenticatable` | Utilisateur courant |
| `$newEmail` | `string` | Nouvelle adresse email |

**Retourne :** `bool` - `true` si l'OTP a été envoyé

**Exceptions :** Aucune — retourne `false` si l'email est déjà pris ou si le rate limit est atteint

### `updateEmail(Authenticatable&Model $authenticatable, string $newEmail, string $code): bool`

Vérifie un OTP et applique le changement d'email.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Model&Authenticatable` | Utilisateur courant |
| `$newEmail` | `string` | Nouvelle adresse email |
| `$code` | `string` | Code OTP |

**Retourne :** `bool` - `true` si l'email a été modifié

Le champ `email_verified_at` est remis à `null` après mise à jour.

### `sendTwoFactorOtp(Authenticatable&Model $authenticatable, string $purpose): bool`

Envoie un OTP de 2FA pour une action sensible. Le `$purpose` sert à isoler les OTP par contexte (`change_email`, `change_password`, `delete_account`, ...).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$authenticatable` | `Model&Authenticatable` | Utilisateur cible |
| `$purpose` | `string` | Contexte d'action sensible |

**Retourne :** `bool` - `true` si l'OTP a été envoyé

### `verifyTwoFactorOtp(Authenticatable&Model $authenticatable, string $purpose, string $code): bool`

Vérifie un OTP de 2FA.

**Retourne :** `bool` - `true` si l'OTP est valide

### `getPasswordValidationRules(): array`

Règles de validation partagées pour un mot de passe d'inscription.

**Retourne :** `array<string, array<int, mixed>>` - `['password' => ['required', 'string', 'min:8', 'confirmed']]`

## Cas d'utilisation

### Cas 1 : Inscription avec token et cookie persistant

Un client mobile s'inscrit via une API headless et reçoit un token, stocké en cookie persistant côté navigateur.

```php
$record = new EmailRegisterAuthRecord(
    model_type: User::class,
    data: new StrictDataObject([
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]),
    with_token: true,
    remember_me: true,
);

$result = $service->register($record);

return response()->json([
    'user' => $result['user']->nemesisFormat(),
    'token' => $result['plain_token'],
]);
```

### Cas 2 : Connexion standard avec cookie de session

Un utilisateur se connecte depuis un navigateur qui ferme le cookie à la fermeture de l'onglet.

```php
$result = $service->login('jane@example.com', 'Password123!');

if ($result === null) {
    throw new \RuntimeException('Identifiants invalides');
}

return response()->json(['token' => $result->plain_token]);
```

### Cas 3 : Restauration de cookie après suppression navigateur

Le client conserve le plain token en localStorage. Après suppression du cookie par le navigateur, il appelle une route publique qui restaure le cookie.

```php
$user = $service->restoreCookie(new RestoreCookieAuthRecord(
    model_type: User::class,
    token: $client->plainToken(),
    remember_me: true,
));

if ($user === null) {
    return response()->json(['error' => 'Token expiré ou invalide'], 401);
}

return response()->json([
    'message' => 'Cookie restauré',
    'auth' => $user->nemesisFormat(),
]);
```

### Cas 4 : Surcharger les hooks pour ajouter un profil et un rôle

Une application métier veut créer un profil utilisateur et attribuer un rôle par défaut après l'inscription.

```php
final class AppMailAuthenticationService extends MailAuthenticationService
{
    protected function afterRegister(Model&Authenticatable $user, AbstractRecord $record): void
    {
        $user->profile()->create(['bio' => $record->data->get('bio')]);
        $user->assignRole('user');
    }

    protected function afterLogin(Model&Authenticatable $user): void
    {
        $user->last_login_at = now();
        $user->save();
    }
}
```

## Flux d'exécution

### Flux d'inscription

```
register(record)
  → beforeRegister(record)
  → Validator::make(data, rules)
      ├── si invalide → logRegistrationFailure + ValidationException
      └── si valide
          → Model::generate(data)
          → si with_token
              → nemesis->createWithPlainToken(...)
              → si shouldStoreTokenInCookie → cookieStorage->store(...)
          → logRegistrationSuccess
          → afterRegister(user, record)
          → return ['user', 'token', 'plain_token']
```

### Flux de connexion

```
login(email, password, remember_me)
  → beforeLogin(email, password)
  → Model::where('email', email)->first()
      ├── absent → loginFailure + return null
      └── présent
          → Hash::check(password, hash)
              ├── invalide → loginFailure + return null
              └── valide
                  → logSuccess
                  → nemesis->createWithPlainToken(...)
                  → si shouldStoreTokenInCookie → cookieStorage->store(...)
                  → afterLogin(user)
                  → return LoginResultRecord
```

### Flux de restauration de cookie

```
restoreCookie(record)
  → beforeRestoreCookie(record)
  → si !shouldStoreTokenInCookie → afterRestoreCookie(record, null) → return null
  → hash = hash(nemesisConfig.hashAlgorithm, record.token)
  → token = nemesis->findByHash(hash)
      ├── null → return null
      └── trouvé
          → vérifier tokenable_type === modelClass
          → vérifier !trashed()
          → vérifier expires_at non dépassé
          → auth = Model::find(token.tokenable_id)
              ├── null → return null
              └── trouvé
                  → cookieStorage->store(record.token, remember_me ? duration : 0)
                  → afterRestoreCookie(record, auth)
                  → return auth
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Modèle inexistant | `InvalidArgumentException` | `Model class X does not exist` |
| Modèle n'implémentant pas `MailAuthenticatable` | `InvalidArgumentException` | `Model X must implement MailAuthenticatable` |
| Record invalide (register) | `InvalidArgumentException` | `Invalid record type` |
| Email déjà pris (register) | `ValidationException` | `The email has already been taken.` |
| Mot de passe non confirmé | `ValidationException` | `The password confirmation does not match.` |
| Mot de passe trop court | `ValidationException` | `The password must be at least 8 characters.` |

Les erreurs de connexion (`INVALID_CREDENTIALS`, `USER_NOT_FOUND`) ne lèvent **pas** d'exception : elles sont retournées via `null` et journalisées. Le choix de traduire ou non `null` en exception HTTP est laissé aux Actions.

## Intégration

### Avec les Actions HTTP

`MailAuthenticationService` est appelé par les Actions du package (`EmailRegisterAction`, `EmailLoginAction`, `EmailLogoutAction`, `RestoreCookieAction`, etc.). Les Actions :

1. valident le payload via un `AbstractRequest`,
2. construisent un `Record`,
3. appellent le service,
4. convertissent le retour en réponse HTTP (JSON ou redirection).

### Avec Nemesis

Le service délègue toute la gestion des tokens à `NemesisInterface` :
- `createWithPlainToken(record, user)` pour la création,
- `getTokenByPlainText(plain, user)` pour la révocation,
- `findByHash(hash)` pour la restauration de cookie,
- `revoke(token)` pour la déconnexion.

Le hash est calculé avec l'algorithme configuré dans `NemesisConfigInterface::tokenConfig()->hash_algorithm`.

### Avec OtpService

Chaque flux OTP (vérification email, reset, update email, 2FA) utilise `OtpService` :
- `create(identifier, purpose)` pour générer,
- `verify(identifier, code, purpose)` pour valider,
- `isRateLimited(identifier, purpose, attempts, window)` pour limiter.

Les `PurposeVO` sont construits à l'intérieur du service (durée et tentatives fixées) sauf pour la 2FA, où le contexte est préfixé par `two_factor_`.

### Avec CookieTokenStorage

L'écriture du cookie est conditionnée par `AuthenticationKitConfigInterface::shouldStoreTokenInCookie()`. La durée dépend de `remember_me` :
- `true` → `getCookieDuration()`,
- `false` → `0` (cookie de session).

## Performance

- **Requêtes SQL par opération** : 1 à 3 selon le flux (lookup user + create token + log).
- **Hash** : `hash('sha256', $plain)` est en O(1), négligeable.
- **Validation** : déléguée à `Validator::make()`, coût constant.
- **OTP** : une écriture DB par génération, une lecture + update par vérification.
- **Cookie** : mis en file dans le `CookieJar`, sérialisé une seule fois par réponse.
- **Hooks** : par défaut vides → coût nul ; à surcharger avec parcimonie.
- **Pas de cache interne** : toute mise en cache est à la charge de la sous-classe ou du service appelant.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.5 | ✅ Complet |
| PHP 8.4 | ✅ Complet |
| PHP 8.3 | ✅ Complet |
| PHP 8.2 | ✅ Complet |
| PHP 8.1 | ⚠️ Les types `A&B` en signature de paramètre ne sont pas supportés ; `?Model` est utilisé à la place |
| PHP 8.0 | ❌ Non supporté (enum et readonly manquants) |

Laravel : 10.x, 11.x, 12.x.

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Records\EmailRegisterAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Records\RestoreCookieAuthRecord;
use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$service = MailAuthenticationService::for(App\Models\User::class);

// 1. Inscription avec token et cookie persistant
$registration = $service->register(new EmailRegisterAuthRecord(
    model_type: App\Models\User::class,
    data: new StrictDataObject([
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
    ]),
    with_token: true,
    remember_me: true,
));

$user = $registration['user'];
$plainToken = $registration['plain_token'];

// 2. Connexion ultérieure
$login = $service->login('john@example.com', 'Password123!', rememberMe: true);

// 3. Restauration de cookie à partir du plain token
$restored = $service->restoreCookie(new RestoreCookieAuthRecord(
    model_type: App\Models\User::class,
    token: $plainToken,
    remember_me: true,
));

// 4. Déconnexion
if ($login !== null) {
    $service->logout($user, $login->plain_token);
}
```

## Voir aussi

- `MailAuthenticationInterface` - Contrat implémenté par le service
- `EmailRegisterAuthRecord` - Record d'entrée pour `register()`
- `RestoreCookieAuthRecord` - Record d'entrée pour `restoreCookie()`
- `LoginResultRecord` - Retour de `login()`
- `NemesisInterface` - Gestion des tokens sous-jacente
- `OtpService` - Génération et vérification des OTP
- `CookieTokenStorageInterface` - Stockage du cookie
```