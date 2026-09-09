<?php

// src/Mail/Records/LoginResultRecord.php

declare(strict_types=1);

namespace AndyDefer\AuthenticationKit\Mail\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\Nemesis\Records\NemesisTokenRecord;

final class LoginResultRecord extends AbstractRecord
{
    public function __construct(
        public readonly NemesisTokenRecord $token_record,
        public readonly string $plain_token,
    ) {}
}
