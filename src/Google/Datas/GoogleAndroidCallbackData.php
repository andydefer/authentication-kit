<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Datas;

use AndyDefer\AuthenticationKit\Google\ValueObjects\GoogleIdTokenClaimsVO;
use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Android flow callback payload returned by GoogleOAuthInterface::onAndroidCallbackSuccess().
 */
final class GoogleAndroidCallbackData extends AbstractData
{
    public function __construct(
        public readonly GoogleIdTokenClaimsVO $claims,
        public readonly string $modelType,
        public readonly StrictAssociative $auth,
        public readonly string $token,
        public readonly bool $isNewUser,
        public readonly StrictAssociative $metadata = new StrictAssociative,
    ) {}
}
