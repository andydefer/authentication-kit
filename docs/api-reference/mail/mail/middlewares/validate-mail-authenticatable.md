# ValidateMailAuthenticatable - Référence Technique

## Description

Middleware qui valide le champ `model_type` dans les requêtes entrantes. Vérifie que le modèle fourni existe et implémente l'interface `MailAuthenticatable` avant de laisser la requête continuer.

## Position dans le pipeline

```
Requête entrante
    ↓
ValidateMailAuthenticatable
    ├── Vérifie model_type présent
    ├── Vérifie model_type existe
    ├── Vérifie MailAuthenticatable
    └── Succès → Continue
    ↓
Contrôleur / Action
```

## Enregistrement du middleware

### Dans le ServiceProvider

```php
// MailServiceProvider.php
$this->app->make(Router::class)->aliasMiddleware(
    name: 'validate.mail.authenticatable',
    class: ValidateMailAuthenticatable::class
);
```

### Utilisation dans les routes

```php
Route::middleware(['validate.mail.authenticatable'])->group(function (): void {
    Route::post('/register', action_route(
        EmailRegisterRequest::class,
        EmailRegisterAction::class
    ))->name('register');

    Route::post('/login', action_route(
        EmailLoginRequest::class,
        EmailLoginAction::class
    ))->name('login');
});
```

---

## Structure de la requête

### Body (JSON)

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle (ex: `App\\Models\\User`) |

### Exemple de requête valide

```json
{
    "model_type": "App\\Models\\User",
    "email": "john@example.com",
    "password": "Password123!"
}
```

---

## Structure de la réponse

### Erreur - model_type manquant (400 Bad Request)

```json
{
    "message": "model_type is required",
    "status": 400,
    "errorCode": "MODEL_TYPE_REQUIRED"
}
```

### Erreur - Modèle inexistant (500 Internal Server Error)

```json
{
    "message": "Model NonExistentClass does not exist",
    "status": 500,
    "errorCode": "MODEL_NOT_FOUND"
}
```

### Erreur - Modèle non compatible (500 Internal Server Error)

```json
{
    "message": "Model App\\Models\\User must implement AndyDefer\\AuthenticationKit\\Mail\\Contracts\\MailAuthenticatable",
    "status": 500,
    "errorCode": "INVALID_MODEL"
}
```

---

## Codes d'erreur

| Code | Description |
|------|-------------|
| `MODEL_TYPE_REQUIRED` | Le champ `model_type` est manquant dans la requête |
| `MODEL_NOT_FOUND` | La classe du modèle spécifiée n'existe pas |
| `INVALID_MODEL` | Le modèle n'implémente pas `MailAuthenticatable` |

---

## Exemples d'utilisation

### Exemple 1 : Avec un modèle valide

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Http;

$response = Http::post('http://localhost/api/login', [
    'model_type' => User::class,
    'email' => 'john@example.com',
    'password' => 'Password123!',
]);

// Le middleware valide que User implémente MailAuthenticatable
// La requête continue vers EmailLoginAction
```

### Exemple 2 : model_type manquant

```php
<?php

use Illuminate\Support\Facades\Http;

$response = Http::post('http://localhost/api/login', [
    'email' => 'john@example.com',
    'password' => 'Password123!',
]);

// Réponse 400
// {
//     "message": "model_type is required",
//     "status": 400,
//     "errorCode": "MODEL_TYPE_REQUIRED"
// }
```

### Exemple 3 : Modèle inexistant

```php
<?php

use Illuminate\Support\Facades\Http;

$response = Http::post('http://localhost/api/login', [
    'model_type' => 'NonExistentClass',
    'email' => 'john@example.com',
    'password' => 'Password123!',
]);

// Réponse 500
// {
//     "message": "Model NonExistentClass does not exist",
//     "status": 500,
//     "errorCode": "MODEL_NOT_FOUND"
// }
```

### Exemple 4 : Modèle ne correspondant pas dans un test Laravel

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ValidateMailAuthenticatableTest extends TestCase
{
    public function test_middleware_requires_model_type(): void
    {
        $response = $this->postJson('/api/login', [
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'model_type is required',
                'status' => 400,
                'errorCode' => 'MODEL_TYPE_REQUIRED',
            ]);
    }

    public function test_middleware_requires_valid_model(): void
    {
        $response = $this->postJson('/api/login', [
            'model_type' => 'NonExistentClass',
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(500)
            ->assertJson([
                'message' => 'Model NonExistentClass does not exist',
                'status' => 500,
                'errorCode' => 'MODEL_NOT_FOUND',
            ]);
    }

    public function test_middleware_passes_with_valid_model(): void
    {
        $response = $this->postJson('/api/login', [
            'model_type' => User::class,
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ]);

        // Le middleware passe, la réponse dépend de l'action suivante
        $response->assertStatus(401); // Identifiants invalides
    }
}
```

---

## Flux d'exécution

```
Requête entrante
    ↓
Récupération de 'model_type' de la requête
    ↓
model_type === null ?
    ├── Oui → 400 (MODEL_TYPE_REQUIRED)
    └── Non → Continue
    ↓
class_exists(model_type) ?
    ├── Non → 500 (MODEL_NOT_FOUND)
    └── Oui → Continue
    ↓
model_type implémente MailAuthenticatable ?
    ├── Non → 500 (INVALID_MODEL)
    └── Oui → Continue
    ↓
$next($request) → Passe à l'étape suivante
```

---

## Gestion des erreurs

| Situation | Code | Message |
|-----------|------|---------|
| `model_type` manquant | 400 | `model_type is required` |
| `model_type` n'existe pas | 500 | `Model X does not exist` |
| `model_type` n'implémente pas MailAuthenticatable | 500 | `Model X must implement MailAuthenticatable` |

---

## Intégration

### Dépendances

| Dépendance | Rôle |
|------------|------|
| `MailAuthenticatable` | Interface que le modèle doit implémenter |
| `ErrorResponseData` | Structure de réponse d'erreur |

### Middlewares associés

| Middleware | Rôle |
|------------|------|
| `validate.mail.authenticatable` | Valide le modèle (ce middleware) |
| `nemesis.token` | Valide le token d'authentification |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 9.x | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |

---

## Voir aussi

- `MailAuthenticatable` - Interface du modèle
- `ErrorResponseData` - Structure de réponse d'erreur
- `EmailLoginAction` - Action de connexion
- `EmailRegisterAction` - Action d'inscription