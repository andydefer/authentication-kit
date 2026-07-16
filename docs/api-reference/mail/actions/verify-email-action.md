# VerifyEmailAction - Référence Technique

## Description

Action qui vérifie l'email d'un utilisateur via un OTP. Valide le code, marque l'email comme vérifié et journalise la tentative.

## Endpoint

```
POST /email/verify
```

## Définition de la route

```php
Route::post('/email/verify', action_route(
    VerifyEmailRequest::class,
    VerifyEmailAction::class
))->name('verification.verify');
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

**Note :** La Request valide `model_type`, `email` et `token`. L'OTP est validé par la règle `ValidOtpRule`.

### Exemple de requête

```json
{
    "model_type": "App\\Models\\User",
    "email": "john@example.com",
    "token": "123456"
}
```

---

## Structure de la réponse

### Succès - Email vérifié (200 OK)

```json
{
    "message": "Email verified successfully",
    "email": "john@example.com",
    "verifiedAt": "2024-01-01T12:00:00+00:00",
    "alreadyVerified": false
}
```

### Succès - Email déjà vérifié (200 OK)

```json
{
    "message": "Email already verified",
    "email": "john@example.com",
    "verifiedAt": "2024-01-01T10:00:00+00:00",
    "alreadyVerified": true
}
```

### Erreur - OTP invalide ou expiré (400 Bad Request)

```json
{
    "message": "Invalid or expired verification OTP",
    "status": 400,
    "errorCode": "INVALID_VERIFICATION_OTP"
}
```

### Erreur - Utilisateur non trouvé (500 Internal Server Error)

```json
{
    "message": "An error occurred while verifying email",
    "status": 500,
    "errorCode": "VERIFY_EMAIL_ERROR"
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
        "token": [
            "The token field is required."
        ]
    }
}
```

---

## Codes d'erreur

| Code | Description |
|------|-------------|
| `INVALID_VERIFICATION_OTP` | L'OTP est invalide ou expiré |
| `VERIFY_EMAIL_ERROR` | Erreur générique lors de la vérification |
| `VALIDATION_ERROR` | Erreur de validation des données |
| `MODEL_NOT_FOUND` | Le modèle spécifié n'existe pas |
| `INVALID_MODEL` | Le modèle n'implémente pas `MailAuthenticatable` |

---

## Exemples d'appel

### Exemple 1 : Laravel HTTP Client

```php
<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;

$response = Http::post('http://localhost/api/email/verify', [
    'model_type' => 'App\\Models\\User',
    'email' => 'john@example.com',
    'token' => '123456',
]);

if ($response->successful()) {
    $data = $response->json();
    
    if ($data['alreadyVerified']) {
        echo 'Email déjà vérifié le ' . $data['verifiedAt'];
    } else {
        echo 'Email vérifié avec succès !';
    }
}
```

### Exemple 2 : Application React (JavaScript)

```javascript
const verifyEmail = async (email, token) => {
    try {
        const response = await fetch('http://localhost/api/email/verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify({
                model_type: 'App\\Models\\User',
                email: email,
                token: token,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            if (data.alreadyVerified) {
                return { success: true, alreadyVerified: true, verifiedAt: data.verifiedAt };
            }
            return { success: true, alreadyVerified: false, verifiedAt: data.verifiedAt };
        } else {
            return { success: false, error: data };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
};

// Utilisation
verifyEmail('john@example.com', '123456');
```

### Exemple 3 : Application Kotlin (Android)

```kotlin
import retrofit2.http.*

interface ApiService {
    @POST("api/email/verify")
    suspend fun verifyEmail(
        @Body request: VerifyEmailRequest
    ): Response<VerifyEmailResponse>
}

data class VerifyEmailRequest(
    val model_type: String = "App\\Models\\User",
    val email: String,
    val token: String
)

data class VerifyEmailResponse(
    val message: String,
    val email: String,
    val verifiedAt: String,
    val alreadyVerified: Boolean = false
)

// Utilisation dans un ViewModel
class VerifyEmailViewModel(
    private val apiService: ApiService
) : ViewModel() {

    private val _verifyState = MutableStateFlow<VerifyState>(VerifyState.Idle)
    val verifyState: StateFlow<VerifyState> = _verifyState.asStateFlow()

    suspend fun verifyEmail(email: String, token: String) {
        _verifyState.value = VerifyState.Loading

        try {
            val request = VerifyEmailRequest(
                email = email,
                token = token
            )

            val response = apiService.verifyEmail(request)

            if (response.isSuccessful) {
                val data = response.body()
                if (data != null) {
                    if (data.alreadyVerified) {
                        _verifyState.value = VerifyState.AlreadyVerified(data)
                    } else {
                        _verifyState.value = VerifyState.Success(data)
                    }
                }
            } else {
                val error = response.errorBody()?.string()?.let {
                    parseErrorResponse(it)
                }
                _verifyState.value = VerifyState.Error(error?.message ?: "Erreur inconnue")
            }
        } catch (e: Exception) {
            _verifyState.value = VerifyState.Error(e.message ?: "Erreur réseau")
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

class VerifyEmailTest extends TestCase
{
    public function test_can_verify_email(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        
        $otpService = app(OtpService::class);
        $purpose = new PurposeVO(
            value: 'email_verification',
            label: 'Email Verification',
            ttl: 300,
            maxAttempts: 3
        );
        $otp = $otpService->create($user, $purpose);

        // Act
        $response = $this->postJson('/api/email/verify', [
            'model_type' => User::class,
            'email' => $user->email,
            'token' => $otp->code,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Email verified successfully',
                'email' => $user->email,
                'alreadyVerified' => false,
            ]);
    }

    public function test_verify_fails_with_invalid_otp(): void
    {
        $response = $this->postJson('/api/email/verify', [
            'model_type' => User::class,
            'email' => 'john@example.com',
            'token' => '000000',
        ]);

        $response->assertStatus(400)
            ->assertJson([
                'message' => 'Invalid or expired verification OTP',
                'status' => 400,
                'errorCode' => 'INVALID_VERIFICATION_OTP',
            ]);
    }
}
```

---

## Flux d'exécution

```
Requête POST /email/verify
    ↓
Middleware validate.mail.authenticatable
    ├── Vérifie model_type existe
    ├── Vérifie model_type implémente MailAuthenticatable
    └── Échec → 400/500
    ↓
VerifyEmailRequest (Validation)
    ├── model_type: required|string
    ├── email: required|email
    ├── token: required|string + ValidOtpRule
    └── Échec → 422
    ↓
VerifyEmailAction
    ├── handle()
    │   ├── Normalise l'email (trim + lowercase)
    │   ├── Recherche l'utilisateur (avec soft deletes)
    │   │   └── Échec → 500 (VERIFY_EMAIL_ERROR)
    │   ├── Vérifie si l'utilisateur est soft deleted
    │   │   └── Échec → 500 (VERIFY_EMAIL_ERROR)
    │   ├── Vérifie si email déjà vérifié
    │   │   └── Oui → 200 (alreadyVerified: true)
    │   ├── Appelle service->verifyEmail()
    │   │   ├── beforeVerifyEmail() [HOOK]
    │   │   ├── Vérifie OTP via OtpService
    │   │   ├── Marque email comme vérifié
    │   │   ├── Log Verification Success
    │   │   └── afterVerifyEmail() [HOOK]
    │   └── Retourne EmailVerifiedData
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
| `email` invalide | 422 | `The email must be a valid email address` |
| `token` manquant | 422 | `The token field is required` |
| `token` invalide/expiré | 400 | `Invalid or expired verification OTP` |
| Utilisateur non trouvé | 500 | `An error occurred while verifying email` |
| Utilisateur soft deleted | 500 | `An error occurred while verifying email` |
| Exception générique | 500 | `An error occurred while verifying email` |

---

## Considérations sur les Soft Deletes

| Situation | Comportement |
|-----------|--------------|
| Utilisateur supprimé (soft delete) | Erreur 500 - `VERIFY_EMAIL_ERROR` |
| Utilisateur restauré | Vérification possible |
| Utilisateur non supprimé | Vérification normale |

L'Action utilise `withTrashed()` pour inclure les utilisateurs supprimés, mais les vérifie ensuite via `trashed()`.

---

## Intégration

### Dépendances de l'Action

| Dépendance | Rôle |
|------------|------|
| `MailAuthenticationInterface` | Service d'authentification pour la vérification |
| `LogRepositoryInterface` | Journalisation des événements |

### Appel au service

L'Action appelle `MailAuthenticationService::verifyEmail()` qui orchestre :
- Vérification de l'OTP via OtpService
- Mise à jour de `email_verified_at`
- Logging des succès/échecs
- Hooks `beforeVerifyEmail()` et `afterVerifyEmail()`

---

## Voir aussi

- `SendEmailVerificationAction` - Envoi de l'OTP de vérification
- `ResendEmailVerificationAction` - Renvoi de l'OTP de vérification
- `MailAuthenticationService` - Service d'authentification générique
- `VerifyEmailRequest` - Validation de la requête
- `EmailVerifiedData` - Structure de la réponse
- `ValidOtpRule` - Règle de validation OTP