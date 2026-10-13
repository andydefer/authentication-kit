<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Structs;

use AndyDefer\PhpClient\Abstracts\Struct;

final class GoogleTokenExchangeBodyStruct extends Struct
{
    public function __construct(
        public readonly string $code,
        public readonly string $client_id,
        public readonly string $client_secret,
        public readonly string $redirect_uri,
        public readonly string $grant_type,
        public readonly string $code_verifier,
    ) {}
}
