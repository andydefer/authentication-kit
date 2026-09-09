```markdown
# AuthenticationResolver - Référence Technique

## Description

Classe utilitaire statique pour résoudre les services d'authentification et les modèles authentifiables à partir d'un type de modèle et d'un identifiant.

## Rôle principal

Facilite la résolution du service d'authentification mail (`MailAuthenticationService`) et du modèle authentifiable (`Model` implémentant `MailAuthenticatable`) à partir d'un type de modèle et d'un identifiant (ID ou email).

## Hiérarchie / Implémentations

```
- Aucune hiérarchie (classe finale statique)
- Utilise : MailAuthenticatable, Model, SoftDeletes
- Dépend de : MailAuthenticationService
```

## API / Méthodes publiques

---

### `resolveService(string $modelType): ?MailAuthenticationService`

Résout le service d'authentification mail pour un type de modèle donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelType` | `string` | Le nom complet (FQCN) de la classe du modèle |

**Retourne :** `MailAuthenticationService|null` - Le service d'authentification, ou `null` si le modèle est invalide

**Exemple :**
```php
$service = AuthenticationResolver::resolveService(User::class);
// $service est une instance de MailAuthenticationService
```

---

### `resolveAuthenticatable(string $modelType, int|string $id, bool $includeTrashed = false): ?Model`

Résout le modèle authentifiable pour un type de modèle et un ID donnés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelType` | `string` | Le nom complet (FQCN) de la classe du modèle |
| `$id` | `int|string` | L'ID du modèle |
| `$includeTrashed` | `bool` | Inclure les enregistrements soft-deleted (défaut: false) |

**Retourne :** `Model|null` - Le modèle authentifiable, ou `null` si non trouvé ou invalide

**Exemple :**
```php
$user = AuthenticationResolver::resolveAuthenticatable(User::class, 1);
// $user est une instance de User
```

---

### `resolveAuthenticatableByEmail(string $modelType, string $email, bool $includeTrashed = false): ?Model`

Résout le modèle authentifiable par adresse email.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelType` | `string` | Le nom complet (FQCN) de la classe du modèle |
| `$email` | `string` | L'adresse email à rechercher |
| `$includeTrashed` | `bool` | Inclure les enregistrements soft-deleted (défaut: false) |

**Retourne :** `Model|null` - Le modèle authentifiable, ou `null` si non trouvé ou invalide

**Exemple :**
```php
$user = AuthenticationResolver::resolveAuthenticatableByEmail(
    User::class, 
    'john@example.com'
);
// $user est une instance de User
```

---

### `isValidAuthenticatable(string $modelType): bool`

Vérifie si un type de modèle est un `MailAuthenticatable` valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelType` | `string` | Le nom complet (FQCN) de la classe du modèle |

**Retourne :** `bool` - `true` si le modèle est valide, `false` sinon

**Exemple :**
```php
$isValid = AuthenticationResolver::isValidAuthenticatable(User::class);
// $isValid = true
```

---

### `usesSoftDeletes(string $modelType): bool`

Vérifie si un type de modèle utilise le trait `SoftDeletes`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelType` | `string` | Le nom complet (FQCN) de la classe du modèle |

**Retourne :** `bool` - `true` si le modèle utilise `SoftDeletes`, `false` sinon

**Exemple :**
```php
$usesSoftDeletes = AuthenticationResolver::usesSoftDeletes(User::class);
// $usesSoftDeletes = true (si User utilise SoftDeletes)
```

---

### `resolve(string $modelType, int|string $id, bool $includeTrashed = false): array`

Résout à la fois le service et le modèle authentifiable.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelType` | `string` | Le nom complet (FQCN) de la classe du modèle |
| `$id` | `int|string` | L'ID du modèle |
| `$includeTrashed` | `bool` | Inclure les enregistrements soft-deleted (défaut: false) |

**Retourne :** `array{service: MailAuthenticationService|null, authenticatable: Model|null}` - Tableau associatif contenant le service et le modèle

**Exemple :**
```php
$result = AuthenticationResolver::resolve(User::class, 1);
// $result = [
//     'service' => MailAuthenticationService,
//     'authenticatable' => User
// ]
```

---

### `resolveByEmail(string $modelType, string $email, bool $includeTrashed = false): array`

Résout à la fois le service et le modèle authentifiable par email.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$modelType` | `string` | Le nom complet (FQCN) de la classe du modèle |
| `$email` | `string` | L'adresse email à rechercher |
| `$includeTrashed` | `bool` | Inclure les enregistrements soft-deleted (défaut: false) |

**Retourne :** `array{service: MailAuthenticationService|null, authenticatable: Model|null}` - Tableau associatif contenant le service et le modèle

**Exemple :**
```php
$result = AuthenticationResolver::resolveByEmail(
    User::class, 
    'john@example.com'
);
// $result = [
//     'service' => MailAuthenticationService,
//     'authenticatable' => User
// ]
```

---

## Cas d'utilisation

### Cas 1 : Résolution du service pour une action d'authentification

**Problème :** Dans une action, on reçoit un `model_type` et on doit résoudre le service d'authentification correspondant.

```php
// Dans une Action
protected function before(AbstractRecord $record): void
{
    $this->modelType = $record->model_type;
    $this->authService = AuthenticationResolver::resolveService($this->modelType);
    
    if ($this->authService === null) {
        throw new \InvalidArgumentException('Invalid model type');
    }
}
```

### Cas 2 : Résolution d'un utilisateur par email avec gestion des soft-deletes

**Problème :** On doit vérifier si un email existe, même si l'utilisateur est soft-deleted.

```php
// Vérifier si un utilisateur existe (incluant les soft-deleted)
$authenticatable = AuthenticationResolver::resolveAuthenticatableByEmail(
    User::class,
    'john@example.com',
    true // includeTrashed
);

if ($authenticatable !== null && $authenticatable->trashed()) {
    // L'utilisateur existe mais est supprimé
    // Gérer ce cas spécifique
}
```

### Cas 3 : Résolution complète pour une action de vérification d'email

**Problème :** Dans une action de vérification d'email, on a besoin du service et du modèle.

```php
// Dans VerifyEmailAction
protected function before(AbstractRecord $record): void
{
    $this->modelType = $record->model_type;
    $this->email = trim($record->email);
    
    $includeTrashed = AuthenticationResolver::usesSoftDeletes($this->modelType);
    $result = AuthenticationResolver::resolveByEmail(
        $this->modelType,
        $this->email,
        $includeTrashed
    );
    
    $this->authService = $result['service'];
    $this->authenticatable = $result['authenticatable'];
}

protected function handle(AbstractRecord $record): ResponseFactory
{
    if ($this->authenticatable === null) {
        return ResponseFactory::json(
            new ErrorResponseData(
                message: 'User not found',
                status: 404,
                errorCode: 'USER_NOT_FOUND'
            ),
            404
        );
    }
    // ... suite du traitement
}
```

---

## Flux d'exécution

### Flux de `resolveService()`

```
modelType → isValidAuthenticatable()
    ├── false → return null
    └── true → modelClass::getMailAuthService() → return service
```

### Flux de `resolveAuthenticatable()`

```
modelType + id → isValidAuthenticatable()
    ├── false → return null
    └── true → modelClass::query()
                ├── withTrashed() si includeTrashed = true
                └── find(id)
                    ├── null → return null
                    ├── trashed() et usesSoftDeletes() → return null
                    └── return model
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Classe non trouvée | `null` retourné | N/A (retourne null) |
| Classe n'implémente pas `MailAuthenticatable` | `null` retourné | N/A (retourne null) |
| ID non trouvé | `null` retourné | N/A (retourne null) |
| Modèle soft-deleted | `null` retourné | N/A (retourne null) |

**Note :** La classe ne lève pas d'exceptions. Elle retourne `null` en cas d'échec, permettant une gestion élégante dans le code appelant.

---

## Intégration

### Dépendances

| Dépendance | Utilisation |
|-----------|-------------|
| `MailAuthenticatable` | Vérification de l'implémentation de l'interface |
| `MailAuthenticationService` | Résolution du service |
| `Illuminate\Database\Eloquent\Model` | Résolution des modèles |
| `Illuminate\Database\Eloquent\SoftDeletes` | Détection des soft-deletes |
| `class_implements()` | Vérification des interfaces |
| `class_uses()` | Vérification des traits |

### Points d'extension

La classe est `final` et ne peut pas être étendue. Toutes les méthodes sont `static`. Elle est conçue comme un utilitaire pur.

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `resolveService()` | O(1) | Vérification de classe + appel static |
| `resolveAuthenticatable()` | O(1) + requête DB | Une seule requête `find()` |
| `resolveAuthenticatableByEmail()` | O(1) + requête DB | Une seule requête `where()->first()` |
| `isValidAuthenticatable()` | O(1) | Vérification de classe et interface |
| `usesSoftDeletes()` | O(1) | Vérification de trait |

**Optimisation :** Aucun cache n'est utilisé. Pour de nombreuses résolutions, envisager de mettre en cache les résultats.

---

## Compatibilité

| Version PHP | Support | Notes |
|-------------|---------|-------|
| PHP 8.1+ | ✅ Complet | Support des types union (`int|string`) |
| PHP 8.0+ | ✅ Complet | Support des types union (`int|string`) |
| PHP 7.4 | ❌ | Typage `int|string` non supporté |

| Version Laravel | Support |
|-----------------|---------|
| Laravel 10+ | ✅ |
| Laravel 9+ | ✅ |
| Laravel 8+ | ✅ |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Utils\AuthenticationResolver;
use App\Models\User;

// 1. Vérifier si un modèle est valide
$isValid = AuthenticationResolver::isValidAuthenticatable(User::class);
// true

// 2. Résoudre le service
$service = AuthenticationResolver::resolveService(User::class);
// MailAuthenticationService

// 3. Résoudre un utilisateur par ID
$user = AuthenticationResolver::resolveAuthenticatable(User::class, 1);
// User instance

// 4. Résoudre un utilisateur par email
$user = AuthenticationResolver::resolveAuthenticatableByEmail(
    User::class, 
    'john@example.com'
);
// User instance

// 5. Résoudre tout en une seule fois
$result = AuthenticationResolver::resolve(User::class, 1);
// [
//     'service' => MailAuthenticationService,
//     'authenticatable' => User
// ]

// 6. Résoudre par email avec soft-deletes
$result = AuthenticationResolver::resolveByEmail(
    User::class,
    'john@example.com',
    true // includeTrashed
);
// [
//     'service' => MailAuthenticationService,
//     'authenticatable' => User (même si soft-deleted)
// ]

// 7. Vérifier si un modèle utilise SoftDeletes
$usesSoftDeletes = AuthenticationResolver::usesSoftDeletes(User::class);
// true
```

---

## Voir aussi

- `MailAuthenticationService` - Service principal d'authentification
- `MailAuthenticatable` - Interface pour les modèles authentifiables
- `ResendEmailVerificationAction` - Action qui utilise le resolver
- `ResetPasswordAction` - Action qui utilise le resolver
- `SendEmailVerificationAction` - Action qui utilise le resolver
- `VerifyEmailAction` - Action qui utilise le resolver
```