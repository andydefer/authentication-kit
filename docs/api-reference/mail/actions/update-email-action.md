# UpdateEmailAction - Référence Technique

## Description

Action HTTP qui confirme un changement d'email en vérifiant l'OTP reçu sur la nouvelle adresse.

## Hiérarchie / Implémentations

```
AbstractAction
    └── UpdateEmailAction

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
4. Délègue la confirmation à `MailAuthenticationService::updateEmail()`
5. Retourne une réponse JSON normalisée

Elle constitue la **seconde moitié** du flux de changement d'email. La **première moitié** est assurée par `SendEmailUpdateOtpAction`, qui envoie l'OTP sur la nouvelle adresse.

## Installation

Action interne au package `andydefer/authentication-kit`. La route doit être enregistrée avec les middlewares `validate.mail.authenticatable` et `nemesis.token` :

```php
Route::middleware(['validate.mail.authenticatable', 'nemesis.token'])
    ->post('/api/update-email', action_route(
        UpdateEmailRequest::class,
        UpdateEmailAction::class
    ))
    ->name('update-email');
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
| `$record` | `UpdateEmailAuthRecord` | Record contenant `model_type`, `new_email` et `code` |

**Retourne :** `ResponseFactory` - Réponse JSON construite selon l'issue

**Comportement selon l'état :**

| État | Status | `errorCode` | `message` |
|------|--------|-------------|-----------|
| Utilisateur non authentifié | 401 | `UNAUTHENTICATED` | `Unauthenticated` |
| Modèle incompatible | 422 | `MODEL_TYPE_MISMATCH` | `Model type mismatch` |
| Modèle non conforme à MustNemesis | 422 | `USER_FORMAT_ERROR` | `User data format not available` |
| OTP invalide, expiré ou email pris | 400 | `EMAIL_UPDATE_FAILED` | `Invalid or expired code, or email already taken` |
| Exception métier | 422 | `EMAIL_UPDATE_ERROR` | (message de l'exception) |
| Succès | 200 | — | `Email updated successfully. Please verify your new email.` |

---

## Cas d'utilisation

### Cas 1 : Confirmation d'un changement d'email

L'utilisateur a préalablement appelé `/api/send-email-update-otp` et reçu un code sur sa nouvelle adresse.

```bash
POST /api/update-email
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Content-Type: application/json

{
  "model_type": "App\\Models\\User",
  "email": "new-email@example.com",
  "code": "483920"
}
```

**Réponse (200) :**
```json
{
  "message": "Email updated successfully. Please verify your new email.",
  "status": 200
}
```

Après succès :

- L'email est mis à jour
- `email_verified_at` est remis à `null`
- L'utilisateur doit revérifier sa nouvelle adresse via `/api/send-email-verification`

---

### Cas 2 : Rejet d'un code invalide

```bash
POST /api/update-email
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{
  "model_type": "App\\Models\\User",
  "email": "new-email@example.com",
  "code": "000000"
}
```

**Réponse (400) :**
```json
{
  "message": "Invalid or expired code, or email already taken",
  "status": 400,
  "errorCode": "EMAIL_UPDATE_FAILED",
  "errors": null
}
```

L'email d'origine reste **inchangé** — aucune modification n'est faite tant que la vérification n'a pas réussi.

---

### Cas 3 : Rejet d'un code déjà utilisé

Un OTP est à usage unique. Toute tentative de réutilisation échoue.

**Premier appel :** succès (200) → email modifié.
**Second appel avec le même code :** échec (400).

```json
{
  "message": "Invalid or expired code, or email already taken",
  "status": 400,
  "errorCode": "EMAIL_UPDATE_FAILED",
  "errors": null
}
```

---

### Cas 4 : Utilisation d'un code pour un autre purpose

Un code émis pour `email_verification` ne peut pas être utilisé pour confirmer un changement d'email. Le `purpose` de l'OTP doit être exactement `email_update`.

```bash
POST /api/update-email
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{
  "model_type": "App\\Models\\User",
  "email": "new-email@example.com",
  "code": "483920"
}
```

**Réponse (400) :** `EMAIL_UPDATE_FAILED` — le code n'existe pas sous le purpose `email_update`.

## Flux d'exécution

```
Requête HTTP
    ↓
Middleware validate.mail.authenticatable
    ↓
Middleware nemesis.token
    ↓
UpdateEmailRequest → UpdateEmailAuthRecord
    ↓
UpdateEmailAction::handle()
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
        └─ updateEmail(authenticatable, newEmail, code)
            ├─ OtpService::verify(purpose=email_update)
            │   └─ false → 400 EMAIL_UPDATE_FAILED
            │
            ├─ update email + reset email_verified_at
            ├─ log event
            ├─ afterUpdateEmail()
            └─ true → 200 "Email updated successfully"
```

## Gestion des erreurs

| Situation | Status | `errorCode` | Origine |
|-----------|--------|-------------|---------|
| Token manquant ou invalide | 401 | `UNAUTHENTICATED` | NemesisHelper retourne `null` |
| `model_type` ≠ modèle authentifié | 422 | `MODEL_TYPE_MISMATCH` | Vérification interne de l'action |
| Modèle n'implémente pas `MustNemesis` | 422 | `USER_FORMAT_ERROR` | Vérification interne de l'action |
| Code invalide ou expiré | 400 | `EMAIL_UPDATE_FAILED` | `MailAuthenticationService::updateEmail()` retourne `false` |
| Code déjà utilisé | 400 | `EMAIL_UPDATE_FAILED` | `MailAuthenticationService::updateEmail()` retourne `false` |
| Code pour mauvais purpose | 400 | `EMAIL_UPDATE_FAILED` | `MailAuthenticationService::updateEmail()` retourne `false` |
| Tentatives dépassées | 400 | `EMAIL_UPDATE_FAILED` | `MailAuthenticationService::updateEmail()` retourne `false` |
| Exception métier | 422 | `EMAIL_UPDATE_ERROR` | `catch (Throwable $e)` |

## Intégration

| Composant | Rôle dans le flux |
|-----------|-------------------|
| `UpdateEmailRequest` | Valide `model_type`, `email` et `code`, normalise l'email en lowercase |
| `UpdateEmailAuthRecord` | Transporte les données validées vers l'action |
| `NemesisHelper` | Résout l'utilisateur authentifié depuis le token |
| `MailAuthenticationService` | Vérifie l'OTP et applique le changement d'email |
| `OtpService` | Vérifie et consomme l'OTP sous le purpose `email_update` |
| `LogRepository` | Journalise `USER_EMAIL_UPDATE_SUCCESS` ou `USER_EMAIL_UPDATE_FAILED` |
| `SendEmailUpdateOtpAction` | Étape précédente obligatoire pour générer l'OTP |

## Performance

- **Coût principal** : 1 `SELECT` sur `otps` pour valider l'OTP.
- **Requêtes DB** :
  - 1 `SELECT` sur `otps` (vérification `code` + `purpose` + `identifier`)
  - 1 `UPDATE` sur le modèle cible (email + email_verified_at)
  - 1 `INSERT` sur log
- **Aucune opération lourde** : pas d'envoi email, pas de hash cryptographique additionnel.
- **Idempotence** : l'OTP est consommé (marqué comme utilisé) au premier succès. Toute réutilisation ultérieure échoue naturellement grâce au mécanisme d'`OtpService::verify()`.
- **Rate limiting** : pas de rate limit spécifique sur la vérification — la protection est assurée par le `maxAttempts` de l'OTP lui-même (3 par défaut).

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
- Un OTP émis par `SendEmailUpdateOtpAction` dans la fenêtre de validité

## Exemple complet

### Étape 1 — Demande initiale (rappel)

```bash
curl -X POST https://api.example.com/api/send-email-update-otp \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
  -H "Content-Type: application/json" \
  -d '{
    "model_type": "App\\Models\\User",
    "email": "new-email@example.com"
  }'
```

**Réponse :** `{ "message": "Email update code sent", "status": 200 }`

### Étape 2 — L'utilisateur reçoit le code par email

```
To: new-email@example.com
Subject: Confirm Your New Email Address

Use this code to confirm your new email: 483920
```

### Étape 3 — Confirmation du changement

```bash
curl -X POST https://api.example.com/api/update-email \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
  -H "Content-Type: application/json" \
  -d '{
    "model_type": "App\\Models\\User",
    "email": "new-email@example.com",
    "code": "483920"
  }'
```

**Réponse :**
```json
{
  "message": "Email updated successfully. Please verify your new email.",
  "status": 200
}
```

### Étape 4 — L'utilisateur doit revérifier sa nouvelle adresse

`email_verified_at` a été remis à `null`. Il faut appeler `/api/send-email-verification` puis `/api/verify-email`.

```bash
curl -X POST https://api.example.com/api/send-email-verification \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..."
```

Puis :

```bash
curl -X POST https://api.example.com/api/verify-email \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
  -d '{
    "model_type": "App\\Models\\User",
    "email": "new-email@example.com",
    "token": "654321"
  }'
```

### Côté PHP — orchestrer le flow complet

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use AndyDefer\AuthenticationKit\Mail\Services\MailAuthenticationService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmailChangeController
{
    public function confirm(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $service = MailAuthenticationService::for(User::class);

        // 1. Confirmer le changement d'email
        $updated = $service->updateEmail(
            authenticatable: $user,
            newEmail: $request->input('email'),
            code: $request->input('code'),
        );

        if (! $updated) {
            return response()->json([
                'message' => 'Invalid or expired code, or email already taken',
            ], 400);
        }

        // 2. Envoyer automatiquement le code de vérification email
        //    pour la nouvelle adresse
        $service->sendEmailVerificationOtp($user);

        return response()->json([
            'message' => 'Email updated. Verification code sent to your new address.',
            'requires_verification' => true,
        ]);
    }
}
```

## Voir aussi

- `UpdateEmailRequest` - Validation des entrées HTTP
- `UpdateEmailAuthRecord` - DTO transporté vers l'action
- `SendEmailUpdateOtpAction` - Étape précédente (envoi de l'OTP)
- `MailAuthenticationService::updateEmail()` - Logique métier
- `SendEmailVerificationAction` - Revérification après changement
- `MustNemesis` - Contrat Nemesis pour les modèles authentifiables
- `NemesisHelper` - Helper de résolution du token courant