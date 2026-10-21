<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Tests\Integration\Google\Fixtures\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;

final class TestGoogleUserData extends AbstractData
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $email,
    ) {}
}
