<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Test data for TestUser model.
 *
 * This record represents the data structure for TestUser model
 * and is used for testing token authentication in a realistic context.
 */
final class TestUserData extends AbstractData
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?DateTimeVO $emailVerifiedAt = null,
        public readonly ?DateTimeVO $createdAt = null,
        public readonly ?DateTimeVO $updatedAt = null,
        public readonly ?DateTimeVO $deletedAt = null,
    ) {}
}
