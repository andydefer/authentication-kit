# EmailRegisterAction - Référence Technique

## Description

Action d'inscription par email qui crée un utilisateur et génère optionnellement un token d'authentification Nemesis.

## Hiérarchie

```
AbstractAction
    └── EmailRegisterAction
```

## Rôle principal

Orchestre le flux d'inscription d'un utilisateur en validant les données, créant le modèle via la factory du modèle, et retournant une réponse avec ou sans token Nemesis.

## Installation

```bash
composer require andydefer/authentication-kit
```

Le service provider enregistre automatiquement la route `/register`.

## API / Méthodes publiques

### `__construct(NemesisService $nemesis): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$nemesis` | `NemesisService` | Service de gestion des tokens Nemesis |

---

### `before(AbstractRecord $record): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Record de la requête, doit être `EmailRegisterUserRecord` |

**Retourne :** `void`

**Exceptions :**

| Exception | Condition |
|-----------|-----------|
| `InvalidArgumentException` | Le record n'est pas `EmailRegisterUserRecord` |
| `InvalidArgumentException` | La classe modèle n'existe pas |
| `InvalidArgumentException` | La classe modèle n'implémente pas `MailAuthenticatable` |
| `ValidationException` | Les données ne passent pas les règles de validation |

**Exemple :**
```php
// Appelé automatiquement par AbstractAction::run()
// Ne pas appeler directement.
```

---

### `handle(AbstractRecord $record): ResponseFactory`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `AbstractRecord` | Record de la requête, doit être `EmailRegisterUserRecord` |

**Retourne :** `ResponseFactory` - Réponse HTTP 201 avec les données utilisateur et éventuellement un token

**Exceptions :** `InvalidArgumentException` si le record est invalide

**Exemple :**
```php
// Appelé automatiquement par AbstractAction::run()
// Ne pas appeler directement.
```

## Cas d'utilisation

### Cas 1 : Inscription sans token (API publique)

```http
POST /register
Content-Type: application/json

{
    "model_type": "App\\Models\\User",
    "with_token": false,
    "name": "John Doe",
    "email": "john@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
}
```

**Réponse :**
```json
{
    "message": "User registered successfully",
    "user": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "createdAt": "2024-01-01T12:00:00+00:00"
    }
}
```

### Cas 2 : Inscription avec token (connexion immédiate)

```http
POST /register
Content-Type: application/json

{
    "model_type": "App\\Models\\User",
    "with_token": true,
    "name": "Jane Doe",
    "email": "jane@example.com",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
}
```

**Réponse :**
```json
{
    "message": "User registered successfully",
    "user": {
        "id": 2,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "createdAt": "2024-01-01T12:00:00+00:00"
    },
    "token": "a1b2c3d4e5f6g7h8i9j0..."
}
```

### Cas 3 : Inscription d'un docteur

```http
POST /register
Content-Type: application/json

{
    "model_type": "App\\Models\\Doctor",
    "with_token": true,
    "name": "Dr. Sarah Connor",
    "email": "sarah@hospital.com",
    "specialty": "Cardiology",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
}
```

## Flux d'exécution

```
Requête HTTP
    ↓
EmailRegisterRequest (validation)
    ↓
EmailRegisterUserRecord (transformation)
    ↓
EmailRegisterAction::run()
    ↓
EmailRegisterAction::before()
    ├── Validation du record type
    ├── Vérification de l'existence du modèle
    ├── Vérification de l'implémentation MailAuthenticatable
    └── Validation des données via Validator
    ↓
EmailRegisterAction::handle()
    ├── Création de l'utilisateur via MailAuthenticatable::createUser()
    ├── Si with_token = true
    │   └── NemesisService::createWithPlainToken()
    └── Retour ResponseFactory JSON 201
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Record invalide | `InvalidArgumentException` | `Invalid record type` |
| Modèle inexistant | `InvalidArgumentException` | `Model [X] does not exist` |
| Modèle sans interface | `InvalidArgumentException` | `Model [X] must implement MailAuthenticatable` |
| Données invalides | `ValidationException` | Messages des règles de validation du modèle |
| Erreur token | `Exception` (propagée) | Message de `NemesisService::createWithPlainToken()` |

## Intégration

### Dépendances

```
EmailRegisterAction
    ├── NemesisService (création de token)
    ├── MailAuthenticatable (modèle cible)
    ├── Validator (validation Laravel)
    └── ResponseFactory (réponse HTTP)
```

### Route associée

```php
Route::post('/register', action_route(
    EmailRegisterRequest::class,
    EmailRegisterAction::class
));
```

### Modèle requis

Le modèle doit implémenter `MailAuthenticatable` :

```php
interface MailAuthenticatable extends Authenticatable
{
    public static function getMailAuthIdentifier(): MailAuthIdentifierRecord;
    public static function getValidationRules(): array;
    public static function createUser(Validator $validator): Authenticatable;
}
```

## Performance

| Aspect | Impact |
|--------|--------|
| Validation | `O(n)` avec n = nombre de règles (rapide) |
| Création utilisateur | Dépend du modèle (Eloquent standard) |
| Création token | `O(1)` - génération aléatoire + hash |
| Cache | Aucun cache utilisé dans cette Action |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |
| Laravel 11+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

// 1. Définir le modèle
namespace App\Models;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Records\MailAuthIdentifierRecord;
use AndyDefer\AuthenticationKit\Records\AuthIdentifierRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

final class User extends Model implements MailAuthenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    public static function getAuthIdentifier(): AuthIdentifierRecord
    {
        return new AuthIdentifierRecord(
            password_field: 'password',
            remember_token_field: 'remember_token',
        );
    }

    public static function getMailAuthIdentifier(): MailAuthIdentifierRecord
    {
        return new MailAuthIdentifierRecord(
            email_field: 'email',
            email_verified_at_field: 'email_verified_at',
        );
    }

    public static function getValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }

    public static function createUser(Validator $validator): Authenticatable
    {
        $data = $validator->validated();
        return self::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }

    public function nemesisFormat(): AbstractData
    {
        return new UserData(
            id: $this->id,
            name: $this->name,
            email: $this->email,
            createdAt: $this->created_at?->toIso8601String(),
        );
    }

    public function getFillableRecord(): AbstractRecord
    {
        return new UserRecord(
            name: $this->name,
            email: $this->email,
            password: $this->password,
        );
    }
}

// 2. Utiliser la route
// POST /register
// {
//     "model_type": "App\\Models\\User",
//     "with_token": true,
//     "name": "John Doe",
//     "email": "john@example.com",
//     "password": "SecurePass123!",
//     "password_confirmation": "SecurePass123!"
// }
```

## Voir aussi

- `AbstractAction` - Classe de base des Actions
- `MailAuthenticatable` - Contrat pour les modèles authentifiables par email
- `NemesisService` - Service de gestion des tokens
- `EmailRegisterRequest` - Requête associée