<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Contracts\Repositories;

/**
 * Interface for authentication log repository.
 *
 * Provides methods for logging authentication events including
 * registration, login, and their success/failure states.
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
    public function logLoginSuccess(
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
    public function logLoginFailure(
        string $modelClass,
        string $email,
        string $error,
        string $errorClass,
    ): void;
}
