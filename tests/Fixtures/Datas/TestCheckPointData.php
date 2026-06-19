<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Datas;

use AndyDefer\DomainStructures\Abstracts\AbstractData;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Test data for TestCheckPoint model.
 */
final class TestCheckPointData extends AbstractData
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $location = null,
        public readonly ?string $status = null,
        public readonly ?string $lastSeen = null,
        public readonly ?string $type = null,
        public readonly ?DateTimeVO $lastPingAt = null,
        public readonly ?DateTimeVO $createdAt = null,
        public readonly ?DateTimeVO $updatedAt = null,
        public readonly ?DateTimeVO $deletedAt = null,
    ) {}
}
