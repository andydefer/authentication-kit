<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Exceptions;

use RuntimeException;

final class GoogleOAuthException extends RuntimeException
{
    private bool $providerError = false;

    public function isProviderError(): bool
    {
        return $this->providerError;
    }

    public static function providerError(string $reason): self
    {
        $exception = new self($reason);
        $exception->providerError = true;

        return $exception;
    }

    public static function stateNotFound(): self
    {
        return new self('Google OAuth state not found');
    }

    public static function stateExpired(): self
    {
        return new self('Google OAuth state has expired');
    }

    public static function idTokenMalformed(): self
    {
        return new self('Malformed Google ID token');
    }

    public static function idTokenSignatureInvalid(): self
    {
        return new self('Google ID token signature is invalid');
    }

    public static function idTokenIssuerInvalid(): self
    {
        return new self('Google ID token issuer is invalid');
    }

    public static function idTokenAudienceInvalid(): self
    {
        return new self('Google ID token audience is invalid');
    }

    public static function idTokenExpired(): self
    {
        return new self('Google ID token has expired');
    }

    public static function idTokenIssuedInFuture(): self
    {
        return new self('Google ID token was issued in the future');
    }

    public static function idTokenNonceMismatch(): self
    {
        return new self('Google ID token nonce mismatch');
    }

    public static function idTokenUnknownKey(): self
    {
        return new self('No matching public key found for Google ID token');
    }

    public static function emailNotVerified(): self
    {
        return new self('Google email is not verified');
    }

    public static function tokenExchangeFailed(string $reason): self
    {
        return new self("Google token exchange failed: {$reason}");
    }
}
