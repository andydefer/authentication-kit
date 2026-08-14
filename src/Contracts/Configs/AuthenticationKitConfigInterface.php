<?php

// src/Contracts/Configs/AuthenticationKitConfigInterface.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts\Configs;

/**
 * Interface for authentication kit configuration.
 *
 * Provides methods to retrieve authentication configuration values
 * such as token names and other settings.
 */
interface AuthenticationKitConfigInterface
{
    /**
     * Get the token name used for authentication.
     *
     * @return string The token name
     */
    public function getTokenName(): string;

    /**
     * Get the rate limit attempts for password reset OTP.
     *
     * @return int The number of attempts allowed per period
     */
    public function getPasswordResetRateLimitAttempts(): int;

    /**
     * Get the rate limit attempts for email verification OTP.
     *
     * @return int The number of attempts allowed per period
     */
    public function getEmailVerificationRateLimitAttempts(): int;

    /**
     * Check if authentication token should be stored in a cookie.
     *
     * @return bool True if tokens should be stored in cookies, false otherwise
     */
    public function shouldStoreTokenInCookie(): bool;
}
