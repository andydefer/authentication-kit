# EmailLogoutAction - Référence Technique

## Description

Action qui gère la déconnexion d'un utilisateur en révoquant son token d'authentification. Valide le token, récupère l'utilisateur associé et effectue la déconnexion via le service.

## Endpoint

```
POST /logout
```

## Définition de la route

```php
Route::post('/logout', action_route(
    EmailLogoutRequest::class,
    EmailLogoutAction::class
))->name('logout');
```

## Middleware

| Middleware | Rôle |
|------------|------|
| `validate.mail.authenticatable` | Valide que le `model_type` existe et implémente `MailAuthenticatable` |
| `nemesis.token` | Valide que le token est présent, valide et non expiré |

---

## Structure de la requête

### Headers

| Header | Valeur | Requis |
|--------|--------|--------|
| `Content-Type` | `application/json` | ✅ Oui |
| `Accept` | `application/json` | ✅ Oui |
| `Authorization` | `Bearer {token}` | ✅ Oui |

### Body (JSON)

| Champ | Type | Requis | Description |
|-------|------|--------|-------------|
| `model_type` | `string` | ✅ Oui | FQCN du modèle (ex: `App\\Models\\User`) |
| `token` | `string` | ✅ Oui | Le token d'authentification à révoquer |

**Note :** La Request valide `model_type` et `token`. Le token est également vérifié dans le header `Authorization`.

### Exemple de requête

```json
{
    "model_type": "App\\Models\\User",
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c"
}
```

---

## Structure de la réponse

### Succès (204 No Content)

```json
// Aucun contenu retourné
```

### Erreur - Token invalide (401 Unauthorized)

```json
{
    "message": "Invalid token",
    "status": 401,
    "errorCode": "INVALID_TOKEN"
}
```

### Erreur - Token expiré (401 Unauthorized)

```json
{
    "message": "Token expired",
    "status": 401,
    "errorCode": "TOKEN_EXPIRED"
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

### Erreur - Échec de la déconnexion (500 Internal Server Error)

```json
{
    "message": "Logout failed",
    "status": 500,
    "errorCode": "LOGOUT_FAILED"
}
```

### Erreur - Exception lors de la déconnexion (500 Internal Server Error)

```json
{
    "message": "Logout failed: Database connection error",
    "status": 500,
    "errorCode": "LOGOUT_EXCEPTION"
}
```

---

## Codes d'erreur

| Code | Description |
|------|-------------|
| `INVALID_TOKEN` | Le token est invalide ou introuvable |
| `TOKEN_EXPIRED` | Le token a expiré |
| `AUTHENTICATABLE_NOT_FOUND` | Utilisateur associé au token non trouvé |
| `LOGOUT_FAILED` | Échec de la déconnexion |
| `LOGOUT_EXCEPTION` | Exception lors de la déconnexion |
| `MODEL_NOT_FOUND` | Le modèle spécifié n'existe pas |
| `INVALID_MODEL` | Le modèle n'implémente pas `MailAuthenticatable` |

---

## Exemples d'appel

### Exemple 1 : Laravel HTTP Client

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

$token = session('auth_token');

$response = Http::withHeaders([
    'Authorization' => 'Bearer ' . $token,
])->post('http://localhost/api/logout', [
    'model_type' => 'App\\Models\\User',
    'token' => $token,
]);

if ($response->successful()) {
    // Succès - 204 No Content
    session()->forget('auth_token');
} else {
    $error = $response->json();
    echo 'Erreur: ' . ($error['message'] ?? 'Unknown error');
}
```

### Exemple 2 : Application React (JavaScript)

```javascript
const logout = async () => {
    const token = localStorage.getItem('auth_token');
    
    try {
        const response = await fetch('http://localhost/api/logout', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify({
                model_type: 'App\\Models\\User',
                token: token,
            }),
        });

        if (response.ok) {
            // Succès - 204 No Content
            localStorage.removeItem('auth_token');
            return { success: true };
        } else {
            const error = await response.json();
            return { success: false, error: error };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
};

// Utilisation
logout();
```

### Exemple 3 : Application Kotlin (Android)

```kotlin
import retrofit2.http.*

interface ApiService {
    @POST("api/logout")
    suspend fun logout(
        @Header("Authorization") authorization: String,
        @Body request: LogoutRequest
    ): Response<Unit>
}

data class LogoutRequest(
    val model_type: String = "App\\Models\\User",
    val token: String
)

// Utilisation dans un ViewModel
class LogoutViewModel(
    private val apiService: ApiService,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _logoutState = MutableStateFlow<LogoutState>(LogoutState.Idle)
    val logoutState: StateFlow<LogoutState> = _logoutState.asStateFlow()

    suspend fun logout() {
        _logoutState.value = LogoutState.Loading

        val token = sessionManager.getToken()
        if (token == null) {
            _logoutState.value = LogoutState.Error("Token non trouvé")
            return
        }

        try {
            val response = apiService.logout(
                authorization = "Bearer $token",
                request = LogoutRequest(token = token)
            )

            if (response.isSuccessful) {
                sessionManager.clearToken()
                _logoutState.value = LogoutState.Success
            } else {
                val error = response.errorBody()?.string()?.let {
                    parseErrorResponse(it)
                }
                _logoutState.value = LogoutState.Error(error?.message ?: "Erreur inconnue")
            }
        } catch (e: Exception) {
            _logoutState.value = LogoutState.Error(e.message ?: "Erreur réseau")
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
use AndyDefer\Nemesis\Contracts\Services\NemesisInterface;

class LogoutTest extends TestCase
{
    public function test_user_can_logout(): void
    {
        // Arrange
        $user = User::factory()->create();
        $nemesis = app(NemesisInterface::class);
        $token = $nemesis->create($user)->getPlainText();

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/logout', [
            'model_type' => User::class,
            'token' => $token,
        ]);

        // Assert
        $response->assertStatus(204);
    }

    public function test_logout_fails_with_invalid_token(): void
    {
        $response = $this->postJson('/api/logout', [
            'model_type' => User::class,
            'token' => 'invalid_token',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid token',
                'status' => 401,
                'errorCode' => 'INVALID_TOKEN',
            ]);
    }
}
```

---

## Flux d'exécution

```
Requête POST /logout
    ↓
Middleware validate.mail.authenticatable
    ├── Vérifie model_type existe
    ├── Vérifie model_type implémente MailAuthenticatable
    └── Échec → 400/500
    ↓
Middleware nemesis.token
    ├── Vérifie token présent dans header
    ├── Vérifie token valide
    ├── Vérifie token non expiré
    └── Échec → 401
    ↓
EmailLogoutRequest (Validation)
    ├── model_type: required|string
    ├── token: required|string
    └── Échec → 422
    ↓
EmailLogoutAction
    ├── before() - Valide le modèle
    ├── handle()
    │   ├── Recherche le token par hash
    │   │   └── Échec → 401 (INVALID_TOKEN)
    │   ├── Vérifie expiration du token
    │   │   └── Échec → 401 (TOKEN_EXPIRED)
    │   ├── Récupère l'utilisateur associé
    │   │   └── Échec → 404 (AUTHENTICATABLE_NOT_FOUND)
    │   ├── Appelle service->logout()
    │   │   ├── beforeLogout() [HOOK]
    │   │   ├── Révocation du token
    │   │   └── afterLogout() [HOOK]
    │   └── Retourne EmptyData (204)
    └── after() - Journalisation
    ↓
Réponse 204 No Content
```

---

## Gestion des erreurs

| Situation | Code | Message |
|-----------|------|---------|
| `model_type` manquant | 400 | `model_type is required` |
| `model_type` invalide | 500 | `Model X does not exist` |
| Modèle non compatible | 500 | `Model X must implement MailAuthenticatable` |
| Token manquant | 401 | Erreur du middleware `nemesis.token` |
| Token invalide | 401 | `Invalid token` |
| Token expiré | 401 | `Token expired` |
| Utilisateur non trouvé | 404 | `Authenticatable not found` |
| Échec déconnexion | 500 | `Logout failed` |
| Exception déconnexion | 500 | `Logout failed: {message}` |

---

## Intégration

### Dépendances de l'Action

| Dépendance | Rôle |
|------------|------|
| `NemesisInterface` | Recherche et révocation du token |
| `LogRepositoryInterface` | Journalisation des événements |

### Appel au service

L'Action appelle `MailAuthenticationService::logout()` qui orchestre :
- Récupération du token par hash
- Révocation du token via Nemesis
- Logging des succès/échecs
- Hooks `beforeLogout()` et `afterLogout()`

---

## Voir aussi

- `EmailLoginAction` - Connexion d'un utilisateur
- `EmailRegisterAction` - Inscription d'un utilisateur
- `MailAuthenticationService` - Service d'authentification générique
- `EmailLogoutRequest` - Validation de la requête
- `EmptyData` - Structure de la réponse (204)