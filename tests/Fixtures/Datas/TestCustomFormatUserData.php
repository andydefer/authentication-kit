<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Test data for TestCustomFormatUser model.
 */
final class TestCustomFormatUserData extends AbstractData
{
    public function __construct(
        public readonly ?int $userId = null,
        public readonly ?string $fullName = null,
        public readonly ?bool $isVerified = null,
        public readonly ?string $customField = null,
        public readonly ?string $type = null,
        public readonly ?DateTimeVO $createdAt = null,
        public readonly ?DateTimeVO $updatedAt = null,
        public readonly ?DateTimeVO $deletedAt = null,
    ) {}
}
