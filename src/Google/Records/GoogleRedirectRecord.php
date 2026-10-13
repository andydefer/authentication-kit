<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class GoogleRedirectRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $state,
        public readonly string $code_challenge,
        public readonly string $nonce,
    ) {}
}
