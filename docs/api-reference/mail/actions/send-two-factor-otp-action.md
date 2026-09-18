# SendTwoFactorOtpAction - Référence Technique

## Description

Action HTTP qui envoie un OTP de vérification pour une action sensible (changement d'email, changement de mot de passe, suppression de compte, etc.).

## Hiérarchie / Implémentations

```
AbstractAction
    └── SendTwoFactorOtpAction

Dépendances injectées :
    - NemesisHelper

Services utilisés :
    - MailAuthenticationService
```

## Rôle principal

L'action :

1. Récupère l'utilisateur authentifié via le token Nemesis
2. Vérifie que le `model_type` reçu correspond au modèle authentifié
3. Vérifie que le modèle implémente `MustNemesis`
4. Délègue l'envoi à `MailAuthenticationService::sendTwoFactorOtp()`
5. Retourne une réponse JSON normalisée

Elle agit comme **point d'entrée HTTP** unique pour toute demande de code 2FA, indépendamment du type de modèle (User, Pharmacy, Doctor, etc.).

## Installation

Action interne au package `andydefer/authentication-kit`. Aucune installation spécifique n'est requise.

Pour l'utiliser, la route doit être enregistrée avec les middlewares `validate.mail.authenticatable` et `nemesis.token` :

```php
Route::middleware(['validate.mail.authenticatable', 'nemesis.token'])
    ->post('/api/send-two-factor-otp', action_route(
        SendTwoFactorOtpRequest::class,
        SendTwoFactorOtpAction::class
    ))
    ->name('send-two-factor-otp');
```

## API / Méthodes publiques

### `__construct(NemesisHelper $helper)`

Injecte le helper Nemesis qui expose l'utilisateur authentifié via le token courant.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$helper` | `NemesisHelper` | Helper Nemesis pour résoudre l'utilisateur authentifié |

---

### `handle(AbstractRecord $record): ResponseFactory`

Méthode protégée héritée d'`AbstractAction`. Contient la logique de traitement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `SendTwoFactorOtpAuthRecord` | Record contenant `model_type` et `two_factor_purpose` |

**Retourne :** `ResponseFactory` - Réponse JSON construite selon l'issue

**Comportement selon l'état :**

| État | Status | `errorCode` | `message` |
|------|--------|-------------|-----------|
| Utilisateur non authentifié | 401 | `UNAUTHENTICATED` | `Unauthenticated` |
| Modèle incompatible | 422 | `MODEL_TYPE_MISMATCH` | `Model type mismatch` |
| Modèle non conforme à MustNemesis | 422 | `USER_FORMAT_ERROR` | `User data format not available` |
| Échec d'envoi (rate limit, etc.) | 429 | `TWO_FACTOR_SEND_FAILED` | `Unable to send two-factor code` |
| Exception métier | 422 | `TWO_FACTOR_SEND_ERROR` | (message de l'exception) |
| Succès | 200 | — | `Two-factor code sent` |

---

## Cas d'utilisation

### Cas 1 : Protéger un changement de mot de passe

L'utilisateur souhaite changer son mot de passe. Avant d'autoriser l'opération, le frontend demande un code 2FA.

```bash
POST /api/send-two-factor-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Content-Type: application/json

{
  "model_type": "App\\Models\\User",
  "two_factor_purpose": "change_password"
}
```

**Réponse :**
```json
{
  "message": "Two-factor code sent",
  "status": 200
}
```

Le code est envoyé sur l'email du modèle. L'utilisateur doit ensuite appeler `/api/verify-two-factor-otp` avec le code pour confirmer.

---

### Cas 2 : Protéger la suppression de compte

```bash
POST /api/send-two-factor-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{
  "model_type": "App\\Models\\Pharmacy",
  "two_factor_purpose": "delete_account"
}
```

Chaque `purpose` a son propre espace d'OTP isolé. Un code émis pour `change_password` ne peut pas être utilisé pour `delete_account`.

---

### Cas 3 : Refus de changement d'email sur mauvais modèle

Un utilisateur authentifié en tant que `User` tente de demander un code en se déclarant `Pharmacy`.

```bash
POST /api/send-two-factor-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{
  "model_type": "App\\Models\\Pharmacy",
  "two_factor_purpose": "change_email"
}
```

**Réponse (422) :**
```json
{
  "message": "Model type mismatch",
  "status": 422,
  "errorCode": "MODEL_TYPE_MISMATCH",
  "errors": null
}
```

## Flux d'exécution

```
Requête HTTP
    ↓
Middleware validate.mail.authenticatable
    ↓
Middleware nemesis.token
    ↓
SendTwoFactorOtpRequest → SendTwoFactorOtpAuthRecord
    ↓
SendTwoFactorOtpAction::handle()
    ├─ getCurrentAuthenticatable()
    │   ├─ null → 401 UNAUTHENTICATED
    │   └─ Model → continue
    │
    ├─ class !== model_type
    │   └─ true → 422 MODEL_TYPE_MISMATCH
    │
    ├─ ! instanceof MustNemesis
    │   └─ true → 422 USER_FORMAT_ERROR
    │
    └─ MailAuthenticationService::for(class)
        └─ sendTwoFactorOtp()
            ├─ false → 429 TWO_FACTOR_SEND_FAILED
            ├─ exception → 422 TWO_FACTOR_SEND_ERROR
            └─ true → 200 "Two-factor code sent"
```

## Gestion des erreurs

| Situation | Status | `errorCode` | Origine |
|-----------|--------|-------------|---------|
| Token manquant ou invalide | 401 | `UNAUTHENTICATED` | NemesisHelper retourne `null` |
| `model_type` ≠ modèle authentifié | 422 | `MODEL_TYPE_MISMATCH` | Vérification interne de l'action |
| Modèle n'implémente pas `MustNemesis` | 422 | `USER_FORMAT_ERROR` | Vérification interne de l'action |
| Rate limit dépassé | 429 | `TWO_FACTOR_SEND_FAILED` | `MailAuthenticationService::sendTwoFactorOtp()` retourne `false` |
| Exception métier (ex : DB indisponible) | 422 | `TWO_FACTOR_SEND_ERROR` | `catch (Throwable $e)` |

## Intégration

| Composant | Rôle dans le flux |
|-----------|-------------------|
| `SendTwoFactorOtpRequest` | Valide `model_type` et `two_factor_purpose` |
| `SendTwoFactorOtpAuthRecord` | Transporte les données validées vers l'action |
| `NemesisHelper` | Résout l'utilisateur authentifié depuis le token |
| `MailAuthenticationService` | Envoie l'OTP, gère le rate limit, log l'événement |
| `OtpService` | Crée et persiste l'OTP sous le purpose `two_factor_{context}` |
| `LogRepository` | Journalise l'événement `USER_TWO_FACTOR_SENT` ou `USER_TWO_FACTOR_SEND_FAILED` |
| `Notify` (MailChannel) | Envoie l'email contenant le code |

## Performance

- **Coût principal** : envoi d'email via `MailChannel` (potentiellement lent).
- **Requêtes DB** : 1 count (rate limit) + 1 insert (OTP) + 1 insert log.
- **Recommandation** : si l'envoi email devient un goulot d'étranglement, externaliser l'envoi dans un job queue en surchargeant `buildTwoFactorNotification()` et en passant par `sendLater()`.
- **Rate limiting** : par défaut 3 envois par fenêtre de 300 secondes (`ttl` du purpose). Configurable via `authentication-kit.two_factor_rate_limit`.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| Laravel 12.x | ✅ Complet |
| Laravel 13.x | ✅ Complet |

**Prérequis runtime :**
- Modèle Eloquent implémentant `MustNemesis` et `MailAuthenticatable`
- Table `otps` migrée (package `andydefer/laravel-otp`)
- Table `nemesis_tokens` migrée (package `andydefer/laravel-nemesis`)
- Canal `MailChannel` configuré et fonctionnel

## Exemple complet

### Étape 1 — Le client demande un code

```bash
curl -X POST https://api.example.com/api/send-two-factor-otp \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
  -H "Content-Type: application/json" \
  -d '{
    "model_type": "App\\Models\\User",
    "two_factor_purpose": "change_password"
  }'
```

**Réponse :**
```json
{
  "message": "Two-factor code sent",
  "status": 200
}
```

### Étape 2 — L'utilisateur reçoit le code par email

```
Subject: Your Two-Factor Authentication Code

Your two-factor code for change_password is: 483920
```

### Étape 3 — Le client vérifie le code

```bash
curl -X POST https://api.example.com/api/verify-two-factor-otp \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
  -d '{
    "model_type": "App\\Models\\User",
    "two_factor_purpose": "change_password",
    "code": "483920"
  }'
```

**Réponse :**
```json
{
  "message": "Two-factor verification successful",
  "status": 200
}
```

### Côté PHP (contrôleur consommateur)

```php
<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use App\Models\User;

final class ChangePasswordController
{
    public function __invoke(ChangePasswordRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $service = MailAuthenticationService::for(User::class);

        // L'utilisateur doit avoir préalablement vérifié son 2FA
        $verified = $service->verifyTwoFactorOtp(
            authenticatable: $user,
            purpose: 'change_password',
            code: $request->input('two_factor_code'),
        );

        if (! $verified) {
            return response()->json([
                'message' => 'Invalid or expired 2FA code',
            ], 400);
        }

        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return response()->json(['message' => 'Password changed']);
    }
}
```

## Voir aussi

- `SendTwoFactorOtpRequest` - Validation des entrées HTTP
- `SendTwoFactorOtpAuthRecord` - DTO transporté vers l'action
- `VerifyTwoFactorOtpAction` - Vérification du code 2FA
- `MailAuthenticationService` - Service métier sous-jacent
- `MustNemesis` - Contrat Nemesis pour les modèles authentifiables
- `NemesisHelper` - Helper de résolution du token courant