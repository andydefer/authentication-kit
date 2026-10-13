<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Datas;

use AndyDefer\AuthenticationKit\Google\Enums\OperationType;
use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class GoogleCallbackData extends AbstractData
{
    public function __construct(
        public readonly string $idToken,
        public readonly string $accessToken,
        public readonly int $expiresIn,
        public readonly string $tokenType,
        public readonly string $scope,
        public readonly GoogleIdTokenClaimsData $claims,
        public readonly OperationType $operation,
    ) {}
}
