<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Datas;

use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleIdTokenClaimsVO;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Web flow callback payload returned by GoogleOAuthInterface::onCallbackSuccess().
 */
final class GoogleCallbackData extends AbstractData
{
    public function __construct(
        public readonly string $idToken,
        public readonly string $accessToken,
        public readonly int $expiresIn,
        public readonly string $tokenType,
        public readonly string $scope,
        public readonly GoogleIdTokenClaimsVO $claims,
        public readonly OperationType $operation,
        public readonly StrictAssociative $auth,
        public readonly string $token,
        public readonly bool $isNewUser,
        public readonly StrictAssociative $metadata = new StrictAssociative,
    ) {}
}
