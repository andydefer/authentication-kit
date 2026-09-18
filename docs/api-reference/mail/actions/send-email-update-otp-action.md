# SendEmailUpdateOtpAction - Référence Technique

## Description

Action HTTP qui envoie un OTP sur la nouvelle adresse email d'un utilisateur pour confirmer un changement d'email.

## Hiérarchie / Implémentations

```
AbstractAction
    └── SendEmailUpdateOtpAction

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
4. Délègue l'envoi à `MailAuthenticationService::sendEmailUpdateOtp()`
5. Retourne une réponse JSON normalisée

Elle constitue la **première moitié** du flux de changement d'email. La **seconde moitié** est assurée par `UpdateEmailAction`, qui confirme le changement après vérification du code.

## Installation

Action interne au package `andydefer/authentication-kit`. La route doit être enregistrée avec les middlewares `validate.mail.authenticatable` et `nemesis.token` :

```php
Route::middleware(['validate.mail.authenticatable', 'nemesis.token'])
    ->post('/api/send-email-update-otp', action_route(
        SendEmailUpdateOtpRequest::class,
        SendEmailUpdateOtpAction::class
    ))
    ->name('send-email-update-otp');
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
| `$record` | `SendEmailUpdateOtpAuthRecord` | Record contenant `model_type` et `new_email` |

**Retourne :** `ResponseFactory` - Réponse JSON construite selon l'issue

**Comportement selon l'état :**

| État | Status | `errorCode` | `message` |
|------|--------|-------------|-----------|
| Utilisateur non authentifié | 401 | `UNAUTHENTICATED` | `Unauthenticated` |
| Modèle incompatible | 422 | `MODEL_TYPE_MISMATCH` | `Model type mismatch` |
| Modèle non conforme à MustNemesis | 422 | `USER_FORMAT_ERROR` | `User data format not available` |
| Échec d'envoi (email déjà pris, rate limit) | 429 | `EMAIL_UPDATE_SEND_FAILED` | `Unable to send email update code` |
| Exception métier | 422 | `EMAIL_UPDATE_SEND_ERROR` | (message de l'exception) |
| Succès | 200 | — | `Email update code sent` |

---

## Cas d'utilisation

### Cas 1 : Changement d'email standard

L'utilisateur souhaite migrer son compte vers une nouvelle adresse.

```bash
POST /api/send-email-update-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...
Content-Type: application/json

{
  "model_type": "App\\Models\\User",
  "email": "new-email@example.com"
}
```

**Réponse :**
```json
{
  "message": "Email update code sent",
  "status": 200
}
```

Le code est envoyé **sur la nouvelle adresse** (pas l'ancienne). C'est une protection contre les erreurs de saisie : si l'utilisateur se trompe d'adresse, il ne pourra pas confirmer.

---

### Cas 2 : Refus lorsque la nouvelle adresse est déjà utilisée

Un autre compte a déjà cette adresse.

```bash
POST /api/send-email-update-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{
  "model_type": "App\\Models\\User",
  "email": "already-taken@example.com"
}
```

**Réponse (429) :**
```json
{
  "message": "Unable to send email update code",
  "status": 429,
  "errorCode": "EMAIL_UPDATE_SEND_FAILED",
  "errors": null
}
```

> Note : le status `429` est utilisé ici par commodité, mais il couvre aussi bien le rate limit que le conflit d'email. Le `LogRepository` distingue les deux cas (`ErrorType::EMAIL_ALREADY_TAKEN` vs `ErrorType::RATE_LIMIT_EXCEEDED`).

---

### Cas 3 : Rate limit dépassé

L'utilisateur a déjà demandé 3 codes dans la fenêtre de validité.

```bash
POST /api/send-email-update-otp
Authorization: Bearer eyJhbGciOiJIUzI1NiIs...

{
  "model_type": "App\\Models\\User",
  "email": "new-email@example.com"
}
```

**Réponse (429) :**
```json
{
  "message": "Unable to send email update code",
  "status": 429,
  "errorCode": "EMAIL_UPDATE_SEND_FAILED",
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
SendEmailUpdateOtpRequest → SendEmailUpdateOtpAuthRecord
    ↓
SendEmailUpdateOtpAction::handle()
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
        └─ sendEmailUpdateOtp()
            ├─ userExists(new_email) → 429 EMAIL_UPDATE_SEND_FAILED
            ├─ rate limit dépassé → 429 EMAIL_UPDATE_SEND_FAILED
            ├─ exception → 422 EMAIL_UPDATE_SEND_ERROR
            └─ envoi réussi → 200 "Email update code sent"
```

## Gestion des erreurs

| Situation | Status | `errorCode` | Origine |
|-----------|--------|-------------|---------|
| Token manquant ou invalide | 401 | `UNAUTHENTICATED` | NemesisHelper retourne `null` |
| `model_type` ≠ modèle authentifié | 422 | `MODEL_TYPE_MISMATCH` | Vérification interne de l'action |
| Modèle n'implémente pas `MustNemesis` | 422 | `USER_FORMAT_ERROR` | Vérification interne de l'action |
| Email déjà pris | 429 | `EMAIL_UPDATE_SEND_FAILED` | `MailAuthenticationService::sendEmailUpdateOtp()` retourne `false` |
| Rate limit dépassé | 429 | `EMAIL_UPDATE_SEND_FAILED` | `MailAuthenticationService::sendEmailUpdateOtp()` retourne `false` |
| Exception métier | 422 | `EMAIL_UPDATE_SEND_ERROR` | `catch (Throwable $e)` |

## Intégration

| Composant | Rôle dans le flux |
|-----------|-------------------|
| `SendEmailUpdateOtpRequest` | Valide `model_type` et `email`, normalise l'email en lowercase |
| `SendEmailUpdateOtpAuthRecord` | Transporte les données validées vers l'action |
| `NemesisHelper` | Résout l'utilisateur authentifié depuis le token |
| `MailAuthenticationService` | Envoie l'OTP, vérifie l'unicité de l'email, applique le rate limit |
| `OtpService` | Crée et persiste l'OTP sous le purpose `email_update` |
| `LogRepository` | Journalise `USER_EMAIL_UPDATE_SUCCESS` ou `USER_EMAIL_UPDATE_FAILED` |
| `Notify` (MailChannel) | Envoie l'email contenant le code **à la nouvelle adresse** |
| `UpdateEmailAction` | Consomme l'OTP et finalise le changement (dans un second appel) |

## Performance

- **Coût principal** : envoi d'email vers la nouvelle adresse.
- **Requêtes DB** :
  - 1 `SELECT` sur `userExists()` (index sur `email`)
  - 1 `SELECT COUNT` sur `isRateLimited()` (index sur `identifier_type` + `identifier_id`)
  - 1 `INSERT` sur `otps`
  - 1 `INSERT` sur log
- **Recommandation** : pour un envoi à haute fréquence, envisager la queue après surcharge de `buildEmailUpdateNotification()`.
- **Rate limiting** : par défaut 3 envois par fenêtre de 600 secondes (`ttl` du purpose `email_update`). Configurable via `authentication-kit.email_update_rate_limit`.

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
- Canal `MailChannel` configuré et fonctionnel

## Exemple complet

### Étape 1 — Le client demande le changement d'email

```bash
curl -X POST https://api.example.com/api/send-email-update-otp \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
  -H "Content-Type: application/json" \
  -d '{
    "model_type": "App\\Models\\User",
    "email": "new-email@example.com"
  }'
```

**Réponse :**
```json
{
  "message": "Email update code sent",
  "status": 200
}
```

### Étape 2 — L'utilisateur reçoit le code sur la nouvelle adresse

```
To: new-email@example.com
Subject: Confirm Your New Email Address

Use this code to confirm your new email: 483920
```

### Étape 3 — Le client confirme avec `UpdateEmailAction`

```bash
curl -X POST https://api.example.com/api/update-email \
  -H "Authorization: Bearer eyJhbGciOiJIUzI1NiIs..." \
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

`email_verified_at` est remis à `null` : l'utilisateur doit ensuite revérifier sa nouvelle adresse via `sendEmailVerificationOtp()`.

### Côté PHP — orchestrer les deux étapes

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
    public function request(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $service = MailAuthenticationService::for(User::class);

        $sent = $service->sendEmailUpdateOtp(
            authenticatable: $user,
            newEmail: $request->input('email'),
        );

        if (! $sent) {
            return response()->json([
                'message' => 'Unable to send email update code',
            ], 429);
        }

        return response()->json(['message' => 'Code sent']);
    }

    public function confirm(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $service = MailAuthenticationService::for(User::class);

        $updated = $service->updateEmail(
            authenticatable: $user,
            newEmail: $request->input('email'),
            code: $request->input('code'),
        );

        if (! $updated) {
            return response()->json([
                'message' => 'Invalid or expired code',
            ], 400);
        }

        return response()->json([
            'message' => 'Email updated. Please verify your new email.',
        ]);
    }
}
```

## Voir aussi

- `SendEmailUpdateOtpRequest` - Validation des entrées HTTP
- `SendEmailUpdateOtpAuthRecord` - DTO transporté vers l'action
- `UpdateEmailAction` - Confirmation du changement d'email
- `MailAuthenticationService::sendEmailUpdateOtp()` - Logique métier
- `MustNemesis` - Contrat Nemesis pour les modèles authentifiables
- `NemesisHelper` - Helper de résolution du token courant