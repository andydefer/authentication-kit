# ResetPasswordAction - Référence Technique

## Description

Action qui gère la réinitialisation du mot de passe d'un utilisateur via un OTP. Valide la confirmation du mot de passe, vérifie l'OTP et met à jour le mot de passe.

## Endpoint

```
POST /reset-password
```

## Définition de la route

```php
Route::post('/reset-password', action_route(
    ResetPasswordRequest::class,
    ResetPasswordAction::class
))->name('password.update');
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
| `token` | `string` | ✅ Oui | Code OTP reçu par email |
| `password` | `string` | ✅ Oui | Nouveau mot de passe (min 8 caractères) |
| `password_confirmation` | `string` | ✅ Oui | Confirmation du nouveau mot de passe |

### Exemple de requête

```json
{
    "model_type": "App\\Models\\User",
    "email": "john@example.com",
    "token": "123456",
    "password": "NewPassword123!",
    "password_confirmation": "NewPassword123!"
}
```

---

## Structure de la réponse

### Succès (200 OK)

```json
{
    "message": "Password reset successfully",
    "email": "john@example.com",
    "resetAt": "2024-01-01T12:00:00+00:00"
}
```

### Erreur - Mot de passe non confirmé (422 Unprocessable Entity)

```json
{
    "message": "Password confirmation does not match",
    "status": 422,
    "errorCode": "PASSWORD_CONFIRMATION_MISMATCH"
}
```

### Erreur - OTP invalide ou expiré (400 Bad Request)

```json
{
    "message": "Invalid or expired reset OTP",
    "status": 400,
    "errorCode": "INVALID_RESET_OTP"
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
            "The password must be at least 8 characters."
        ]
    }
}
```

### Erreur - Exception (500 Internal Server Error)

```json
{
    "message": "An error occurred while resetting the password",
    "status": 500,
    "errorCode": "RESET_PASSWORD_ERROR"
}
```

---

## Codes d'erreur

| Code | Description |
|------|-------------|
| `PASSWORD_CONFIRMATION_MISMATCH` | Les mots de passe ne correspondent pas |
| `INVALID_RESET_OTP` | L'OTP est invalide ou expiré |
| `VALIDATION_ERROR` | Erreur de validation des données |
| `RESET_PASSWORD_ERROR` | Erreur générique lors de la réinitialisation |
| `MODEL_NOT_FOUND` | Le modèle spécifié n'existe pas |
| `INVALID_MODEL` | Le modèle n'implémente pas `MailAuthenticatable` |

---

## Exemples d'appel

### Exemple 1 : Laravel HTTP Client

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

$response = Http::post('http://localhost/api/reset-password', [
    'model_type' => 'App\\Models\\User',
    'email' => 'john@example.com',
    'token' => '123456',
    'password' => 'NewPassword123!',
    'password_confirmation' => 'NewPassword123!',
]);

if ($response->successful()) {
    $data = $response->json();
    echo 'Mot de passe réinitialisé avec succès';
} else {
    $error = $response->json();
    echo 'Erreur: ' . ($error['message'] ?? 'Unknown error');
}
```

### Exemple 2 : Application React (JavaScript)

```javascript
const resetPassword = async (email, token, password, passwordConfirmation) => {
    try {
        const response = await fetch('http://localhost/api/reset-password', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                model_type: 'App\\Models\\User',
                email: email,
                token: token,
                password: password,
                password_confirmation: passwordConfirmation,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            return { success: true, data: data };
        } else {
            return { success: false, error: data };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
};

// Utilisation
resetPassword(
    'john@example.com',
    '123456',
    'NewPassword123!',
    'NewPassword123!'
);
```

### Exemple 3 : Application Kotlin (Android)

```kotlin
import retrofit2.http.*

interface ApiService {
    @POST("api/reset-password")
    suspend fun resetPassword(
        @Body request: ResetPasswordRequest
    ): Response<ResetPasswordResponse>
}

data class ResetPasswordRequest(
    val model_type: String = "App\\Models\\User",
    val email: String,
    val token: String,
    val password: String,
    @SerializedName("password_confirmation")
    val passwordConfirmation: String
)

data class ResetPasswordResponse(
    val message: String,
    val email: String,
    val resetAt: String
)

// Utilisation dans un ViewModel
class ResetPasswordViewModel(
    private val apiService: ApiService
) : ViewModel() {

    private val _resetState = MutableStateFlow<ResetState>(ResetState.Idle)
    val resetState: StateFlow<ResetState> = _resetState.asStateFlow()

    suspend fun resetPassword(email: String, token: String, password: String) {
        _resetState.value = ResetState.Loading

        try {
            val request = ResetPasswordRequest(
                email = email,
                token = token,
                password = password,
                passwordConfirmation = password
            )

            val response = apiService.resetPassword(request)

            if (response.isSuccessful) {
                val data = response.body()
                if (data != null) {
                    _resetState.value = ResetState.Success(data)
                }
            } else {
                val error = response.errorBody()?.string()?.let {
                    parseErrorResponse(it)
                }
                _resetState.value = ResetState.Error(error?.message ?: "Erreur inconnue")
            }
        } catch (e: Exception) {
            _resetState.value = ResetState.Error(e.message ?: "Erreur réseau")
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
use AndyDefer\LaravelOtp\Services\OtpService;
use AndyDefer\LaravelOtp\ValueObjects\PurposeVO;

class ResetPasswordTest extends TestCase
{
    public function test_can_reset_password(): void
    {
        // Arrange
        $user = User::factory()->create();
        $otpService = app(OtpService::class);
        
        $purpose = new PurposeVO(
            value: 'password_reset',
            label: 'Password Reset',
            ttl: 600,
            maxAttempts: 3
        );
        
        $otp = $otpService->create($user, $purpose);

        // Act
        $response = $this->postJson('/api/reset-password', [
            'model_type' => User::class,
            'email' => $user->email,
            'token' => $otp->code,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Password reset successfully',
                'email' => $user->email,
            ]);
    }

    public function test_reset_fails_with_invalid_otp(): void
    {
        $response = $this->postJson('/api/reset-password', [
            'model_type' => User::class,
            'email' => 'john@example.com',
            'token' => '000000',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or expired reset OTP',
                'status' => 400,
                'errorCode' => 'INVALID_RESET_OTP',
            ]);
    }
}
```

---

## Flux d'exécution

```
Requête POST /reset-password
    ↓
Middleware validate.mail.authenticatable
    ├── Vérifie model_type existe
    ├── Vérifie model_type implémente MailAuthenticatable
    └── Échec → 400/500
    ↓
ResetPasswordRequest (Validation)
    ├── model_type: required|string
    ├── email: required|email
    ├── token: required|string + ValidOtpRule
    ├── password: required|min:8|confirmed
    ├── password_confirmation: required|min:8
    └── Échec → 422
    ↓
ResetPasswordAction
    ├── handle()
    │   ├── Vérifie correspondance password/password_confirmation
    │   │   └── Échec → 422 (PASSWORD_CONFIRMATION_MISMATCH)
    │   ├── Appelle service->resetPassword()
    │   │   ├── beforeResetPassword() [HOOK]
    │   │   ├── Vérifie OTP
    │   │   ├── Met à jour le mot de passe
    │   │   ├── Log Password Reset Success
    │   │   └── afterResetPassword() [HOOK]
    │   └── Retourne PasswordResetSuccessData
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
| Email invalide | 422 | `The email must be a valid email address` |
| Token manquant | 422 | `The token field is required` |
| Token invalide/expiré | 400 | `Invalid or expired reset OTP` |
| Mot de passe trop court | 422 | `The password must be at least 8 characters` |
| Confirmation incorrecte | 422 | `Password confirmation does not match` |
| Exception générique | 500 | `An error occurred while resetting the password` |

---

## Intégration

### Dépendances de l'Action

| Dépendance | Rôle |
|------------|------|
| `MailAuthenticationInterface` | Service d'authentification pour la réinitialisation |
| `LogRepositoryInterface` | Journalisation des événements |

### Appel au service

L'Action appelle `MailAuthenticationService::resetPassword()` qui orchestre :
- Vérification de l'OTP via OtpService
- Mise à jour du mot de passe
- Logging des succès/échecs
- Hooks `beforeResetPassword()` et `afterResetPassword()`

---

## Voir aussi

- `SendPasswordResetLinkAction` - Envoi de l'OTP de réinitialisation
- `EmailLoginAction` - Connexion d'un utilisateur
- `MailAuthenticationService` - Service d'authentification générique
- `ResetPasswordRequest` - Validation de la requête
- `PasswordResetSuccessData` - Structure de la réponse
- `ValidOtpRule` - Règle de validation OTP