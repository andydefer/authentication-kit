<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Mail\Fixtures\Data;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class TestUserMailData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $createdAt,
    ) {}
}
