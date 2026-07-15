<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class SendPasswordResetLinkRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $email,
    ) {}
}
