# 📘 Guide d'Utilisation - AuthenticationKit

## Introduction

AuthenticationKit est un kit d'authentification headless pour Laravel qui permet d'inscrire et d'authentifier **n'importe quel modèle Eloquent** via une API unique. Il est construit sur **Nemesis** (gestion des tokens) et **Laravel Actions** (architecture orientée actions).

---

## 📦 Installation

```bash
composer require andydefer/authentication-kit
```

### Service Provider

Le package s'enregistre automatiquement via Laravel. Assurez-vous que les providers sont chargés :

```php
// config/app.php
'providers' => [
    // ...
    AndyDefer\AuthenticationKit\AuthenticationKitServiceProvider::class,
];
```

---

## 🚀 Configuration

### Publication du fichier de configuration

```bash
php artisan vendor:publish --tag=authentication-kit-config
```

### Variables d'environnement

```env
AUTH_KIT_TOKEN_NAME=my-app-token
```

### Fichier de configuration

```php
// config/authentication-kit.php
return [
    'token_name' => env('AUTH_KIT_TOKEN_NAME', 'authentication-kit'),
];
```

---

## 🎯 Prérequis : Votre Modèle

Pour utiliser l'inscription, votre modèle doit implémenter `MailAuthenticatable`.

### Exemple complet avec le modèle User

```php
<?php

namespace App\Models;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Records\MailAuthIdentifierRecord;
use AndyDefer\AuthenticationKit\Records\AuthIdentifierRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

class User extends Model implements MailAuthenticatable
{
    protected $fillable = ['name', 'email', 'password'];

    // 1. Définir les champs d'authentification
    public static function getAuthIdentifier(): AuthIdentifierRecord
    {
        return new AuthIdentifierRecord(
            password_field: 'password',
            remember_token_field: 'remember_token',
        );
    }

    // 2. Définir les champs email
    public static function getMailAuthIdentifier(): MailAuthIdentifierRecord
    {
        return new MailAuthIdentifierRecord(
            email_field: 'email',
            email_verified_at_field: 'email_verified_at',
        );
    }

    // 3. Définir les règles de validation
    public static function getValidationRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }

    // 4. Créer l'utilisateur
    public static function createUser(Validator $validator): Authenticatable
    {
        $data = $validator->validated();

        return self::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => bcrypt($data['password']),
        ]);
    }

    // 5. Format de sortie API (Nemesis)
    public function nemesisFormat(): AbstractData
    {
        return new DataObject([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'createdAt' => $this->created_at?->toIso8601String(),
        ]);
    }

    // 6. Record des champs fillable
    public function getFillableRecord(): AbstractRecord
    {
        return new DataObject([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);
    }
}
```

---

## 📝 Inscription

### Endpoint

```
POST /register
```

### Headers

```
Content-Type: application/json
```

### Corps de la requête

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | Namespace complet de votre modèle (ex: `App\Models\User`) |
| `with_token` | `boolean` | ❌ Non | Si `true`, génère un token Nemesis automatiquement |
| `...` | `mixed` | ❌ Non | Tous les autres champs sont passés au modèle selon ses règles |

### Exemples

#### 1. Inscription sans token

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

**Réponse (201 Created) :**

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

#### 2. Inscription avec token

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

**Réponse (201 Created) :**

```json
{
    "message": "User registered successfully",
    "user": {
        "id": 2,
        "name": "Jane Doe",
        "email": "jane@example.com",
        "createdAt": "2024-01-01T12:00:00+00:00"
    },
    "token": "a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6..."
}
```

---

## 🏥 Inscription d'un docteur (exemple multi-modèles)

### Modèle Doctor

```php
<?php

namespace App\Models;

use AndyDefer\AuthenticationKit\Mail\Contracts\MailAuthenticatable;
use AndyDefer\AuthenticationKit\Mail\Records\MailAuthIdentifierRecord;
use AndyDefer\AuthenticationKit\Records\AuthIdentifierRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Utils\DataObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Validator;

class Doctor extends Model implements MailAuthenticatable
{
    protected $fillable = ['name', 'email', 'specialty', 'hospital', 'password'];

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
            'email' => ['required', 'email', 'unique:doctors'],
            'specialty' => ['required', 'string', 'max:100'],
            'hospital' => ['required', 'string', 'max:255'],
            'password' => ['required', 'min:8', 'confirmed'],
        ];
    }

    public static function createUser(Validator $validator): Authenticatable
    {
        $data = $validator->validated();

        return self::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'specialty' => $data['specialty'],
            'hospital' => $data['hospital'],
            'password' => bcrypt($data['password']),
        ]);
    }

    public function nemesisFormat(): AbstractData
    {
        return new DataObject([
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'specialty' => $this->specialty,
            'hospital' => $this->hospital,
            'createdAt' => $this->created_at?->toIso8601String(),
        ]);
    }

    public function getFillableRecord(): AbstractRecord
    {
        return new DataObject([
            'name' => $this->name,
            'email' => $this->email,
            'specialty' => $this->specialty,
            'hospital' => $this->hospital,
            'password' => $this->password,
        ]);
    }
}
```

### Requête d'inscription d'un docteur

```http
POST /register
Content-Type: application/json

{
    "model_type": "App\\Models\\Doctor",
    "with_token": true,
    "name": "Dr. Sarah Connor",
    "email": "sarah@hospital.com",
    "specialty": "Cardiology",
    "hospital": "City Hospital",
    "password": "SecurePass123!",
    "password_confirmation": "SecurePass123!"
}
```

**Réponse (201 Created) :**

```json
{
    "message": "User registered successfully",
    "user": {
        "id": 1,
        "name": "Dr. Sarah Connor",
        "email": "sarah@hospital.com",
        "specialty": "Cardiology",
        "hospital": "City Hospital",
        "createdAt": "2024-01-01T12:00:00+00:00"
    },
    "token": "x1y2z3..."
}
```

---

## 📊 Logs d'inscription

Le système journalise automatiquement chaque tentative d'inscription avec des informations contextuelles.

### Structure du log

```json
{
    "time": "2024-01-01T12:00:00Z",
    "level": "info",
    "data": {
        "type": "auth",
        "payload": {
            "event": "user_registration_success",
            "user_id": 1,
            "model_type": "App\\Models\\User",
            "with_token": true,
            "ip": "127.0.0.1",
            "user_agent": "Mozilla/5.0...",
            "platform": "Windows 10",
            "browser": "Chrome",
            "device_type": "desktop",
            "is_mobile": false,
            "is_robot": false
        }
    }
}
```

### Types d'événements

| Événement | Description |
|-----------|-------------|
| `user_registration_success` | Inscription réussie |
| `user_registration_failed` | Inscription échouée |

---

## ❌ Gestion des erreurs

### 1. Modèle inexistant

```http
POST /register
{
    "model_type": "NonExistentClass"
}
```

**Réponse (500) :**

```json
{
    "message": "Model NonExistentClass does not exist"
}
```

### 2. Modèle sans interface MailAuthenticatable

```http
POST /register
{
    "model_type": "stdClass"
}
```

**Réponse (500) :**

```json
{
    "message": "Model stdClass must implement MailAuthenticatable"
}
```

### 3. Erreur de validation

```http
POST /register
{
    "model_type": "App\\Models\\User",
    "email": "invalid"
}
```

**Réponse (422) :**

```json
{
    "message": "The given data was invalid.",
    "errors": {
        "email": ["The email must be a valid email address."]
    }
}
```

---

## 🧪 Tester l'inscription

### Test unitaire avec PHPUnit

```php
<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    public function test_user_can_register_without_token(): void
    {
        $response = $this->postJson('/register', [
            'model_type' => User::class,
            'with_token' => false,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'user' => ['id', 'name', 'email', 'createdAt'],
        ]);
        $response->assertJsonMissing(['token']);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    public function test_user_can_register_with_token(): void
    {
        $response = $this->postJson('/register', [
            'model_type' => User::class,
            'with_token' => true,
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'user',
            'token',
        ]);
    }
}
```

---

## 🔧 Personnalisation avancée

### Changer le nom du token

Dans `.env` :

```env
AUTH_KIT_TOKEN_NAME=my-custom-token-name
```

### Ajouter des métadonnées au token

Le token inclut automatiquement les métadonnées suivantes :

```php
[
    'device_type' => $agent->deviceType(),
    'platform' => $agent->platform(),
    'browser' => $agent->browser(),
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
]
```

### Ajouter vos propres métadonnées

Vous pouvez étendre l'Action en surchargeant la création du token.

---

## 📋 Résumé des méthodes du modèle

| Méthode | Retour | Description |
|---------|--------|-------------|
| `getAuthIdentifier()` | `AuthIdentifierRecord` | Champs password et remember_token |
| `getMailAuthIdentifier()` | `MailAuthIdentifierRecord` | Champs email et email_verified_at |
| `getValidationRules()` | `array` | Règles de validation Laravel |
| `createUser(Validator $validator)` | `Authenticatable` | Création de l'utilisateur |
| `nemesisFormat()` | `AbstractData` | Format de sortie API |
| `getFillableRecord()` | `AbstractRecord` | Record des champs fillable |

---

## ❓ FAQ

### Q : Puis-je inscrire plusieurs types de modèles ?

**R :** Oui ! Utilisez `model_type` pour spécifier le modèle souhaité (User, Doctor, Patient, etc.).

### Q : Comment ajouter des champs personnalisés ?

**R :** Ajoutez-les dans `$fillable`, dans `getValidationRules()`, et dans `createUser()`.

### Q : Le token expire-t-il ?

**R :** Par défaut non. Configurez `nemesis.expiration` dans `config/nemesis.php` pour définir une durée.

### Q : Où sont stockés les logs ?

**R :** Les logs sont stockés dans `storage/logs/structured/` au format JSONL.

### Q : Comment désactiver les logs ?

**R :** Remplacez `LoggerInterface` par un mock ou un logger silencieux dans votre conteneur.

---

## 🔗 Voir aussi

- [Nemesis - Documentation](https://github.com/andydefer/laravel-nemesis)
- [Laravel Actions - Documentation](https://github.com/andydefer/laravel-actions)
- [Laravel Logger - Documentation](https://github.com/andydefer/laravel-logger)