<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for mail-based authentication services.
 *
 * Defines the core authentication operations for email-based user management,
 * including registration, login, logout, password reset, and email verification.
 */
interface MailAuthenticationInterface
{
    /**
     * Registers a new authenticatable entity.
     *
     * @param  AbstractRecord  $record  The registration record containing user data
     * @return Model&Authenticatable The newly created authenticatable model
     */
    public function register(AbstractRecord $record): Model&Authenticatable;

    /**
     * Authenticates a user with email and password.
     *
     * @param  string  $email  The user's email address
     * @param  string  $password  The user's password
     * @return NemesisTokenRecord|null The authentication token record on success, null on failure
     */
    public function login(string $email, string $password): ?NemesisTokenRecord;

    /**
     * Logs out a user by revoking their current token.
     *
     * @param  Authenticatable&Model  $authenticatable  The authenticatable entity
     * @param  string  $plainToken  The plain text token to revoke
     * @return bool True on successful logout, false otherwise
     */
    public function logout(Authenticatable&Model $authenticatable, string $plainToken): bool;

    /**
     * Sends a password reset OTP to the user's email address.
     *
     * @param  string  $email  The user's email address
     * @return bool True if the OTP was sent successfully, false otherwise
     */
    public function sendPasswordResetOtp(string $email): bool;

    /**
     * Resets the user's password using a valid OTP code.
     *
     * @param  string  $email  The user's email address
     * @param  string  $code  The OTP verification code
     * @param  string  $password  The new password
     * @return bool True if the password was reset successfully, false otherwise
     */
    public function resetPassword(string $email, string $code, string $password): bool;

    /**
     * Sends an email verification OTP to the user.
     *
     * @param  Authenticatable&Model  $authenticatable  The authenticatable entity
     * @return bool True if the OTP was sent successfully, false otherwise
     */
    public function sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool;

    /**
     * Verifies the user's email using an OTP code.
     *
     * @param  string  $email  The user's email address
     * @param  string  $code  The OTP verification code
     * @return bool True if the email was verified successfully, false otherwise
     */
    public function verifyEmail(string $email, string $code): bool;

    /**
     * Resends the email verification OTP to the user.
     *
     * @param  Authenticatable&Model  $authenticatable  The authenticatable entity
     * @return bool True if the OTP was resent successfully, false otherwise
     */
    public function resendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool;

    /**
     * Checks if the user's email is verified.
     *
     * @param  Authenticatable&Model  $authenticatable  The authenticatable entity
     * @return bool True if the email is verified, false otherwise
     */
    public function isEmailVerified(Authenticatable&Model $authenticatable): bool;

    /**
     * Checks if a user exists with the given email address.
     *
     * @param  string  $email  The email address to check
     * @return bool True if a user with the email exists, false otherwise
     */
    public function userExists(string $email): bool;
}
