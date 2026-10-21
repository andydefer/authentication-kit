<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

/**
 * Android callback input: the id_token obtained directly by the Google
 * Credential Manager SDK on the device, plus the target model type.
 *
 * This record is not part of the Authorization Code flow — there is no
 * `code` or `state` to exchange. The id_token is verified directly
 * against Google's JWKS, then the service decides login vs register.
 */
final class GoogleAndroidCallbackRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $model_type,
        public readonly string $id_token,
    ) {}
}
