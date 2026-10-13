<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class GoogleStateRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $code_verifier,
        public readonly string $nonce,
        public readonly int $expires_at,
    ) {}
}