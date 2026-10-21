<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\ValueObjects;

use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\Associative;

/**
 * Immutable value object carrying the verified Google web callback payload.
 *
 * Produced by GoogleOAuthService::callback() and passed to
 * GoogleOAuthInterface::onCallbackSuccess().
 */
final class GoogleCallbackVO extends AbstractValueObject
{
    public function __construct(
        private readonly string $idToken,
        private readonly string $accessToken,
        private readonly int $expiresIn,
        private readonly string $tokenType,
        private readonly string $scope,
        private readonly GoogleIdTokenClaimsVO $claims,
        private readonly OperationType $operation,
    ) {}

    public function getIdToken(): string
    {
        return $this->idToken;
    }

    public function getAccessToken(): string
    {
        return $this->accessToken;
    }

    public function getExpiresIn(): int
    {
        return $this->expiresIn;
    }

    public function getTokenType(): string
    {
        return $this->tokenType;
    }

    public function getScope(): string
    {
        return $this->scope;
    }

    public function getClaims(): GoogleIdTokenClaimsVO
    {
        return $this->claims;
    }

    public function getOperation(): OperationType
    {
        return $this->operation;
    }

    public function getValue(): Associative
    {
        return Associative::from([
            'idToken' => $this->idToken,
            'accessToken' => $this->accessToken,
            'expiresIn' => $this->expiresIn,
            'tokenType' => $this->tokenType,
            'scope' => $this->scope,
            'claims' => $this->claims,
            'operation' => $this->operation,
        ]);
    }

    public function equals(AbstractValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->idToken === $other->idToken
            && $this->accessToken === $other->accessToken
            && $this->expiresIn === $other->expiresIn
            && $this->tokenType === $other->tokenType
            && $this->scope === $other->scope
            && $this->claims->equals($other->claims)
            && $this->operation === $other->operation;
    }
}
