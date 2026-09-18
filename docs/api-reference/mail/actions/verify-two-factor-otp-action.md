# VerifyTwoFactorOtpAction - Référence Technique

## Description

Action HTTP qui vérifie un OTP de double authentification soumis par un utilisateur pour confirmer une action sensible.

## Hiérarchie / Implémentations

```
AbstractAction
    └── VerifyTwoFactorOtpAction

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
4. Délègue la vérification à `MailAuthenticationService::verifyTwoFactorOtp()`
5. Retourne une réponse JSON normalisée

Elle constitue la **seconde moitié** du flux de double authentification. La **première moitié** est assurée par `SendTwoFactorOtpAction`, qui envoie l'OTP sur l'email de l'utilisateur.

## Installation

Action interne au package `andydefer/authentication-kit`. La route doit être enregistrée avec les middlewares `validate.mail.authenticatable` et `nemesis.token` :

```php
Route::middleware(['validate.mail.authenticatable', 'nemesis.token'])
    ->post('/api/verify-two-factor-otp', action_route(
        VerifyTwoFactorOtpRequest::class,
        VerifyTwoFactorOtpAction::class
    ))
    ->name('verify-two-factor-otp');
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
| `$record` | `VerifyTwoFactorOtpAuthRecord` | Record contenant `model_type`, `two_factor_purpose` et `code` |

**Retourne :** `ResponseFactory` - Réponse JSON construite selon l'issue

**Comportement selon l'état :**

| État | Status | `errorCode` | `message` |
|------|--------|-------------|-----------|
| Utilisateur non authentifié | 401 | `UNAUTHENTICATED` | `Unauthenticated` |
| Modèle incompatible | 422 | `MODEL_TYPE_MISMATCH` | `Model type mismatch` |
| Modèle non conforme à MustNemesis | 422 | `USER_FORMAT_ERROR` | `User data format not available` |
| Code invalide, expiré ou déjà utilisé | 400 | `TWO_FACTOR_VERIFY_FAILED` | `Invalid or expired two-factor code` |
| Exception métier | 422 | `TWO_FACTOR_VERIFY_ERROR` | (message de l'exception) |
| Succès | 200 | — | `Two-factor verification successful` |

---

## Cas d'utilisation

### Cas 1 : Confirmation d'un changement de mot de passe

L'utilisateur a préalablement appelé `/api/send-two-factor-otp` avec `two_factor_purpose = "change_password"`.

```bash
POST /api/verify-two-factor-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Content-Type: application/json

{
  "model_type": "App\\Models\\User",
  "two_factor_purpose": "change_password",
  "code": "483920"
}
```

**Réponse (200) :**
```json
{
  "message": "Two-factor verification successful",
  "status": 200
}
```

Le code est **consommé** : toute tentative de réutilisation échouera.

---

### Cas 2 : Rejet d'un code invalide

```bash
POST /api/verify-two-factor-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{
  "model_type": "App\\Models\\User",
  "two_factor_purpose": "change_password",
  "code": "000000"
}
```

**Réponse (400) :**
```json
{
  "message": "Invalid or expired two-factor code",
  "status": 400,
  "errorCode": "TWO_FACTOR_VERIFY_FAILED",
  "errors": null
}
```

L'action sensible protégée n'est **pas** autorisée. Le `LogRepository` enregistre l'échec sous `USER_TWO_FACTOR_VERIFY_FAILED`.

---

### Cas 3 : Rejet d'un code utilisé pour un autre purpose

Un code émis pour `change_email` ne peut pas être utilisé pour vérifier `change_password`.

```bash
POST /api/verify-two-factor-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{
  "model_type": "App\\Models\\User",
  "two_factor_purpose": "change_password",
  "code": "483920"
}
```

**Réponse (400) :** `TWO_FACTOR_VERIFY_FAILED`.

Chaque `purpose` dispose de son propre espace d'OTP isolé sous le préfixe `two_factor_{purpose}`. Il n'y a aucune contamination possible entre actions.

---

### Cas 4 : Rejet après dépassement des tentatives

`OtpService` autorise 3 tentatives par défaut (`maxAttempts: 3`). Après 3 échecs, l'OTP est invalidé.

```bash
# Tentative 4
POST /api/verify-two-factor-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{
  "model_type": "App\\Models\\User",
  "two_factor_purpose": "change_password",
  "code": "483920"
}
```

**Réponse (400) :** `TWO_FACTOR_VERIFY_FAILED` — même avec le bon code.

---

### Cas 5 : Refus d'un code expiré

Les OTP 2FA ont une durée de vie de **300 secondes** (5 minutes). Après expiration, la vérification échoue.

```json
{
  "message": "Invalid or expired two-factor code",
  "status": 400,
  "errorCode": "TWO_FACTOR_VERIFY_FAILED",
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
VerifyTwoFactorOtpRequest → VerifyTwoFactorOtpAuthRecord
    ↓
VerifyTwoFactorOtpAction::handle()
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
        └─ verifyTwoFactorOtp(authenticatable, purpose, code)
            ├─ OtpService::verify(purpose=two_factor_{purpose})
            │   ├─ false → 400 TWO_FACTOR_VERIFY_FAILED
            │   └─ true  → consomme l'OTP + log success
            │
            ├─ afterVerifyTwoFactorOtp()
            └─ true → 200 "Two-factor verification successful"
```

## Gestion des erreurs

| Situation | Status | `errorCode` | Origine |
|-----------|--------|-------------|---------|
| Token manquant ou invalide | 401 | `UNAUTHENTICATED` | NemesisHelper retourne `null` |
| `model_type` ≠ modèle authentifié | 422 | `MODEL_TYPE_MISMATCH` | Vérification interne de l'action |
| Modèle n'implémente pas `MustNemesis` | 422 | `USER_FORMAT_ERROR` | Vérification interne de l'action |
| Code invalide | 400 | `TWO_FACTOR_VERIFY_FAILED` | `verifyTwoFactorOtp()` retourne `false` |
| Code expiré | 400 | `TWO_FACTOR_VERIFY_FAILED` | `verifyTwoFactorOtp()` retourne `false` |
| Code déjà utilisé | 400 | `TWO_FACTOR_VERIFY_FAILED` | `verifyTwoFactorOtp()` retourne `false` |
| Code pour mauvais purpose | 400 | `TWO_FACTOR_VERIFY_FAILED` | `verifyTwoFactorOtp()` retourne `false` |
| Tentatives dépassées | 400 | `TWO_FACTOR_VERIFY_FAILED` | `verifyTwoFactorOtp()` retourne `false` |
| Exception métier | 422 | `TWO_FACTOR_VERIFY_ERROR` | `catch (Throwable $e)` |

## Intégration

| Composant | Rôle dans le flux |
|-----------|-------------------|
| `VerifyTwoFactorOtpRequest` | Valide `model_type`, `two_factor_purpose` et `code` |
| `VerifyTwoFactorOtpAuthRecord` | Transporte les données validées vers l'action |
| `NemesisHelper` | Résout l'utilisateur authentifié depuis le token |
| `MailAuthenticationService` | Vérifie l'OTP et déclenche le hook `afterVerifyTwoFactorOtp` |
| `OtpService` | Vérifie et consomme l'OTP sous le purpose `two_factor_{context}` |
| `LogRepository` | Journalise `USER_TWO_FACTOR_VERIFIED` ou `USER_TWO_FACTOR_VERIFY_FAILED` |
| `SendTwoFactorOtpAction` | Étape précédente obligatoire pour générer l'OTP |

## Performance

- **Coût principal** : 1 `SELECT` sur `otps` pour valider l'OTP.
- **Requêtes DB** :
  - 1 `SELECT` sur `otps` (vérification `code` + `purpose` + `identifier`)
  - 1 `UPDATE` sur `otps` (marquage comme utilisé + incrément des tentatives)
  - 1 `INSERT` sur log
- **Aucune opération lourde** : pas d'envoi email, pas de hash cryptographique additionnel.
- **Idempotence** : l'OTP est consommé au premier succès. Toute réutilisation ultérieure échoue naturellement.
- **Protection contre le brute force** : `maxAttempts` de l'OTP limite à 3 tentatives. Après échec, l'utilisateur doit redemander un code.
- **Rate limiting global** : pas sur la vérification elle-même, mais sur l'envoi via `SendTwoFactorOtpAction` (3 envois par fenêtre de 300s).

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| Laravel 12.x | ✅ Complet |
| Laravel 13.x | ✅ Complet |

**Prérequis runtime :**
- Modèle Eloquent implémentant `MustNemesis` et `MailAuthenticatable`
- Table `otps` migrée
- Table `nemesis_tokens` migrée
- Un OTP 2FA émis par `SendTwoFactorOtpAction` dans la fenêtre de validité

## Exemple complet

### Étape 1 — Demander un code 2FA

```bash
curl -X POST https://api.example.com/api/send-two-factor-otp \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
  -H "Content-Type: application/json" \
  -d '{
    "model_type": "App\\Models\\User",
    "two_factor_purpose": "change_password"
  }'
```

**Réponse :** `{ "message": "Two-factor code sent", "status": 200 }`

### Étape 2 — L'utilisateur reçoit le code par email

```
Subject: Your Two-Factor Authentication Code

Your two-factor code for change_password is: 483920
```

### Étape 3 — Vérifier le code et exécuter l'action sensible

```bash
curl -X POST https://api.example.com/api/verify-two-factor-otp \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
  -H "Content-Type: application/json" \
  -d '{
    "model_type": "App\\Models\\User",
    "two_factor_purpose": "change_password",
    "code": "483920"
  }'
```

**Réponse (200) :**
```json
{
  "message": "Two-factor verification successful",
  "status": 200
}
```

### Côté PHP — protéger une action sensible

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class ChangePasswordController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $service = MailAuthenticationService::for(User::class);

        // 1. Vérifier le code 2FA
        $verified = $service->verifyTwoFactorOtp(
            authenticatable: $user,
            purpose: 'change_password',
            code: $request->input('two_factor_code'),
        );

        if (! $verified) {
            return response()->json([
                'message' => 'Invalid or expired two-factor code',
            ], 400);
        }

        // 2. Le code est valide : appliquer le changement
        $user->password = Hash::make($request->input('new_password'));
        $user->save();

        return response()->json([
            'message' => 'Password changed successfully',
        ]);
    }
}
```

### Côté PHP — protéger une suppression de compte

```php
<?php

declare(strict_types=1);

final class DeleteAccountController
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $service = MailAuthenticationService::for(User::class);

        $verified = $service->verifyTwoFactorOtp(
            authenticatable: $user,
            purpose: 'delete_account',
            code: $request->input('two_factor_code'),
        );

        if (! $verified) {
            return response()->json([
                'message' => 'Invalid or expired two-factor code',
            ], 400);
        }

        $user->delete();

        return response()->json(['message' => 'Account deleted']);
    }
}
```

## Pattern recommandé — 2FA comme étape obligatoire

Le 2FA ne se substitue pas à l'authentification par token : il s'y ajoute. L'utilisateur est **déjà authentifié** (via Nemesis), mais son action sensible est bloquée tant qu'il n'a pas prouvé sa présence via un code envoyé sur son email.

```
Authentification (token) + Vérification 2FA (OTP) = Action sensible autorisée
```

Chaque `purpose` doit être traité comme un **scope isolé** :

| Purpose | Usage typique |
|---------|---------------|
| `change_password` | Modification du mot de passe |
| `change_email` | Confirmation d'un changement d'email |
| `delete_account` | Suppression définitive du compte |
| `change_2fa_settings` | Activation/désactivation du 2FA |
| `approve_payment` | Validation d'une transaction sensible |

Un code émis pour un purpose ne peut jamais être réutilisé pour un autre.

## Voir aussi

- `VerifyTwoFactorOtpRequest` - Validation des entrées HTTP
- `VerifyTwoFactorOtpAuthRecord` - DTO transporté vers l'action
- `SendTwoFactorOtpAction` - Étape précédente (envoi de l'OTP)
- `MailAuthenticationService::verifyTwoFactorOtp()` - Logique métier
- `MustNemesis` - Contrat Nemesis pour les modèles authentifiables
- `NemesisHelper` - Helper de résolution du token courant
- `OtpService` - Moteur OTP sous-jacent