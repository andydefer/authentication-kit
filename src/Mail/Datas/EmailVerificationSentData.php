<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class EmailVerificationSentData extends AbstractData
{
    public function __construct(
        public readonly string $message,
        public readonly string $email,
        public readonly string $sentAt,
        public readonly bool $alreadyVerified = false,
    ) {}
}
