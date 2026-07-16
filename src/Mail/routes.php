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

/*
 * Authentication Routes for Mail-Based Authentication
 *
 * This route file defines all public and protected endpoints for
 * email-based authentication flows including registration, login,
 * email verification, and password reset.
 *
 * @package AndyDefer\AuthenticationKit\Mail
 */

/*
 * Public Authentication Routes
 *
 * These routes are accessible without authentication tokens.
 * They handle user registration, login, password reset, and email verification.
 */
Route::middleware(['validate.mail.authenticatable'])->group(function (): void {

    // Registration
    Route::post('/register', action_route(
        EmailRegisterRequest::class,
        EmailRegisterAction::class
    ))->name('register');

    // Login
    Route::post('/login', action_route(
        EmailLoginRequest::class,
        EmailLoginAction::class
    ))->name('login');

    // Password reset request
    Route::post('/forgot-password', action_route(
        SendPasswordResetLinkRequest::class,
        SendPasswordResetLinkAction::class
    ))->name('password.email');

    // Password reset confirmation
    Route::post('/reset-password', action_route(
        ResetPasswordRequest::class,
        ResetPasswordAction::class
    ))->name('password.update');

    // Email verification
    Route::post('/email/verify', action_route(
        VerifyEmailRequest::class,
        VerifyEmailAction::class
    ))->name('verification.verify');

    /*
     * Protected Authentication Routes
     *
     * These routes require a valid Nemesis authentication token.
     * They handle logout and email verification OTP operations.
     */
    Route::middleware(['nemesis.token'])->group(function (): void {

        // Logout
        Route::post('/logout', action_route(
            EmailLogoutRequest::class,
            EmailLogoutAction::class
        ))->name('logout');

        // Send email verification OTP
        Route::post('/email/verification', action_route(
            SendEmailVerificationRequest::class,
            SendEmailVerificationAction::class
        ))->name('verification.send');

        // Resend email verification OTP
        Route::post('/email/resend', action_route(
            ResendEmailVerificationRequest::class,
            ResendEmailVerificationAction::class
        ))->name('verification.resend');
    });
});
