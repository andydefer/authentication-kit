<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts;

use AndyDefer\AuthenticationKit\Contracts\Authenticatable;
use AndyDefer\AuthenticationKit\Mail\Records\LoginResultRecord;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\DomainStructures\Interfaces\Transformable;
use AndyDefer\Nemesis\Models\NemesisToken;
use Illuminate\Database\Eloquent\Model;

/**
 * Interface for mail-based authentication services.
 *
 * Defines the core authentication operations for email-based user management,
 * including registration, login, logout, password reset, email verification,
 * email update, and two-factor authentication.
 */
interface MailAuthenticationInterface
{
    /**
     * Registers a new authenticatable entity.
     *
     * @param  AbstractRecord  $record  The registration record containing user data
     * @return array{user: Model&Authenticatable, token: NemesisToken|null, plain_token: string|null}
     */
    public function register(AbstractRecord $record): array;

    /**
     * Authenticates a user with email and password.
     */
    public function login(string $email, string $password): ?LoginResultRecord;

    /**
     * Logs out a user by revoking their current token.
     */
    public function logout(Authenticatable&Model $authenticatable, string $plainToken): bool;

    /**
     * Sends a password reset OTP to the user's email address.
     */
    public function sendPasswordResetOtp(string $email): bool;

    /**
     * Resets the user's password using a valid OTP code.
     */
    public function resetPassword(string $email, string $code, string $password): bool;

    /**
     * Sends an email verification OTP to the user.
     */
    public function sendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool;

    /**
     * Verifies the user's email using an OTP code.
     */
    public function verifyEmail(string $email, string $code): bool;

    /**
     * Resends the email verification OTP to the user.
     */
    public function resendEmailVerificationOtp(Authenticatable&Model $authenticatable): bool;

    /**
     * Checks if the user's email is verified.
     */
    public function isEmailVerified(Authenticatable&Model $authenticatable): bool;

    /**
     * Checks if a user exists with the given email address.
     */
    public function userExists(string|Transformable $email): bool;

    /**
     * Sends an OTP to the user's new email address to confirm an email update.
     */
    public function sendEmailUpdateOtp(Authenticatable&Model $authenticatable, string $newEmail): bool;

    /**
     * Confirms the email update with the OTP code.
     */
    public function updateEmail(Authenticatable&Model $authenticatable, string $newEmail, string $code): bool;

    /**
     * Sends a two-factor authentication OTP for a sensitive action.
     *
     * @param  Authenticatable&Model  $authenticatable  The authenticatable entity
     * @param  string  $purpose  The sensitive action purpose (e.g. "change_email")
     * @return bool True if the OTP was sent successfully
     */
    public function sendTwoFactorOtp(Authenticatable&Model $authenticatable, string $purpose): bool;

    /**
     * Verifies a two-factor authentication OTP for a sensitive action.
     *
     * @param  Authenticatable&Model  $authenticatable  The authenticatable entity
     * @param  string  $purpose  The sensitive action purpose
     * @param  string  $code  The OTP code to verify
     * @return bool True if the OTP is valid
     */
    public function verifyTwoFactorOtp(Authenticatable&Model $authenticatable, string $purpose, string $code): bool;

    /**
     * Get the password validation rules.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function getPasswordValidationRules(): array;
}
