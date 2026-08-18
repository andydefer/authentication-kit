<?php

declare(strict_types=1);

use AndyDefer\Actions\Http\Requests\EmptyRequest;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Actions\GetCurrentUserAction;
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

Route::name('api.')->group(function (): void {

    /*
     * Public Authentication Routes
     *
     * These routes are accessible without authentication tokens.
     * They handle user registration, login, password reset, and email verification.
     */
    Route::middleware(['validate.mail.authenticatable'])->group(function (): void {

        // Registration
        Route::post('/email-register', action_route(
            EmailRegisterRequest::class,
            EmailRegisterAction::class
        ))->name('email-register');

        // Login
        Route::post('/email-login', action_route(
            EmailLoginRequest::class,
            EmailLoginAction::class
        ))->name('email-login');

        // Password reset request
        Route::post('/send-password-link', action_route(
            SendPasswordResetLinkRequest::class,
            SendPasswordResetLinkAction::class
        ))->name('send-password-link');

        // Password reset confirmation
        Route::post('/reset-password', action_route(
            ResetPasswordRequest::class,
            ResetPasswordAction::class
        ))->name('reset-password');

        // Email verification
        Route::post('/verify-email', action_route(
            VerifyEmailRequest::class,
            VerifyEmailAction::class
        ))->name('verify-email');

        /*
         * Protected Authentication Routes
         *
         * These routes require a valid Nemesis authentication token.
         * They handle logout and email verification OTP operations.
         */
        Route::middleware(['nemesis.token'])->group(function (): void {

            // Logout
            Route::post('/email-logout', action_route(
                EmailLogoutRequest::class,
                EmailLogoutAction::class
            ))->name('email-logout');

            // Send email verification OTP
            Route::post('/send-email-verification', action_route(
                SendEmailVerificationRequest::class,
                SendEmailVerificationAction::class
            ))->name('send-email-verification');

            // Resend email verification OTP
            Route::post('/resend-email-verification', action_route(
                ResendEmailVerificationRequest::class,
                ResendEmailVerificationAction::class
            ))->name('resend-email-verification');

        });

    });

    // ✅ Get current authenticated user (no middleware, we handle it ourselves)
    Route::post('/get-current-user', action_route(
        EmptyRequest::class,
        GetCurrentUserAction::class
    ))->name('get-current-user');

});
