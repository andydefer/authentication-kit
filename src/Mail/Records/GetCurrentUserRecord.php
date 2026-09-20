<?php

// src/Mail/Records/GetCurrentUserRecord.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\AuthenticationKit\Mail\Enums\CurrentUserMode;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class GetCurrentUserRecord extends AbstractRecord
{
    public function __construct(
        public readonly CurrentUserMode $mode = CurrentUserMode::SIMPLE,
    ) {}
}
