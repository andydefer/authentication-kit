<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Test record for TestCustomFormatUser model.
 */
final class TestCustomFormatUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $user_id = null,
        public readonly ?string $full_name = null,
        public readonly ?bool $is_verified = null,
        public readonly ?string $custom_field = null,
        public readonly ?string $type = null,
        public readonly ?DateTimeVO $created_at = null,
        public readonly ?DateTimeVO $updated_at = null,
        public readonly ?DateTimeVO $deleted_at = null,
    ) {}
}
