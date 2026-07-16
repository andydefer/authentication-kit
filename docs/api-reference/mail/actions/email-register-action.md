# EmailRegisterAction - Référence Technique

## Description

Action qui gère l'inscription d'un nouvel utilisateur via email. Crée un compte, génère optionnellement un token d'authentification, et journalise la tentative.

## Endpoint

```
POST /register
```

## Définition de la route

```php
Route::post('/register', action_route(
    EmailRegisterRequest::class,
    EmailRegisterAction::class
))->name('register');
```

## Middleware

| Middleware | Rôle |
|------------|------|
| `validate.mail.authenticatable` | Valide que le `model_type` existe et implémente `MailAuthenticatable` |

---

## Structure de la requête

### Headers

| Header | Valeur | Requis |
|--------|--------|--------|
| `Content-Type` | `application/json` | ✅ Oui |
| `Accept` | `application/json` | ✅ Oui |

### Body (JSON)

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle (ex: `App\\Models\\User`) |
| `with_token` | `boolean` | ❌ Non | Générer un token (défaut: `false`) |
| `*` | `mixed` | ❌ Non | Tous les autres champs sont passés au service et au modèle |

**Note importante :** La Request ne valide que `model_type` et `with_token`. Les autres champs (`name`, `email`, `password`, `password_confirmation`, `age`, `sex`, etc.) sont passés directement au service via `data` et sont validés par le service ou le modèle.

### Exemple de requête

```json
{
    "model_type": "App\\Models\\User",
    "with_token": true,
    "name": "John Doe",
    "email": "john@example.com",
    "password": "Password123!",
    "password_confirmation": "Password123!",
    "age": 30,
    "sex": "male",
    "phone": "+1234567890"
}
```

---

## Structure de la réponse

### Succès - Sans token (201 Created)

```json
{
    "message": "Registration successful",
    "auth": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "emailVerifiedAt": null,
        "createdAt": "2024-01-01T10:00:00+00:00",
        "updatedAt": "2024-01-01T10:00:00+00:00"
    }
}
```

### Succès - Avec token (201 Created)

```json
{
    "message": "Registration successful",
    "auth": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "emailVerifiedAt": null,
        "createdAt": "2024-01-01T10:00:00+00:00",
        "updatedAt": "2024-01-01T10:00:00+00:00"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"
}
```

### Erreur - Validation (422 Unprocessable Entity)

```json
{
    "message": "The name field is required. (and 1 more error)",
    "status": 422,
    "errorCode": "VALIDATION_ERROR",
    "errors": {
        "name": [
            "The name field is required."
        ],
        "email": [
            "The email has already been taken."
        ]
    }
}
```

### Erreur - Model invalide (500 Internal Server Error)

```json
{
    "message": "Model NonExistentClass does not exist",
    "status": 500,
    "errorCode": "MODEL_NOT_FOUND"
}
```

---

## Codes d'erreur

| Code | Description |
|------|-------------|
| `VALIDATION_ERROR` | Erreur de validation des données (service ou modèle) |
| `MODEL_NOT_FOUND` | Le modèle spécifié n'existe pas |
| `INVALID_MODEL` | Le modèle n'implémente pas `MailAuthenticatable` |
| `MODEL_TYPE_REQUIRED` | Le champ `model_type` est manquant |
| `REGISTRATION_ERROR` | Erreur générique lors de l'inscription |

---

## Exemples d'appel

### Exemple 1 : Laravel HTTP Client

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

$response = Http::post('http://localhost/api/register', [
    'model_type' => 'App\\Models\\User',
    'with_token' => true,
    'name' => 'John Doe',
    'email' => 'john@example.com',
    'password' => 'Password123!',
    'password_confirmation' => 'Password123!',
]);

if ($response->successful()) {
    $data = $response->json();
    $token = $data['token'] ?? null;
    $user = $data['auth'];
    
    // Stocker le token
    if ($token) {
        session(['auth_token' => $token]);
    }
}
```

### Exemple 2 : Application React (JavaScript)

```javascript
const register = async (userData) => {
    try {
        const response = await fetch('http://localhost/api/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                model_type: 'App\\Models\\User',
                with_token: true,
                ...userData,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            // Succès
            if (data.token) {
                localStorage.setItem('auth_token', data.token);
            }
            return { success: true, user: data.auth, token: data.token };
        } else {
            // Erreur
            return { success: false, error: data };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
};

// Utilisation
register({
    name: 'John Doe',
    email: 'john@example.com',
    password: 'Password123!',
    password_confirmation: 'Password123!',
    age: 30,
    sex: 'male',
});
```

### Exemple 3 : Application Kotlin (Android)

```kotlin
import retrofit2.http.*

interface ApiService {
    @POST("api/register")
    suspend fun register(
        @Body request: RegisterRequest
    ): Response<RegisterResponse>
}

data class RegisterRequest(
    val model_type: String = "App\\Models\\User",
    val with_token: Boolean = true,
    val name: String,
    val email: String,
    val password: String,
    @SerializedName("password_confirmation")
    val passwordConfirmation: String,
    val age: Int? = null,
    val sex: String? = null,
    val phone: String? = null
)

data class RegisterResponse(
    val message: String,
    val auth: UserData,
    val token: String? = null
)

data class UserData(
    val id: Int,
    val name: String,
    val email: String,
    val emailVerifiedAt: String?,
    val createdAt: String,
    val updatedAt: String
)

// Utilisation dans un ViewModel
class RegisterViewModel(
    private val apiService: ApiService,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _registerState = MutableStateFlow<RegisterState>(RegisterState.Idle)
    val registerState: StateFlow<RegisterState> = _registerState.asStateFlow()

    suspend fun register(name: String, email: String, password: String, age: Int?) {
        _registerState.value = RegisterState.Loading

        try {
            val request = RegisterRequest(
                name = name,
                email = email,
                password = password,
                passwordConfirmation = password,
                age = age
            )

            val response = apiService.register(request)

            if (response.isSuccessful) {
                val data = response.body()
                if (data != null) {
                    data.token?.let { token ->
                        sessionManager.saveToken(token)
                    }
                    _registerState.value = RegisterState.Success(data)
                }
            } else {
                val error = response.errorBody()?.string()?.let {
                    parseErrorResponse(it)
                }
                _registerState.value = RegisterState.Error(error?.message ?: "Erreur inconnue")
            }
        } catch (e: Exception) {
            _registerState.value = RegisterState.Error(e.message ?: "Erreur réseau")
        }
    }

    private fun parseErrorResponse(json: String): ErrorResponse {
        return gson.fromJson(json, ErrorResponse::class.java)
    }
}
```

### Exemple 4 : Dans un test Laravel

```php
<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;

class RegisterTest extends TestCase
{
    public function test_user_can_register(): void
    {
        $response = $this->postJson('/api/register', [
            'model_type' => User::class,
            'with_token' => true,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'auth' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_register_fails_with_missing_name(): void
    {
        $response = $this->postJson('/api/register', [
            'model_type' => User::class,
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }
}
```

---

## Flux d'exécution

```
Requête POST /register
    ↓
Middleware validate.mail.authenticatable
    ├── Vérifie model_type existe
    ├── Vérifie model_type implémente MailAuthenticatable
    └── Échec → 400/500
    ↓
EmailRegisterRequest (Validation)
    ├── model_type: required|string
    ├── with_token: sometimes|boolean
    └── Échec → 422
    ↓
EmailRegisterAction
    ├── before() - Extrait les données
    ├── handle()
    │   ├── Vérifie model_type existe
    │   ├── Vérifie MailAuthenticatable
    │   ├── Appelle service->register()
    │   │   ├── beforeRegister() [HOOK]
    │   │   ├── Validation email/password (service)
    │   │   ├── model::generate($data) (modèle)
    │   │   ├── Log Registration Success
    │   │   └── afterRegister() [HOOK]
    │   ├── Crée le token (si with_token = true)
    │   └── Retourne AuthRegisteredData
    └── after() - Journalisation
    ↓
Réponse JSON
```

---

## Gestion des erreurs

| Situation | Code | Message |
|-----------|------|---------|
| `model_type` manquant | 400 | `model_type is required` |
| `model_type` invalide | 500 | `Model X does not exist` |
| Modèle non compatible | 500 | `Model X must implement MailAuthenticatable` |
| Validation (service) | 422 | Erreurs de validation (email, password) |
| Validation (modèle) | 422 | Erreurs de validation (name, age, etc.) |
| Erreur générique | 500 | `An error occurred during registration` |

---

## Intégration

### Dépendances de l'Action

| Dépendance | Rôle |
|------------|------|
| `NemesisInterface` | Création du token d'authentification |
| `LogRepositoryInterface` | Journalisation des événements |
| `AgentInterface` | Détection du device pour le token |
| `AuthenticationKitConfigInterface` | Configuration du nom du token |

### Appel au service

L'Action appelle `MailAuthenticationService::register()` qui orchestre :
- Validation des données (email, password)
- Création de l'utilisateur via `model::generate()`
- Logging des succès/échecs
- Hooks `beforeRegister()` et `afterRegister()`

---

## Voir aussi

- `EmailLoginAction` - Connexion d'un utilisateur
- `EmailLogoutAction` - Déconnexion d'un utilisateur
- `SendPasswordResetLinkAction` - Envoi d'un OTP de réinitialisation
- `MailAuthenticationService` - Service d'authentification générique
- `EmailRegisterRequest` - Validation de la requête
- `AuthRegisteredData` - Structure de la réponse