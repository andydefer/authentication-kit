<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts\Repositories;

/**
 * Interface for authentication log repository.
 *
 * Provides methods for logging authentication events including
 * registration, login, logout, and their success/failure states.
 */
interface LogRepositoryInterface
{
    /**
     * Log a successful registration event.
     *
     * @param  int  $authId  The ID of the authenticated user/entity
     * @param  string  $modelClass  The class name of the authenticatable model
     * @param  bool  $withToken  Whether a token was generated during registration
     */
    public function logRegistrationSuccess(
        int $authId,
        string $modelClass,
        bool $withToken,
    ): void;

    /**
     * Log a failed registration event.
     *
     * @param  string  $modelClass  The class name of the authenticatable model
     * @param  string  $error  The error message
     * @param  string  $errorClass  The class name of the exception thrown
     */
    public function logRegistrationFailure(
        string $modelClass,
        string $error,
        string $errorClass,
    ): void;

    /**
     * Log a successful login event.
     *
     * @param  int  $authId  The ID of the authenticated user/entity
     * @param  string  $modelClass  The class name of the authenticatable model
     * @param  string  $email  The email used for login
     */
    public function loginSuccess(
        int $authId,
        string $modelClass,
        string $email,
    ): void;

    /**
     * Log a failed login event.
     *
     * @param  string  $modelClass  The class name of the authenticatable model
     * @param  string  $email  The email used for login attempt
     * @param  string  $error  The error message
     * @param  string  $errorClass  The class name of the exception thrown
     */
    public function loginFailure(
        string $modelClass,
        string $email,
        string $error,
        string $errorClass,
    ): void;

    /**
     * Log a successful logout event.
     *
     * @param  int  $authId  The ID of the authenticated user/entity
     * @param  string  $modelClass  The class name of the authenticatable model
     * @param  string  $email  The email of the user who logged out
     */
    public function logoutSuccess(
        int $authId,
        string $modelClass,
        string $email,
    ): void;

    /**
     * Log a failed logout event.
     *
     * @param  string  $modelClass  The class name of the authenticatable model
     * @param  string  $email  The email of the user who attempted to logout
     * @param  string  $error  The error message
     * @param  string  $errorClass  The class name of the exception thrown
     */
    public function logoutFailure(
        string $modelClass,
        string $email,
        string $error,
        string $errorClass,
    ): void;

    /**
     * Log a successful password reset link sent event.
     *
     * @param  string  $email  The email address where the reset link was sent
     * @param  bool  $success  Whether the reset link was sent successfully
     * @param  string|null  $error  The error message if failed
     * @param  string|null  $errorClass  The class name of the exception if failed
     */
    public function logPasswordResetLinkSent(
        string $email,
        bool $success,
        ?string $error = null,
        ?string $errorClass = null,
    ): void;

    /**
     * Log a successful password reset event.
     *
     * @param  string  $email  The email address whose password was reset
     */
    public function logPasswordResetSuccess(
        string $email,
    ): void;

    /**
     * Log a failed password reset event.
     *
     * @param  string  $email  The email address that failed to reset password
     * @param  string  $error  The error message
     * @param  string  $errorClass  The class name of the exception thrown
     */
    public function logPasswordResetFailure(
        string $email,
        string $error,
        string $errorClass,
    ): void;

    /**
     * Log a successful email verification event.
     *
     * @param  string  $email  The email address verified
     * @param  string  $modelClass  The class name of the authenticatable model
     * @param  bool  $alreadyVerified  Whether the email was already verified
     */
    public function logVerificationSuccess(
        string $email,
        string $modelClass,
        bool $alreadyVerified = false,
    ): void;

    /**
     * Log a failed email verification event.
     *
     * @param  string  $email  The email address that failed verification
     * @param  string  $modelClass  The class name of the authenticatable model
     * @param  string  $error  The error message
     * @param  string  $errorClass  The class name of the exception thrown
     */
    public function logVerificationFailure(
        string $email,
        string $modelClass,
        string $error,
        string $errorClass,
    ): void;
}
