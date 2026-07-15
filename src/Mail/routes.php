<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Actions\ResendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Actions\ResetPasswordAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendPasswordResetLinkAction;
use AndyDefer\AuthenticationKit\Mail\Actions\VerifyEmailAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLogoutRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\ResendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\ResetPasswordRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendPasswordResetLinkRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\VerifyEmailRequest;
use Illuminate\Support\Facades\Route;

// ========================================================================
// Groupe principal : Toutes les routes nécessitent validate.mail.authenticatable
// ========================================================================

Route::middleware(['validate.mail.authenticatable'])->group(function () {

    // ========================================================================
    // Routes publiques (pas de token Nemesis requis)
    // ========================================================================

    // ✅ Inscription
    Route::post('/register', action_route(
        EmailRegisterRequest::class,
        EmailRegisterAction::class
    ))->name('register');

    // ✅ Connexion
    Route::post('/login', action_route(
        EmailLoginRequest::class,
        EmailLoginAction::class
    ))->name('login');

    // ✅ Envoyer un OTP de réinitialisation
    Route::post('/forgot-password', action_route(
        SendPasswordResetLinkRequest::class,
        SendPasswordResetLinkAction::class
    ))->name('password.email');

    // ✅ Réinitialiser le mot de passe avec OTP
    Route::post('/reset-password', action_route(
        ResetPasswordRequest::class,
        ResetPasswordAction::class
    ))->name('password.update');

    // ✅ Vérifier l'email avec OTP
    Route::post('/email/verify', action_route(
        VerifyEmailRequest::class,
        VerifyEmailAction::class
    ))->name('verification.verify');

    // ========================================================================
    // Sous-groupe : Routes authentifiées (nécessitent un token Nemesis)
    // ========================================================================

    Route::middleware(['nemesis.token'])->group(function () {
        // ✅ Déconnexion
        Route::post('/logout', action_route(
            EmailLogoutRequest::class,
            EmailLogoutAction::class
        ))->name('logout');

        // ✅ Envoyer un OTP de vérification d'email
        Route::post('/email/verification', action_route(
            SendEmailVerificationRequest::class,
            SendEmailVerificationAction::class
        ))->name('verification.send');

        // ✅ Renvoyer un OTP de vérification d'email
        Route::post('/email/resend', action_route(
            ResendEmailVerificationRequest::class,
            ResendEmailVerificationAction::class
        ))->name('verification.resend');
    });
});
