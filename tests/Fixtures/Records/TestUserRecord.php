<?php

declare(strict_types=1);

namespace AndyDefer\Nemesis\Tests\Fixtures\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\PhpVo\ValueObjects\DateTimeVO;

/**
 * Test record for TestUser model.
 *
 * This record represents the data structure for TestUser model
 * and is used for testing token authentication in a realistic context.
 */
final class TestUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly ?int $id = null,
        public readonly ?string $name = null,
        public readonly ?string $email = null,
        public readonly ?DateTimeVO $email_verified_at = null,
        public readonly ?DateTimeVO $created_at = null,
        public readonly ?DateTimeVO $updated_at = null,
        public readonly ?DateTimeVO $deleted_at = null,
    ) {}
}
