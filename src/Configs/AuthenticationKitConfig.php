<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Configs;

use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

/**
 * Authentication Kit configuration manager.
 *
 * Reads configuration values from the Laravel config repository and
 * exposes them through a typed, testable interface.
 */
final class AuthenticationKitConfig implements AuthenticationKitConfigInterface
{
    private const DEFAULT_TOKEN_NAME = 'authentication-kit';

    private const DEFAULT_PASSWORD_RESET_RATE_LIMIT = 3;

    private const DEFAULT_EMAIL_VERIFICATION_RATE_LIMIT = 5;

    private const DEFAULT_EMAIL_UPDATE_RATE_LIMIT = 3;

    private const DEFAULT_TWO_FACTOR_RATE_LIMIT = 3;

    private const DEFAULT_STORE_TOKEN_IN_COOKIE = true;

    public function __construct(
        private readonly ConfigRepository $config,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function getTokenName(): string
    {
        return $this->config->get(
            'authentication-kit.token_name',
            self::DEFAULT_TOKEN_NAME
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getPasswordResetRateLimitAttempts(): int
    {
        return (int) $this->config->get(
            'authentication-kit.password_reset_rate_limit',
            self::DEFAULT_PASSWORD_RESET_RATE_LIMIT
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getEmailVerificationRateLimitAttempts(): int
    {
        return (int) $this->config->get(
            'authentication-kit.email_verification_rate_limit',
            self::DEFAULT_EMAIL_VERIFICATION_RATE_LIMIT
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getEmailUpdateRateLimitAttempts(): int
    {
        return (int) $this->config->get(
            'authentication-kit.email_update_rate_limit',
            self::DEFAULT_EMAIL_UPDATE_RATE_LIMIT
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getTwoFactorRateLimitAttempts(): int
    {
        return (int) $this->config->get(
            'authentication-kit.two_factor_rate_limit',
            self::DEFAULT_TWO_FACTOR_RATE_LIMIT
        );
    }

    /**
     * {@inheritDoc}
     */
    public function shouldStoreTokenInCookie(): bool
    {
        return (bool) $this->config->get(
            'authentication-kit.store_token_in_cookie',
            self::DEFAULT_STORE_TOKEN_IN_COOKIE
        );
    }
}
