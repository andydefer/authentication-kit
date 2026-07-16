<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Configs;

use AndyDefer\AuthenticationKit\Contracts\Configs\AuthenticationKitConfigInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class AuthenticationKitConfig implements AuthenticationKitConfigInterface
{
    private const DEFAULT_TOKEN_NAME = 'authentication-kit';

    private const DEFAULT_PASSWORD_RESET_RATE_LIMIT = 3;

    private const DEFAULT_EMAIL_VERIFICATION_RATE_LIMIT = 5;

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
}
