# SendPasswordResetLinkAction - Référence Technique

## Description

Action qui envoie un OTP de réinitialisation de mot de passe à l'email d'un utilisateur. Pour des raisons de sécurité, retourne toujours une réponse 200, que l'utilisateur existe ou non.

## Endpoint

```
POST /forgot-password
```

## Définition de la route

```php
Route::post('/forgot-password', action_route(
    SendPasswordResetLinkRequest::class,
    SendPasswordResetLinkAction::class
))->name('password.email');
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
| `model_type` | `string` | ❌ Non | FQCN du modèle (ex: `App\\Models\\User`) - Optionnel pour cette route |
| `email` | `string` | ✅ Oui | Email de l'utilisateur |

**Note :** La Request ne valide que `email`. Le `model_type` n'est pas requis pour cette action.

### Exemple de requête

```json
{
    "model_type": "App\\Models\\User",
    "email": "john@example.com"
}
```

---

## Structure de la réponse

### Succès - Toujours 200 OK (même si l'utilisateur n'existe pas)

```json
{
    "message": "Password reset OTP sent successfully",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00"
}
```

### Erreur - Exception (500 Internal Server Error)

```json
{
    "message": "An error occurred while sending the reset OTP",
    "status": 500,
    "errorCode": "RESET_LINK_ERROR"
}
```

---

## Codes d'erreur

| Code | Description |
|------|-------------|
| `RESET_LINK_ERROR` | Erreur générique lors de l'envoi de l'OTP |
| `INVALID_RECORD_TYPE` | Type de record invalide |

---

## Exemples d'appel

### Exemple 1 : Laravel HTTP Client

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

$response = Http::post('http://localhost/api/forgot-password', [
    'model_type' => 'App\\Models\\User',
    'email' => 'john@example.com',
]);

// Toujours 200, quelle que soit la réponse du service
if ($response->successful()) {
    $data = $response->json();
    echo 'OTP envoyé avec succès (ou email inexistant)';
}
```

### Exemple 2 : Application React (JavaScript)

```javascript
const forgotPassword = async (email) => {
    try {
        const response = await fetch('http://localhost/api/forgot-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                model_type: 'App\\Models\\User',
                email: email,
            }),
        });

        const data = await response.json();

        // Toujours 200
        if (response.ok) {
            return { success: true, message: data.message };
        } else {
            return { success: false, error: data };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
};

// Utilisation
forgotPassword('john@example.com');
```

### Exemple 3 : Application Kotlin (Android)

```kotlin
import retrofit2.http.*

interface ApiService {
    @POST("api/forgot-password")
    suspend fun forgotPassword(
        @Body request: ForgotPasswordRequest
    ): Response<ForgotPasswordResponse>
}

data class ForgotPasswordRequest(
    val model_type: String = "App\\Models\\User",
    val email: String
)

data class ForgotPasswordResponse(
    val message: String,
    val email: String,
    val sentAt: String
)

// Utilisation dans un ViewModel
class ForgotPasswordViewModel(
    private val apiService: ApiService
) : ViewModel() {

    private val _forgotState = MutableStateFlow<ForgotState>(ForgotState.Idle)
    val forgotState: StateFlow<ForgotState> = _forgotState.asStateFlow()

    suspend fun forgotPassword(email: String) {
        _forgotState.value = ForgotState.Loading

        try {
            val request = ForgotPasswordRequest(email = email)

            val response = apiService.forgotPassword(request)

            // Toujours 200 si succès
            if (response.isSuccessful) {
                val data = response.body()
                if (data != null) {
                    _forgotState.value = ForgotState.Success(data)
                }
            } else {
                val error = response.errorBody()?.string()?.let {
                    parseErrorResponse(it)
                }
                _forgotState.value = ForgotState.Error(error?.message ?: "Erreur inconnue")
            }
        } catch (e: Exception) {
            _forgotState.value = ForgotState.Error(e.message ?: "Erreur réseau")
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

class ForgotPasswordTest extends TestCase
{
    public function test_can_request_password_reset_for_existing_user(): void
    {
        // Arrange
        $user = User::factory()->create();

        // Act
        $response = $this->postJson('/api/forgot-password', [
            'model_type' => User::class,
            'email' => $user->email,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password reset OTP sent successfully',
                'email' => $user->email,
            ]);
    }

    public function test_request_password_reset_returns_200_for_nonexistent_user(): void
    {
        // Act - Email inexistant
        $response = $this->postJson('/api/forgot-password', [
            'model_type' => User::class,
            'email' => 'nonexistent@example.com',
        ]);

        // Assert - Toujours 200 pour des raisons de sécurité
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password reset OTP sent successfully',
                'email' => 'nonexistent@example.com',
            ]);
    }
}
```

---

## Flux d'exécution

```
Requête POST /forgot-password
    ↓
Middleware validate.mail.authenticatable
    ├── Vérifie model_type existe (si présent)
    ├── Vérifie model_type implémente MailAuthenticatable (si présent)
    └── Échec → 400/500
    ↓
SendPasswordResetLinkRequest (Validation)
    ├── email: required|email
    └── Échec → 422
    ↓
SendPasswordResetLinkAction
    ├── handle()
    │   ├── Vérifie si l'utilisateur existe (userExists)
    │   ├── Appelle service->sendPasswordResetOtp()
    │   │   ├── beforeSendPasswordResetOtp() [HOOK]
    │   │   ├── Création de l'OTP (si user existe)
    │   │   ├── Envoi de l'OTP par email (si user existe)
    │   │   └── afterSendPasswordResetOtp() [HOOK]
    │   └── Retourne TOUJOURS 200 (même si user inexistant)
    └── after() - Journalisation (seulement si user existe)
    ↓
Réponse JSON 200
```

---

## Gestion des erreurs

| Situation | Code | Message |
|-----------|------|---------|
| `email` manquant | 422 | `The email field is required` |
| `email` invalide | 422 | `The email must be a valid email address` |
| `model_type` invalide | 500 | `Model X does not exist` (si présent) |
| Modèle non compatible | 500 | `Model X must implement MailAuthenticatable` (si présent) |
| Exception générique | 500 | `An error occurred while sending the reset OTP` |

---

## 🔒 Considérations de sécurité

| Aspect | Comportement |
|--------|--------------|
| **Réponse** | Toujours 200, même si l'utilisateur n'existe pas |
| **Logs** | Les logs ne sont créés que si l'utilisateur existe |
| **Rate limiting** | Limite le nombre de requêtes par email |
| **OTP** | Expire après 600 secondes (10 minutes) |

---

## Intégration

### Dépendances de l'Action

| Dépendance | Rôle |
|------------|------|
| `MailAuthenticationInterface` | Service d'authentification pour l'envoi OTP |
| `LogRepositoryInterface` | Journalisation des événements |

### Appel au service

L'Action appelle `MailAuthenticationService::sendPasswordResetOtp()` qui orchestre :
- Vérification de l'existence de l'utilisateur via `userExists()`
- Création d'un OTP via OtpService
- Envoi de l'OTP par email via Notification
- Logging des succès/échecs (via after())
- Hooks `beforeSendPasswordResetOtp()` et `afterSendPasswordResetOtp()`

---

## Voir aussi

- `ResetPasswordAction` - Réinitialisation du mot de passe avec OTP
- `EmailLoginAction` - Connexion d'un utilisateur
- `MailAuthenticationService` - Service d'authentification générique
- `SendPasswordResetLinkRequest` - Validation de la requête
- `PasswordResetLinkSentData` - Structure de la réponse