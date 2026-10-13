<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Contracts\Configs;

/**
 * Interface for authentication kit configuration.
 *
 * Provides methods to retrieve authentication configuration values
 * such as rate limits, cookie storage behavior, and cookie duration.
 */
interface AuthenticationKitConfigInterface
{
    /**
     * Get the token name used for authentication.
     */
    public function getTokenName(): string;

    /**
     * Get the rate limit attempts for password reset OTP.
     */
    public function getPasswordResetRateLimitAttempts(): int;

    /**
     * Get the rate limit attempts for email verification OTP.
     */
    public function getEmailVerificationRateLimitAttempts(): int;

    /**
     * Get the rate limit attempts for email update OTP.
     */
    public function getEmailUpdateRateLimitAttempts(): int;

    /**
     * Get the rate limit attempts for two-factor authentication OTP.
     */
    public function getTwoFactorRateLimitAttempts(): int;

    /**
     * Check if authentication token should be stored in a cookie.
     */
    public function shouldStoreTokenInCookie(): bool;

    /**
     * Get the authentication cookie duration in minutes.
     */
    public function getCookieDuration(): int;

    /**
     * Get the Google OAuth client ID.
     */
    public function getGoogleClientId(): string;

    /**
     * Get the Google OAuth client secret.
     */
    public function getGoogleClientSecret(): string;

    /**
     * Get the Google OAuth redirect URI.
     */
    public function getGoogleRedirectUri(): string;

    /**
     * Get the Google OIDC discovery / authorization endpoint.
     */
    public function getGoogleAuthorizationEndpoint(): string;

    /**
     * Get the Google OAuth token endpoint.
     */
    public function getGoogleTokenEndpoint(): string;

    /**
     * Get the Google JWKS (public keys) endpoint.
     */
    public function getGoogleJwksEndpoint(): string;

    /**
     * Get the Google OAuth scopes.
     *
     * @return array<int, string>
     */
    public function getGoogleScopes(): array;

    /**
     * Get the Google OAuth state lifetime in seconds.
     */
    public function getGoogleStateLifetime(): int;

    /**
     * Get the Google OAuth PKCE verifier length in bytes.
     */
    public function getGooglePkceVerifierLength(): int;
}
