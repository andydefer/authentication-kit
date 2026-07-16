# EmailLoginAction - Référence Technique

## Description

Action qui gère l'authentification d'un utilisateur par email et mot de passe. Valide les identifiants, génère un token d'authentification et journalise la tentative.

## Endpoint

```
POST /login
```

## Définition de la route

```php
Route::post('/login', action_route(
    EmailLoginRequest::class,
    EmailLoginAction::class
))->name('login');
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
| `email` | `string` | ✅ Oui | Email de l'utilisateur |
| `password` | `string` | ✅ Oui | Mot de passe de l'utilisateur |

**Note :** La Request valide `model_type`, `email` et `password`. Tous les autres champs sont ignorés.

### Exemple de requête

```json
{
    "model_type": "App\\Models\\User",
    "email": "john@example.com",
    "password": "Password123!"
}
```

---

## Structure de la réponse

### Succès (200 OK)

```json
{
    "message": "Login successful",
    "auth": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "emailVerifiedAt": "2024-01-01T12:00:00+00:00",
        "createdAt": "2024-01-01T10:00:00+00:00",
        "updatedAt": "2024-01-01T12:00:00+00:00"
    },
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"
}
```

### Erreur - Credentials manquants (400 Bad Request)

**Exemple 1 : Email et password manquants**

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

**Exemple 2 : Email manquant uniquement**

```json
{
    "message": "Email and password are required",
    "status": 400,
    "errorCode": "MISSING_CREDENTIALS",
    "errors": {
        "email": ["The email field is required."]
    }
}
```

**Exemple 3 : Password manquant uniquement**

```json
{
    "message": "Email and password are required",
    "status": 400,
    "errorCode": "MISSING_CREDENTIALS",
    "errors": {
        "password": ["The password field is required."]
    }
}
```

### Erreur - Identifiants invalides (401 Unauthorized)

```json
{
    "message": "Invalid credentials",
    "status": 401,
    "errorCode": "INVALID_CREDENTIALS"
}
```

### Erreur - Utilisateur non trouvé (404 Not Found)

```json
{
    "message": "Authenticatable not found",
    "status": 404,
    "errorCode": "AUTHENTICATABLE_NOT_FOUND"
}
```

### Erreur - Validation (422 Unprocessable Entity)

```json
{
    "message": "The email field is required. (and 1 more error)",
    "status": 422,
    "errorCode": "VALIDATION_ERROR",
    "errors": {
        "email": [
            "The email field is required."
        ],
        "password": [
            "The password field is required."
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
| `MISSING_CREDENTIALS` | Email ou password manquant dans la requête |
| `INVALID_CREDENTIALS` | Email ou password incorrect |
| `AUTHENTICATABLE_NOT_FOUND` | Utilisateur non trouvé après authentification |
| `VALIDATION_ERROR` | Erreur de validation des données |
| `MODEL_NOT_FOUND` | Le modèle spécifié n'existe pas |
| `INVALID_MODEL` | Le modèle n'implémente pas `MailAuthenticatable` |
| `MODEL_TYPE_REQUIRED` | Le champ `model_type` est manquant |
| `LOGIN_ERROR` | Erreur générique lors de la connexion |

---

## Exemples d'appel

### Exemple 1 : Laravel HTTP Client

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

$response = Http::post('http://localhost/api/login', [
    'model_type' => 'App\\Models\\User',
    'email' => 'john@example.com',
    'password' => 'Password123!',
]);

if ($response->successful()) {
    $data = $response->json();
    $token = $data['token'];
    $user = $data['auth'];
    
    // Stocker le token pour les requêtes suivantes
    session(['auth_token' => $token]);
}
```

### Exemple 2 : Application React (JavaScript)

```javascript
const login = async (email, password) => {
    try {
        const response = await fetch('http://localhost/api/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                model_type: 'App\\Models\\User',
                email: email,
                password: password,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            // Succès
            localStorage.setItem('auth_token', data.token);
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
login('john@example.com', 'Password123!');
```

### Exemple 3 : Application Kotlin (Android)

```kotlin
import retrofit2.http.*

interface ApiService {
    @POST("api/login")
    suspend fun login(
        @Body request: LoginRequest
    ): Response<LoginResponse>
}

data class LoginRequest(
    val model_type: String = "App\\Models\\User",
    val email: String,
    val password: String
)

data class LoginResponse(
    val message: String,
    val auth: UserData,
    val token: String
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
class LoginViewModel(
    private val apiService: ApiService,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _loginState = MutableStateFlow<LoginState>(LoginState.Idle)
    val loginState: StateFlow<LoginState> = _loginState.asStateFlow()

    suspend fun login(email: String, password: String) {
        _loginState.value = LoginState.Loading

        try {
            val request = LoginRequest(
                email = email,
                password = password
            )

            val response = apiService.login(request)

            if (response.isSuccessful) {
                val data = response.body()
                if (data != null) {
                    sessionManager.saveToken(data.token)
                    _loginState.value = LoginState.Success(data)
                }
            } else {
                val error = response.errorBody()?.string()?.let {
                    parseErrorResponse(it)
                }
                _loginState.value = LoginState.Error(error?.message ?: "Erreur inconnue")
            }
        } catch (e: Exception) {
            _loginState.value = LoginState.Error(e.message ?: "Erreur réseau")
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
use Illuminate\Support\Facades\Hash;

class LoginTest extends TestCase
{
    public function test_user_can_login(): void
    {
        $user = User::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('Password123!'),
        ]);

        $response = $this->postJson('/api/login', [
            'model_type' => User::class,
            'email' => 'john@example.com',
            'password' => 'Password123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'auth' => ['id', 'name', 'email'],
                'token',
            ]);
    }

    public function test_login_fails_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/login', [
            'model_type' => User::class,
            'email' => 'john@example.com',
            'password' => 'WrongPassword!',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials',
                'status' => 401,
                'errorCode' => 'INVALID_CREDENTIALS',
            ]);
    }

    public function test_login_fails_when_email_is_missing(): void
    {
        $response = $this->postJson('/api/login', [
            'model_type' => User::class,
            'password' => 'Password123!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Email and password are required',
                'status' => 400,
                'errorCode' => 'MISSING_CREDENTIALS',
                'errors' => [
                    'email' => ['The email field is required.'],
                ],
            ]);
    }
}
```

---

## Flux d'exécution

```
Requête POST /login
    ↓
Middleware validate.mail.authenticatable
    ├── Vérifie model_type existe
    ├── Vérifie model_type implémente MailAuthenticatable
    └── Échec → 400/500
    ↓
EmailLoginRequest (Validation)
    ├── model_type: required|string
    ├── email: required|email
    ├── password: required|string
    └── Échec → 422
    ↓
EmailLoginAction
    ├── before() - Extrait les données du record
    ├── handle()
    │   ├── Vérifie email + password présents
    │   │   └── Échec → 400 (MISSING_CREDENTIALS) avec errors
    │   ├── Appelle service->login()
    │   │   ├── beforeLogin() [HOOK]
    │   │   ├── Recherche utilisateur par email
    │   │   ├── Vérification mot de passe
    │   │   ├── Log Login Success
    │   │   └── afterLogin() [HOOK]
    │   ├── Récupère l'utilisateur
    │   │   └── Échec → 404 (AUTHENTICATABLE_NOT_FOUND)
    │   ├── Crée le token Nemesis
    │   └── Retourne AuthLoginData
    └── after() - Journalisation
    ↓
Réponse JSON
```

---

## Gestion des erreurs

| Situation | Code | Message | errors |
|-----------|------|---------|--------|
| `model_type` manquant | 400 | `model_type is required` | - |
| `model_type` invalide | 500 | `Model X does not exist` | - |
| Modèle non compatible | 500 | `Model X must implement MailAuthenticatable` | - |
| Email manquant | 400 | `Email and password are required` | `{ "email": ["The email field is required."] }` |
| Password manquant | 400 | `Email and password are required` | `{ "password": ["The password field is required."] }` |
| Email et password manquants | 400 | `Email and password are required` | `{ "email": [...], "password": [...] }` |
| Email invalide | 422 | `The email must be a valid email address` | `{ "email": ["The email must be a valid email address."] }` |
| Identifiants invalides | 401 | `Invalid credentials` | - |
| Utilisateur non trouvé | 404 | `Authenticatable not found` | - |
| Exception générique | 500 | `An error occurred during login` | - |

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

L'Action appelle `MailAuthenticationService::login()` qui orchestre :
- Recherche de l'utilisateur par email
- Vérification du mot de passe
- Logging des succès/échecs
- Hooks `beforeLogin()` et `afterLogin()`

---

## Voir aussi

- `EmailRegisterAction` - Inscription d'un nouvel utilisateur
- `EmailLogoutAction` - Déconnexion d'un utilisateur
- `SendPasswordResetLinkAction` - Envoi d'un OTP de réinitialisation
- `MailAuthenticationService` - Service d'authentification générique
- `EmailLoginRequest` - Validation de la requête
- `AuthLoginData` - Structure de la réponse