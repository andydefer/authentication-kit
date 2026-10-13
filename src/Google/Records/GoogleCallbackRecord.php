<?php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Google\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class GoogleCallbackRecord extends AbstractRecord
{
    public function __construct(
        public readonly string $code,
        public readonly string $state,
        public readonly ?string $error = null,
        public readonly ?string $error_description = null,
    ) {}
}
