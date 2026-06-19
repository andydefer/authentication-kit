<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class AuthIdentifierRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $password_field,
        public readonly string $remember_token_field,
    ) {}
}
