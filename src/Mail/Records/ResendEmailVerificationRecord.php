<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class ResendEmailVerificationRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $modelType,
        public readonly int $authId,
    ) {}
}
