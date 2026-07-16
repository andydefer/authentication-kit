# SendEmailVerificationAction - Référence Technique

## Description

Action qui envoie un OTP de vérification d'email à un utilisateur. Vérifie si l'email est déjà vérifié avant d'envoyer un nouveau code.

## Endpoint

```
POST /email/verification
```

## Définition de la route

```php
Route::post('/email/verification', action_route(
    SendEmailVerificationRequest::class,
    SendEmailVerificationAction::class
))->name('verification.send');
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
| `auth_id` | `integer` | ✅ Oui | ID de l'utilisateur |

**Note :** La Request valide `model_type` et `auth_id`. L'utilisateur est authentifié via le token.

### Exemple de requête

```json
{
    "model_type": "App\\Models\\User",
    "auth_id": 1
}
```

---

## Structure de la réponse

### Succès - OTP envoyé (200 OK)

```json
{
    "message": "Verification OTP sent successfully",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00"
}
```

### Succès - Email déjà vérifié (200 OK)

```json
{
    "message": "Email already verified",
    "email": "john@example.com",
    "sentAt": "2024-01-01T12:00:00+00:00",
    "alreadyVerified": true
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

### Erreur - Échec envoi OTP (500 Internal Server Error)

```json
{
    "message": "Failed to send verification OTP",
    "status": 500,
    "errorCode": "VERIFICATION_OTP_SEND_FAILED"
}
```

### Erreur - Exception (500 Internal Server Error)

```json
{
    "message": "An error occurred while sending verification OTP",
    "status": 500,
    "errorCode": "VERIFICATION_EMAIL_ERROR"
}
```

---

## Codes d'erreur

| Code | Description |
|------|-------------|
| `VERIFICATION_OTP_SEND_FAILED` | Échec de l'envoi de l'OTP |
| `AUTHENTICATABLE_NOT_FOUND` | Utilisateur non trouvé |
| `VERIFICATION_EMAIL_ERROR` | Erreur générique lors de l'envoi |
| `MODEL_NOT_FOUND` | Le modèle spécifié n'existe pas |
| `INVALID_MODEL` | Le modèle n'implémente pas `MailAuthenticatable` |
| `MODEL_TYPE_REQUIRED` | Le champ `model_type` est manquant |

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
])->post('http://localhost/api/email/verification', [
    'model_type' => 'App\\Models\\User',
    'auth_id' => 1,
]);

if ($response->successful()) {
    $data = $response->json();
    
    if ($data['alreadyVerified'] ?? false) {
        echo 'Email déjà vérifié';
    } else {
        echo 'OTP envoyé avec succès';
    }
}
```

### Exemple 2 : Application React (JavaScript)

```javascript
const sendVerification = async (userId) => {
    const token = localStorage.getItem('auth_token');
    
    try {
        const response = await fetch('http://localhost/api/email/verification', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'Authorization': `Bearer ${token}`,
            },
            body: JSON.stringify({
                model_type: 'App\\Models\\User',
                auth_id: userId,
            }),
        });

        const data = await response.json();

        if (response.ok) {
            if (data.alreadyVerified) {
                return { success: true, alreadyVerified: true };
            }
            return { success: true, alreadyVerified: false };
        } else {
            return { success: false, error: data };
        }
    } catch (error) {
        return { success: false, error: error.message };
    }
};

// Utilisation
sendVerification(1);
```

### Exemple 3 : Application Kotlin (Android)

```kotlin
import retrofit2.http.*

interface ApiService {
    @POST("api/email/verification")
    suspend fun sendVerification(
        @Header("Authorization") authorization: String,
        @Body request: SendVerificationRequest
    ): Response<SendVerificationResponse>
}

data class SendVerificationRequest(
    val model_type: String = "App\\Models\\User",
    @SerializedName("auth_id")
    val authId: Int
)

data class SendVerificationResponse(
    val message: String,
    val email: String,
    val sentAt: String,
    val alreadyVerified: Boolean = false
)

// Utilisation dans un ViewModel
class VerificationViewModel(
    private val apiService: ApiService,
    private val sessionManager: SessionManager
) : ViewModel() {

    private val _sendState = MutableStateFlow<SendState>(SendState.Idle)
    val sendState: StateFlow<SendState> = _sendState.asStateFlow()

    suspend fun sendVerification() {
        _sendState.value = SendState.Loading

        val token = sessionManager.getToken()
        if (token == null) {
            _sendState.value = SendState.Error("Token non trouvé")
            return
        }

        try {
            val request = SendVerificationRequest(
                authId = sessionManager.getUserId()
            )

            val response = apiService.sendVerification(
                authorization = "Bearer $token",
                request = request
            )

            if (response.isSuccessful) {
                val data = response.body()
                if (data != null) {
                    if (data.alreadyVerified) {
                        _sendState.value = SendState.AlreadyVerified
                    } else {
                        _sendState.value = SendState.Success
                    }
                }
            } else {
                val error = response.errorBody()?.string()?.let {
                    parseErrorResponse(it)
                }
                _sendState.value = SendState.Error(error?.message ?: "Erreur inconnue")
            }
        } catch (e: Exception) {
            _sendState.value = SendState.Error(e.message ?: "Erreur réseau")
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

class SendVerificationTest extends TestCase
{
    public function test_can_send_verification_otp(): void
    {
        // Arrange
        $user = User::factory()->create([
            'email_verified_at' => null,
        ]);
        $nemesis = app(NemesisInterface::class);
        $token = $nemesis->create($user)->getPlainText();

        // Act
        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/email/verification', [
            'model_type' => User::class,
            'auth_id' => $user->id,
        ]);

        // Assert
        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Verification OTP sent successfully',
                'email' => $user->email,
            ]);
    }

    public function test_send_fails_when_user_not_found(): void
    {
        $token = 'valid_token';

        $response = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->postJson('/api/email/verification', [
            'model_type' => User::class,
            'auth_id' => 9999,
        ]);

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Authenticatable not found',
                'status' => 404,
                'errorCode' => 'AUTHENTICATABLE_NOT_FOUND',
            ]);
    }
}
```

---

## Flux d'exécution

```
Requête POST /email/verification
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
SendEmailVerificationRequest (Validation)
    ├── model_type: required|string
    ├── auth_id: required|integer
    └── Échec → 422
    ↓
SendEmailVerificationAction
    ├── before() - Extrait les données
    ├── handle()
    │   ├── Vérifie model_type existe
    │   ├── Recherche l'utilisateur
    │   │   └── Échec → 404 (AUTHENTICATABLE_NOT_FOUND)
    │   ├── Vérifie si email déjà vérifié
    │   │   └── Oui → 200 (alreadyVerified: true)
    │   ├── Appelle service->sendEmailVerificationOtp()
    │   │   └── Échec → 500 (VERIFICATION_OTP_SEND_FAILED)
    │   └── Retourne EmailVerificationSentData
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
| `auth_id` manquant | 422 | `The auth_id field is required` |
| Utilisateur non trouvé | 404 | `Authenticatable not found` |
| Échec envoi OTP | 500 | `Failed to send verification OTP` |
| Exception générique | 500 | `An error occurred while sending verification OTP` |

---

## Intégration

### Dépendances de l'Action

| Dépendance | Rôle |
|------------|------|
| `MailAuthenticationInterface` | Service d'authentification pour l'envoi OTP |
| `LogRepositoryInterface` | Journalisation des événements |

### Appel au service

L'Action appelle `MailAuthenticationService::sendEmailVerificationOtp()` qui orchestre :
- Vérification de l'email
- Création d'un OTP
- Envoi de l'OTP par email
- Logging des succès/échecs

---

## Voir aussi

- `VerifyEmailAction` - Vérification de l'email avec OTP
- `ResendEmailVerificationAction` - Renvoi de l'OTP de vérification
- `MailAuthenticationService` - Service d'authentification générique
- `SendEmailVerificationRequest` - Validation de la requête
- `EmailVerificationSentData` - Structure de la réponse