<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class GoogleTokensData extends AbstractData
{
    public function __construct(
        public readonly string $idToken,
        public readonly string $accessToken,
        public readonly ?string $refreshToken,
        public readonly int $expiresIn,
        public readonly string $tokenType,
        public readonly string $scope,
    ) {}
}
