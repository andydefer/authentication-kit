<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;

interface MailAuthenticationInterface
{
    /**
     * Register a new authenticatable entity.
     */
    public function register(AbstractRecord $record): Model&Authenticatable;

    /**
     * Authenticate a user with email and password.
     */
    public function login(string $email, string $password): ?NemesisTokenRecord;

    /**
     * Logout a user by revoking their current token.
     */
    public function logout(Authenticatable&Model $authenticatable, string $plainToken): bool;

    /**
     * Send password reset OTP to user's email.
     */
    public function sendPasswordResetOtp(string $email): bool;

    /**
     * Reset user's password using a valid OTP code.
     */
    public function resetPassword(string $email, string $code, string $password): bool;

    /**
     * Send email verification OTP to user.
     */
    public function sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool;

    /**
     * Verify user's email using an OTP code.
     */
    public function verifyEmail(string $email, string $code): bool;

    /**
     * Resend email verification OTP.
     */
    public function resendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool;

    /**
     * Check if user's email is verified.
     */
    public function isEmailVerified(Authenticatable&Model $authenticatable): bool;

    /**
     * Check if a user exists with the given email.
     *
     * @param  string  $email  The email to check
     * @return bool True if the user exists
     */
    public function userExists(string $email): bool;
}
