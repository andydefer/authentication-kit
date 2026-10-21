<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\ValueObjects;

use AndyDefer\DomainStructures\Abstracts\AbstractValueObject;
use AndyDefer\DomainStructures\Utils\Associative;

/**
 * Immutable value object carrying the verified Google Android callback payload.
 *
 * Produced by GoogleOAuthService::androidCallback() and passed to
 * GoogleOAuthInterface::onAndroidCallbackSuccess().
 */
final class GoogleAndroidCallbackVO extends AbstractValueObject
{
    public function __construct(
        private readonly GoogleIdTokenClaimsVO $claims,
        private readonly string $modelType,
    ) {}

    public function getClaims(): GoogleIdTokenClaimsVO
    {
        return $this->claims;
    }

    public function getModelType(): string
    {
        return $this->modelType;
    }

    public function getValue(): Associative
    {
        return Associative::from([
            'claims' => $this->claims,
            'modelType' => $this->modelType,
        ]);
    }

    public function equals(AbstractValueObject $other): bool
    {
        if (! $other instanceof self) {
            return false;
        }

        return $this->claims->equals($other->claims)
            && $this->modelType === $other->modelType;
    }
}
