<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class MailAuthIdentifierRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $email_field,
        public readonly string $email_verified_at_field,
    ) {}
}
