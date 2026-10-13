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

    private const DEFAULT_COOKIE_DURATION = 525600;

    private const DEFAULT_GOOGLE_AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const DEFAULT_GOOGLE_TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    private const DEFAULT_GOOGLE_JWKS_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/certs';

    private const DEFAULT_GOOGLE_SCOPES = ['openid', 'email', 'profile'];

    private const DEFAULT_GOOGLE_STATE_LIFETIME = 600;

    private const DEFAULT_GOOGLE_PKCE_VERIFIER_LENGTH = 64;

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

    /**
     * {@inheritDoc}
     */
    public function getCookieDuration(): int
    {
        return (int) $this->config->get(
            'authentication-kit.cookie_duration',
            self::DEFAULT_COOKIE_DURATION
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getGoogleClientId(): string
    {
        return (string) $this->config->get(
            'authentication-kit.google.client_id',
            ''
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getGoogleClientSecret(): string
    {
        return (string) $this->config->get(
            'authentication-kit.google.client_secret',
            ''
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getGoogleRedirectUri(): string
    {
        return (string) $this->config->get(
            'authentication-kit.google.redirect_uri',
            ''
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getGoogleAuthorizationEndpoint(): string
    {
        return (string) $this->config->get(
            'authentication-kit.google.authorization_endpoint',
            self::DEFAULT_GOOGLE_AUTHORIZATION_ENDPOINT
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getGoogleTokenEndpoint(): string
    {
        return (string) $this->config->get(
            'authentication-kit.google.token_endpoint',
            self::DEFAULT_GOOGLE_TOKEN_ENDPOINT
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getGoogleJwksEndpoint(): string
    {
        return (string) $this->config->get(
            'authentication-kit.google.jwks_endpoint',
            self::DEFAULT_GOOGLE_JWKS_ENDPOINT
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getGoogleScopes(): array
    {
        $scopes = $this->config->get(
            'authentication-kit.google.scopes',
            self::DEFAULT_GOOGLE_SCOPES
        );

        return is_array($scopes) ? array_values($scopes) : self::DEFAULT_GOOGLE_SCOPES;
    }

    /**
     * {@inheritDoc}
     */
    public function getGoogleStateLifetime(): int
    {
        return (int) $this->config->get(
            'authentication-kit.google.state_lifetime',
            self::DEFAULT_GOOGLE_STATE_LIFETIME
        );
    }

    /**
     * {@inheritDoc}
     */
    public function getGooglePkceVerifierLength(): int
    {
        return (int) $this->config->get(
            'authentication-kit.google.pkce_verifier_length',
            self::DEFAULT_GOOGLE_PKCE_VERIFIER_LENGTH
        );
    }
}
