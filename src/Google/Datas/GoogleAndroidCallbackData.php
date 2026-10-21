<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

/**
 * Android callback success payload.
 *
 * Mirrors the client-side AuthAndroidGoogleData data class:
 *  - message   : human-readable status
 *  - auth      : the model formatted via nemesisFormat()
 *  - token     : the issued Nemesis plain token
 *  - modelType : the model FQCN received on input (e.g. "App\\Models\\User")
 *  - isNewUser : true when the account was just created
 *  - claims    : the verified Google ID-token claims
 */
final class GoogleAndroidCallbackData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly StrictAssociative $auth,
        public readonly string $token,
        public readonly string $modelType,
        public readonly bool $isNewUser,
        public readonly GoogleIdTokenClaimsData $claims,
    ) {}
}
