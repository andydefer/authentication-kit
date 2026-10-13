<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class GoogleIdTokenClaimsData extends AbstractData
{
    public function __construct(
        public readonly string $sub,
        public readonly string $email,
        public readonly bool $emailVerified,
        public readonly string $iss,
        public readonly string $aud,
        public readonly int $exp,
        public readonly int $iat,
        public readonly ?string $nonce = null,
        public readonly ?string $name = null,
        public readonly ?string $givenName = null,
        public readonly ?string $familyName = null,
        public readonly ?string $picture = null,
        public readonly ?string $locale = null,
        public readonly ?string $hd = null,
    ) {}
}
