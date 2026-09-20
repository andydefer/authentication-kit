<?php

declare(strict_types=1);

use AndyDefer\AuthenticationKit\Mail\Actions\EmailLoginAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailLogoutAction;
use AndyDefer\AuthenticationKit\Mail\Actions\EmailRegisterAction;
use AndyDefer\AuthenticationKit\Mail\Actions\GetCurrentUserAction;
use AndyDefer\AuthenticationKit\Mail\Actions\ResendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Actions\ResetPasswordAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendEmailUpdateOtpAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendEmailVerificationAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendPasswordResetLinkAction;
use AndyDefer\AuthenticationKit\Mail\Actions\SendTwoFactorOtpAction;
use AndyDefer\AuthenticationKit\Mail\Actions\UpdateEmailAction;
use AndyDefer\AuthenticationKit\Mail\Actions\VerifyEmailAction;
use AndyDefer\AuthenticationKit\Mail\Actions\VerifyTwoFactorOtpAction;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLoginRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailLogoutRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\EmailRegisterRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\GetCurrentUserRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\ResendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\ResetPasswordRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendEmailUpdateOtpRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendEmailVerificationRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendPasswordResetLinkRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\SendTwoFactorOtpRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\UpdateEmailRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\VerifyEmailRequest;
use AndyDefer\AuthenticationKit\Mail\Requests\VerifyTwoFactorOtpRequest;
use Illuminate\Support\Facades\Route;

/*
 * Authentication Routes for Mail-Based Authentication
 *
 * This route file defines all public and protected endpoints for
 * email-based authentication flows including registration, login,
 * email verification, email update, password reset, and two-factor
 * authentication.
 *
 * @package AndyDefer\AuthenticationKit\Mail
 */

Route::name('api.')->group(function (): void {

    /*
     * Public Authentication Routes
     *
     * These routes are accessible without authentication tokens.
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

            // Email update
            Route::post('/send-email-update-otp', action_route(
                SendEmailUpdateOtpRequest::class,
                SendEmailUpdateOtpAction::class
            ))->name('send-email-update-otp');

            Route::patch('/update-email', action_route(
                UpdateEmailRequest::class,
                UpdateEmailAction::class
            ))->name('update-email');

            // Two-factor authentication
            Route::post('/send-two-factor-otp', action_route(
                SendTwoFactorOtpRequest::class,
                SendTwoFactorOtpAction::class
            ))->name('send-two-factor-otp');

            Route::post('/verify-two-factor-otp', action_route(
                VerifyTwoFactorOtpRequest::class,
                VerifyTwoFactorOtpAction::class
            ))->name('verify-two-factor-otp');

        });

    });

    // Get current authenticated user (no middleware, action handles it)
    Route::post('/get-current-user', action_route(
        GetCurrentUserRequest::class,
        GetCurrentUserAction::class
    ))->name('get-current-user');

});
