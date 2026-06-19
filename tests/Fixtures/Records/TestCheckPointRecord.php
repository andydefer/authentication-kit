<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Test record for TestCheckPoint model.
 */
final class TestCheckPointRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $location = null,
        public readonly ?string $status = null,
        public readonly ?string $last_seen = null,
        public readonly ?string $type = null,
        public readonly ?DateTimeVO $last_ping_at = null,
        public readonly ?DateTimeVO $created_at = null,
        public readonly ?DateTimeVO $updated_at = null,
        public readonly ?DateTimeVO $deleted_at = null,
    ) {}
}
